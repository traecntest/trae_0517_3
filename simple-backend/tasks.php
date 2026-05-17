<?php
function getTasks($params) {
    $db = getDb();
    $page = $params['page'] ?? 1;
    $pageSize = $params['pageSize'] ?? 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = [];
    $bindValues = [];
    
    if (isset($params['assignee_id'])) {
        $where[] = "assignee_id = ?";
        $bindValues[] = $params['assignee_id'];
    }
    if (isset($params['status']) && $params['status'] !== '') {
        $where[] = "wt.status = ?";
        $bindValues[] = $params['status'];
    }
    if (isset($params['instance_id'])) {
        $where[] = "instance_id = ?";
        $bindValues[] = $params['instance_id'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $db->prepare("SELECT wt.*, wi.title as instance_title, w.name as workflow_name, 
        u.name as assignee_name, u2.name as creator_name 
        FROM workflow_tasks wt 
        LEFT JOIN workflow_instances wi ON wt.instance_id = wi.id 
        LEFT JOIN workflows w ON wi.workflow_id = w.id 
        LEFT JOIN users u ON wt.assignee_id = u.id 
        LEFT JOIN users u2 ON wt.created_by = u2.id 
        $whereClause ORDER BY wt.id DESC LIMIT ? OFFSET ?");
    foreach ($bindValues as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }
    $stmt->bindValue(count($bindValues) + 1, (int)$pageSize, SQLITE3_INTEGER);
    $stmt->bindValue(count($bindValues) + 2, (int)$offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $tasks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['instance'] = [
            'title' => $row['instance_title'],
            'workflow' => ['name' => $row['workflow_name']]
        ];
        $row['assignee'] = ['name' => $row['assignee_name']];
        $row['creator'] = ['name' => $row['creator_name']];
        unset($row['instance_title'], $row['workflow_name'], $row['assignee_name'], $row['creator_name']);
        $tasks[] = $row;
    }
    
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM workflow_tasks wt $whereClause");
    foreach ($bindValues as $i => $val) {
        $countStmt->bindValue($i + 1, $val);
    }
    $countResult = $countStmt->execute();
    $total = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
    
    response([
        'data' => $tasks,
        'total' => (int)$total,
        'current_page' => (int)$page,
        'per_page' => (int)$pageSize
    ]);
}

function getPendingTasks($userId, $params) {
    $params['assignee_id'] = $userId;
    $params['status'] = 0;
    getTasks($params);
}

function getCompletedTasks($userId, $params) {
    $params['assignee_id'] = $userId;
    $db = getDb();
    $page = $params['page'] ?? 1;
    $pageSize = $params['pageSize'] ?? 20;
    $offset = ($page - 1) * $pageSize;
    
    $stmt = $db->prepare("SELECT wt.*, wi.title as instance_title, w.name as workflow_name, 
        u2.name as creator_name 
        FROM workflow_tasks wt 
        LEFT JOIN workflow_instances wi ON wt.instance_id = wi.id 
        LEFT JOIN workflows w ON wi.workflow_id = w.id 
        LEFT JOIN users u2 ON wt.created_by = u2.id 
        WHERE wt.assignee_id = ? AND wt.status IN (1, 2) 
        ORDER BY wt.completed_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $userId);
    $stmt->bindValue(2, (int)$pageSize, SQLITE3_INTEGER);
    $stmt->bindValue(3, (int)$offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $tasks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['instance'] = [
            'title' => $row['instance_title'],
            'workflow' => ['name' => $row['workflow_name']]
        ];
        $row['creator'] = ['name' => $row['creator_name']];
        unset($row['instance_title'], $row['workflow_name'], $row['creator_name']);
        $tasks[] = $row;
    }
    
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM workflow_tasks WHERE assignee_id = ? AND status IN (1, 2)");
    $countStmt->bindValue(1, $userId);
    $countResult = $countStmt->execute();
    $total = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
    
    response([
        'data' => $tasks,
        'total' => (int)$total,
        'current_page' => (int)$page,
        'per_page' => (int)$pageSize
    ]);
}

function getMyTasks($userId, $params) {
    $params['assignee_id'] = $userId;
    getTasks($params);
}

function getTask($id) {
    $db = getDb();
    $stmt = $db->prepare("SELECT wt.*, wi.title as instance_title, w.name as workflow_name, 
        u.name as assignee_name, u2.name as creator_name 
        FROM workflow_tasks wt 
        LEFT JOIN workflow_instances wi ON wt.instance_id = wi.id 
        LEFT JOIN workflows w ON wi.workflow_id = w.id 
        LEFT JOIN users u ON wt.assignee_id = u.id 
        LEFT JOIN users u2 ON wt.created_by = u2.id 
        WHERE wt.id = ?");
    $stmt->bindValue(1, $id);
    $result = $stmt->execute();
    $task = $result->fetchArray(SQLITE3_ASSOC);
    
    $task['instance'] = [
        'title' => $task['instance_title'],
        'workflow' => ['name' => $task['workflow_name']]
    ];
    $task['assignee'] = ['name' => $task['assignee_name']];
    $task['creator'] = ['name' => $task['creator_name']];
    unset($task['instance_title'], $task['workflow_name'], $task['assignee_name'], $task['creator_name']);
    
    response($task);
}

function approveTask($taskId, $input, $userId) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM workflow_tasks WHERE id = ?");
    $stmt->bindValue(1, $taskId);
    $result = $stmt->execute();
    $task = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$task) {
        response(null, 404, '任务不存在');
    }
    
    if ($task['assignee_id'] != $userId) {
        response(null, 403, '无权限处理此任务');
    }
    
    if ($task['status'] != 0) {
        response(null, 400, '任务已处理');
    }
    
    $db->exec("UPDATE workflow_tasks SET status = 1, comment = '" . ($input['comment'] ?? '') . "', completed_at = CURRENT_TIMESTAMP WHERE id = $taskId");
    
    $instanceId = $task['instance_id'];
    
    $logStmt = $db->prepare("INSERT INTO workflow_instance_logs 
        (instance_id, node_id, action, comment, operator_id) 
        VALUES (?, ?, 'approve', ?, ?)");
    $logStmt->bindValue(1, $instanceId);
    $logStmt->bindValue(2, $task['node_id']);
    $logStmt->bindValue(3, $input['comment'] ?? '');
    $logStmt->bindValue(4, $userId);
    $logStmt->execute();
    
    $instanceStmt = $db->prepare("SELECT * FROM workflow_instances WHERE id = ?");
    $instanceStmt->bindValue(1, $instanceId);
    $instanceResult = $instanceStmt->execute();
    $instance = $instanceResult->fetchArray(SQLITE3_ASSOC);
    
    $edgeStmt = $db->prepare("SELECT * FROM workflow_edges WHERE workflow_id = ? AND source_node_id = ?");
    $edgeStmt->bindValue(1, $instance['workflow_id']);
    $edgeStmt->bindValue(2, $task['node_id']);
    $edgeResult = $edgeStmt->execute();
    $nextEdge = $edgeResult->fetchArray(SQLITE3_ASSOC);
    
    if ($nextEdge) {
        $nextNodeStmt = $db->prepare("SELECT * FROM workflow_nodes WHERE workflow_id = ? AND node_id = ?");
        $nextNodeStmt->bindValue(1, $instance['workflow_id']);
        $nextNodeStmt->bindValue(2, $nextEdge['target_node_id']);
        $nextNodeResult = $nextNodeStmt->execute();
        $nextNode = $nextNodeResult->fetchArray(SQLITE3_ASSOC);
        
        if ($nextNode) {
            if ($nextNode['type'] == 'end') {
                $db->exec("UPDATE workflow_instances SET status = 1, ended_at = CURRENT_TIMESTAMP, current_node_id = " . $nextNode['id'] . " WHERE id = $instanceId");
                
                $completeLogStmt = $db->prepare("INSERT INTO workflow_instance_logs 
                    (instance_id, node_id, action, comment, operator_id) 
                    VALUES (?, ?, 'complete', '流程完成', ?)");
                $completeLogStmt->bindValue(1, $instanceId);
                $completeLogStmt->bindValue(2, $nextNode['node_id']);
                $completeLogStmt->bindValue(3, $userId);
                $completeLogStmt->execute();
            } elseif ($nextNode['type'] == 'approval') {
                $config = $nextNode['config'] ? json_decode($nextNode['config'], true) : [];
                $assigneeId = $config['assignee_id'] ?? 1;
                
                $nextTaskStmt = $db->prepare("INSERT INTO workflow_tasks 
                    (instance_id, node_id, node_name, assignee_id, assignee_type, status, created_by) 
                    VALUES (?, ?, ?, ?, 'user', 0, ?)");
                $nextTaskStmt->bindValue(1, $instanceId);
                $nextTaskStmt->bindValue(2, $nextNode['node_id']);
                $nextTaskStmt->bindValue(3, $nextNode['name']);
                $nextTaskStmt->bindValue(4, $assigneeId);
                $nextTaskStmt->bindValue(5, $userId);
                $nextTaskStmt->execute();
                
                $db->exec("UPDATE workflow_instances SET current_node_id = " . $nextNode['id'] . " WHERE id = $instanceId");
                
                $taskLogStmt = $db->prepare("INSERT INTO workflow_instance_logs 
                    (instance_id, node_id, action, comment, operator_id) 
                    VALUES (?, ?, 'create_task', '创建审批任务: " . $nextNode['name'] . "', ?)");
                $taskLogStmt->bindValue(1, $instanceId);
                $taskLogStmt->bindValue(2, $nextNode['node_id']);
                $taskLogStmt->bindValue(3, $userId);
                $taskLogStmt->execute();
            } else {
                $db->exec("UPDATE workflow_instances SET current_node_id = " . $nextNode['id'] . " WHERE id = $instanceId");
            }
        }
    }
    
    response(null, 0, '审批通过');
}

function rejectTask($taskId, $input, $userId) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM workflow_tasks WHERE id = ?");
    $stmt->bindValue(1, $taskId);
    $result = $stmt->execute();
    $task = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$task) {
        response(null, 404, '任务不存在');
    }
    
    if ($task['assignee_id'] != $userId) {
        response(null, 403, '无权限处理此任务');
    }
    
    if ($task['status'] != 0) {
        response(null, 400, '任务已处理');
    }
    
    $db->exec("UPDATE workflow_tasks SET status = 2, comment = '" . ($input['comment'] ?? '') . "', completed_at = CURRENT_TIMESTAMP WHERE id = $taskId");
    
    $instanceId = $task['instance_id'];
    
    $logStmt = $db->prepare("INSERT INTO workflow_instance_logs 
        (instance_id, node_id, action, comment, operator_id) 
        VALUES (?, ?, 'reject', ?, ?)");
    $logStmt->bindValue(1, $instanceId);
    $logStmt->bindValue(2, $task['node_id']);
    $logStmt->bindValue(3, $input['comment'] ?? '');
    $logStmt->bindValue(4, $userId);
    $logStmt->execute();
    
    $db->exec("UPDATE workflow_instances SET status = 2, ended_at = CURRENT_TIMESTAMP WHERE id = $instanceId");
    
    response(null, 0, '已驳回');
}
