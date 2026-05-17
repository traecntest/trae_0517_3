<?php
function getInstances($params) {
    $db = getDb();
    $page = $params['page'] ?? 1;
    $pageSize = $params['pageSize'] ?? 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = [];
    $bindValues = [];
    
    if (!empty($params['keyword'])) {
        $where[] = "title LIKE ?";
        $bindValues[] = "%{$params['keyword']}%";
    }
    if (isset($params['status']) && $params['status'] !== '') {
        $where[] = "status = ?";
        $bindValues[] = $params['status'];
    }
    if (!empty($params['workflow_id'])) {
        $where[] = "workflow_id = ?";
        $bindValues[] = $params['workflow_id'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $db->prepare("SELECT wi.*, w.name as workflow_name, w.color as workflow_color, w.icon as workflow_icon, 
        u.name as starter_name 
        FROM workflow_instances wi 
        LEFT JOIN workflows w ON wi.workflow_id = w.id 
        LEFT JOIN users u ON wi.started_by = u.id 
        $whereClause ORDER BY wi.id DESC LIMIT ? OFFSET ?");
    foreach ($bindValues as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }
    $stmt->bindValue(count($bindValues) + 1, (int)$pageSize, SQLITE3_INTEGER);
    $stmt->bindValue(count($bindValues) + 2, (int)$offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $instances = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['workflow'] = [
            'name' => $row['workflow_name'],
            'color' => $row['workflow_color'],
            'icon' => $row['workflow_icon']
        ];
        $row['starter'] = ['name' => $row['starter_name']];
        unset($row['workflow_name'], $row['workflow_color'], $row['workflow_icon'], $row['starter_name']);
        $instances[] = $row;
    }
    
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM workflow_instances $whereClause");
    foreach ($bindValues as $i => $val) {
        $countStmt->bindValue($i + 1, $val);
    }
    $countResult = $countStmt->execute();
    $total = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
    
    response([
        'data' => $instances,
        'total' => (int)$total,
        'current_page' => (int)$page,
        'per_page' => (int)$pageSize
    ]);
}

function getMyInstances($userId, $params) {
    $params['started_by'] = $userId;
    getInstances($params);
}

function getInstance($id) {
    $db = getDb();
    $stmt = $db->prepare("SELECT wi.*, w.name as workflow_name, u.name as starter_name 
        FROM workflow_instances wi 
        LEFT JOIN workflows w ON wi.workflow_id = w.id 
        LEFT JOIN users u ON wi.started_by = u.id 
        WHERE wi.id = ?");
    $stmt->bindValue(1, $id);
    $result = $stmt->execute();
    $instance = $result->fetchArray(SQLITE3_ASSOC);
    
    $instance['workflow'] = ['name' => $instance['workflow_name']];
    $instance['starter'] = ['name' => $instance['starter_name']];
    unset($instance['workflow_name'], $instance['starter_name']);
    $instance['variables'] = $instance['variables'] ? json_decode($instance['variables'], true) : null;
    
    $taskStmt = $db->prepare("SELECT wt.*, u.name as assignee_name, u2.name as creator_name 
        FROM workflow_tasks wt 
        LEFT JOIN users u ON wt.assignee_id = u.id 
        LEFT JOIN users u2 ON wt.created_by = u2.id 
        WHERE wt.instance_id = ? ORDER BY wt.id");
    $taskStmt->bindValue(1, $id);
    $taskResult = $taskStmt->execute();
    $tasks = [];
    while ($row = $taskResult->fetchArray(SQLITE3_ASSOC)) {
        $row['assignee'] = ['name' => $row['assignee_name']];
        $row['creator'] = ['name' => $row['creator_name']];
        unset($row['assignee_name'], $row['creator_name']);
        $tasks[] = $row;
    }
    $instance['tasks'] = $tasks;
    
    $logStmt = $db->prepare("SELECT wil.*, u.name as operator_name 
        FROM workflow_instance_logs wil 
        LEFT JOIN users u ON wil.operator_id = u.id 
        WHERE wil.instance_id = ? ORDER BY wil.id DESC");
    $logStmt->bindValue(1, $id);
    $logResult = $logStmt->execute();
    $logs = [];
    while ($row = $logResult->fetchArray(SQLITE3_ASSOC)) {
        $row['operator'] = ['name' => $row['operator_name']];
        $row['extra'] = $row['extra'] ? json_decode($row['extra'], true) : null;
        unset($row['operator_name']);
        $logs[] = $row;
    }
    $instance['logs'] = $logs;
    
    response($instance);
}

function getInstanceFlowChart($id) {
    $db = getDb();
    $stmt = $db->prepare("SELECT wi.*, w.name as workflow_name FROM workflow_instances wi 
        LEFT JOIN workflows w ON wi.workflow_id = w.id WHERE wi.id = ?");
    $stmt->bindValue(1, $id);
    $result = $stmt->execute();
    $instance = $result->fetchArray(SQLITE3_ASSOC);
    
    $nodeStmt = $db->prepare("SELECT * FROM workflow_nodes WHERE workflow_id = ?");
    $nodeStmt->bindValue(1, $instance['workflow_id']);
    $nodeResult = $nodeStmt->execute();
    $nodes = [];
    while ($row = $nodeResult->fetchArray(SQLITE3_ASSOC)) {
        $row['flow_status'] = 'pending';
        $row['config'] = $row['config'] ? json_decode($row['config'], true) : null;
        
        $taskStmt = $db->prepare("SELECT * FROM workflow_tasks WHERE instance_id = ? AND node_id = ? AND status IN (1, 2)");
        $taskStmt->bindValue(1, $id);
        $taskStmt->bindValue(2, $row['node_id']);
        $taskResult = $taskStmt->execute();
        $task = $taskResult->fetchArray(SQLITE3_ASSOC);
        if ($task) {
            $row['flow_status'] = $task['status'] == 1 ? 'approved' : 'rejected';
        }
        
        if ($instance['current_node_id'] == $row['id']) {
            $row['flow_status'] = 'current';
        }
        
        $nodes[] = $row;
    }
    
    $edgeStmt = $db->prepare("SELECT * FROM workflow_edges WHERE workflow_id = ?");
    $edgeStmt->bindValue(1, $instance['workflow_id']);
    $edgeResult = $edgeStmt->execute();
    $edges = [];
    while ($row = $edgeResult->fetchArray(SQLITE3_ASSOC)) {
        $row['condition'] = $row['condition'] ? json_decode($row['condition'], true) : null;
        $edges[] = $row;
    }
    
    response(['instance' => $instance, 'nodes' => $nodes, 'edges' => $edges]);
}

function createInstance($input, $userId) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM workflows WHERE id = ?");
    $stmt->bindValue(1, $input['workflow_id']);
    $result = $stmt->execute();
    $workflow = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$workflow || $workflow['status'] != 1) {
        response(null, 400, '流程未发布，无法启动');
    }
    
    $instanceStmt = $db->prepare("INSERT INTO workflow_instances 
        (workflow_id, workflow_version, title, description, status, started_by, started_at, variables) 
        VALUES (?, ?, ?, ?, 0, ?, CURRENT_TIMESTAMP, ?)");
    $instanceStmt->bindValue(1, $input['workflow_id']);
    $instanceStmt->bindValue(2, $workflow['version']);
    $instanceStmt->bindValue(3, $input['title']);
    $instanceStmt->bindValue(4, $input['description'] ?? '');
    $instanceStmt->bindValue(5, $userId);
    $instanceStmt->bindValue(6, isset($input['variables']) ? json_encode($input['variables']) : null);
    $instanceStmt->execute();
    
    $instanceId = $db->lastInsertRowID();
    
    $nodeStmt = $db->prepare("SELECT * FROM workflow_nodes WHERE workflow_id = ? AND type = 'start'");
    $nodeStmt->bindValue(1, $input['workflow_id']);
    $nodeResult = $nodeStmt->execute();
    $startNode = $nodeResult->fetchArray(SQLITE3_ASSOC);
    
    $startNodeId = $startNode['id'];
    
    $db->exec("UPDATE workflow_instances SET current_node_id = $startNodeId WHERE id = $instanceId");
    
    $logStmt = $db->prepare("INSERT INTO workflow_instance_logs 
        (instance_id, node_id, action, comment, operator_id) 
        VALUES (?, ?, 'start', '流程启动', ?)");
    $logStmt->bindValue(1, $instanceId);
    $logStmt->bindValue(2, $startNode['node_id']);
    $logStmt->bindValue(3, $userId);
    $logStmt->execute();
    
    $edgeStmt = $db->prepare("SELECT * FROM workflow_edges WHERE workflow_id = ? AND source_node_id = ?");
    $edgeStmt->bindValue(1, $input['workflow_id']);
    $edgeStmt->bindValue(2, $startNode['node_id']);
    $edgeResult = $edgeStmt->execute();
    $firstEdge = $edgeResult->fetchArray(SQLITE3_ASSOC);
    
    if ($firstEdge) {
        $nextNodeStmt = $db->prepare("SELECT * FROM workflow_nodes WHERE workflow_id = ? AND node_id = ?");
        $nextNodeStmt->bindValue(1, $input['workflow_id']);
        $nextNodeStmt->bindValue(2, $firstEdge['target_node_id']);
        $nextNodeResult = $nextNodeStmt->execute();
        $nextNode = $nextNodeResult->fetchArray(SQLITE3_ASSOC);
        
        if ($nextNode && $nextNode['type'] == 'approval') {
            $config = $nextNode['config'] ? json_decode($nextNode['config'], true) : [];
            $assigneeId = $config['assignee_id'] ?? 1;
            
            $taskStmt = $db->prepare("INSERT INTO workflow_tasks 
                (instance_id, node_id, node_name, assignee_id, assignee_type, status, created_by) 
                VALUES (?, ?, ?, ?, 'user', 0, ?)");
            $taskStmt->bindValue(1, $instanceId);
            $taskStmt->bindValue(2, $nextNode['node_id']);
            $taskStmt->bindValue(3, $nextNode['name']);
            $taskStmt->bindValue(4, $assigneeId);
            $taskStmt->bindValue(5, $userId);
            $taskStmt->execute();
            
            $db->exec("UPDATE workflow_instances SET current_node_id = " . $nextNode['id'] . " WHERE id = $instanceId");
            
            $taskLogStmt = $db->prepare("INSERT INTO workflow_instance_logs 
                (instance_id, node_id, action, comment, operator_id) 
                VALUES (?, ?, 'create_task', '创建审批任务: " . $nextNode['name'] . "', ?)");
            $taskLogStmt->bindValue(1, $instanceId);
            $taskLogStmt->bindValue(2, $nextNode['node_id']);
            $taskLogStmt->bindValue(3, $userId);
            $taskLogStmt->execute();
        } elseif ($nextNode && $nextNode['type'] == 'end') {
            $db->exec("UPDATE workflow_instances SET status = 1, ended_at = CURRENT_TIMESTAMP WHERE id = $instanceId");
        }
    }
    
    response(null, 0, '流程启动成功');
}

function cancelInstance($id) {
    $db = getDb();
    $db->exec("UPDATE workflow_instances SET status = 3, ended_at = CURRENT_TIMESTAMP WHERE id = $id");
    $db->exec("UPDATE workflow_tasks SET status = 4 WHERE instance_id = $id AND status = 0");
    
    $logStmt = $db->prepare("INSERT INTO workflow_instance_logs 
        (instance_id, action, comment, operator_id) 
        VALUES (?, 'cancel', '流程已取消', ?)");
    $logStmt->bindValue(1, $id);
    $logStmt->bindValue(2, 1);
    $logStmt->execute();
    
    response(null, 0, '已取消');
}
