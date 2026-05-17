<?php
function getWorkflowOptions() {
    $db = getDb();
    $result = $db->query("SELECT id, name, code, category, type FROM workflows WHERE status = 1 ORDER BY name");
    $workflows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $workflows[] = $row;
    }
    response($workflows);
}

function getWorkflows($params) {
    $db = getDb();
    $page = $params['page'] ?? 1;
    $pageSize = $params['pageSize'] ?? 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = [];
    $bindValues = [];
    
    if (!empty($params['keyword'])) {
        $where[] = "(name LIKE ? OR code LIKE ?)";
        $keyword = "%{$params['keyword']}%";
        $bindValues[] = $keyword;
        $bindValues[] = $keyword;
    }
    if (isset($params['status']) && $params['status'] !== '') {
        $where[] = "w.status = ?";
        $bindValues[] = $params['status'];
    }
    if (isset($params['type']) && $params['type'] !== '') {
        $where[] = "type = ?";
        $bindValues[] = $params['type'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $db->prepare("SELECT w.*, u.name as creator_name FROM workflows w 
        LEFT JOIN users u ON w.created_by = u.id 
        $whereClause ORDER BY w.id DESC LIMIT ? OFFSET ?");
    foreach ($bindValues as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }
    $stmt->bindValue(count($bindValues) + 1, (int)$pageSize, SQLITE3_INTEGER);
    $stmt->bindValue(count($bindValues) + 2, (int)$offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $workflows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['creator'] = ['name' => $row['creator_name']];
        unset($row['creator_name']);
        $workflows[] = $row;
    }
    
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM workflows w $whereClause");
    foreach ($bindValues as $i => $val) {
        $countStmt->bindValue($i + 1, $val);
    }
    $countResult = $countStmt->execute();
    $total = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
    
    response([
        'data' => $workflows,
        'total' => (int)$total,
        'current_page' => (int)$page,
        'per_page' => (int)$pageSize
    ]);
}

function getWorkflow($id) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM workflows WHERE id = ?");
    $stmt->bindValue(1, $id);
    $result = $stmt->execute();
    $workflow = $result->fetchArray(SQLITE3_ASSOC);
    
    $nodeStmt = $db->prepare("SELECT * FROM workflow_nodes WHERE workflow_id = ?");
    $nodeStmt->bindValue(1, $id);
    $nodeResult = $nodeStmt->execute();
    $nodes = [];
    while ($row = $nodeResult->fetchArray(SQLITE3_ASSOC)) {
        $row['config'] = $row['config'] ? json_decode($row['config'], true) : null;
        $nodes[] = $row;
    }
    $workflow['nodes'] = $nodes;
    
    $edgeStmt = $db->prepare("SELECT * FROM workflow_edges WHERE workflow_id = ?");
    $edgeStmt->bindValue(1, $id);
    $edgeResult = $edgeStmt->execute();
    $edges = [];
    while ($row = $edgeResult->fetchArray(SQLITE3_ASSOC)) {
        $row['condition'] = $row['condition'] ? json_decode($row['condition'], true) : null;
        $edges[] = $row;
    }
    $workflow['edges'] = $edges;
    
    $versionStmt = $db->prepare("SELECT * FROM workflow_versions WHERE workflow_id = ? ORDER BY version DESC");
    $versionStmt->bindValue(1, $id);
    $versionResult = $versionStmt->execute();
    $versions = [];
    while ($row = $versionResult->fetchArray(SQLITE3_ASSOC)) {
        $row['definition'] = $row['definition'] ? json_decode($row['definition'], true) : null;
        $versions[] = $row;
    }
    $workflow['versions'] = $versions;
    
    response($workflow);
}

function getWorkflowDefinition($id) {
    $db = getDb();
    
    $nodeStmt = $db->prepare("SELECT * FROM workflow_nodes WHERE workflow_id = ?");
    $nodeStmt->bindValue(1, $id);
    $nodeResult = $nodeStmt->execute();
    $nodes = [];
    while ($row = $nodeResult->fetchArray(SQLITE3_ASSOC)) {
        $row['config'] = $row['config'] ? json_decode($row['config'], true) : null;
        $nodes[] = $row;
    }
    
    $edgeStmt = $db->prepare("SELECT * FROM workflow_edges WHERE workflow_id = ?");
    $edgeStmt->bindValue(1, $id);
    $edgeResult = $edgeStmt->execute();
    $edges = [];
    while ($row = $edgeResult->fetchArray(SQLITE3_ASSOC)) {
        $row['condition'] = $row['condition'] ? json_decode($row['condition'], true) : null;
        $edges[] = $row;
    }
    
    response(['nodes' => $nodes, 'edges' => $edges]);
}

function createWorkflow($input, $userId) {
    $db = getDb();
    
    $stmt = $db->prepare("INSERT INTO workflows (name, code, description, category, icon, color, type, status, version, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?)");
    $stmt->bindValue(1, $input['name']);
    $stmt->bindValue(2, $input['code']);
    $stmt->bindValue(3, $input['description'] ?? '');
    $stmt->bindValue(4, $input['category'] ?? 'default');
    $stmt->bindValue(5, $input['icon'] ?? 'Share');
    $stmt->bindValue(6, $input['color'] ?? '#1890ff');
    $stmt->bindValue(7, $input['type'] ?? 1);
    $stmt->bindValue(8, $userId);
    $stmt->execute();
    
    $workflowId = $db->lastInsertRowID();
    
    $db->exec("INSERT INTO workflow_nodes (workflow_id, node_id, name, type, x, y) VALUES 
        ($workflowId, 'start_1', '开始', 'start', 200, 100),
        ($workflowId, 'end_1', '结束', 'end', 200, 300)");
    
    $db->exec("INSERT INTO workflow_edges (workflow_id, edge_id, source_node_id, target_node_id) VALUES 
        ($workflowId, 'edge_1', 'start_1', 'end_1')");
    
    response(null, 0, '创建成功');
}

function updateWorkflow($id, $input) {
    $db = getDb();
    
    $fields = [];
    $bindValues = [];
    
    foreach (['name', 'code', 'description', 'category', 'icon', 'color', 'type', 'status'] as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $bindValues[] = $input[$field];
        }
    }
    
    if (!empty($fields)) {
        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = "UPDATE workflows SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        foreach ($bindValues as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($bindValues) + 1, $id);
        $stmt->execute();
    }
    
    response(null, 0, '更新成功');
}

function saveWorkflowDesign($id, $input) {
    $db = getDb();
    
    $db->exec("DELETE FROM workflow_nodes WHERE workflow_id = $id");
    $db->exec("DELETE FROM workflow_edges WHERE workflow_id = $id");
    
    foreach ($input['nodes'] as $node) {
        $stmt = $db->prepare("INSERT INTO workflow_nodes 
            (workflow_id, node_id, name, type, config, x, y, width, height) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $id);
        $stmt->bindValue(2, $node['node_id']);
        $stmt->bindValue(3, $node['name']);
        $stmt->bindValue(4, $node['type']);
        $stmt->bindValue(5, isset($node['config']) ? json_encode($node['config']) : null);
        $stmt->bindValue(6, $node['x'] ?? 0);
        $stmt->bindValue(7, $node['y'] ?? 0);
        $stmt->bindValue(8, $node['width'] ?? 160);
        $stmt->bindValue(9, $node['height'] ?? 60);
        $stmt->execute();
    }
    
    foreach ($input['edges'] as $edge) {
        $stmt = $db->prepare("INSERT INTO workflow_edges 
            (workflow_id, edge_id, source_node_id, target_node_id, label, condition) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $id);
        $stmt->bindValue(2, $edge['edge_id']);
        $stmt->bindValue(3, $edge['source_node_id']);
        $stmt->bindValue(4, $edge['target_node_id']);
        $stmt->bindValue(5, $edge['label'] ?? null);
        $stmt->bindValue(6, isset($edge['condition']) ? json_encode($edge['condition']) : null);
        $stmt->execute();
    }
    
    response(null, 0, '保存成功');
}

function publishWorkflow($id, $input) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM workflows WHERE id = ?");
    $stmt->bindValue(1, $id);
    $result = $stmt->execute();
    $workflow = $result->fetchArray(SQLITE3_ASSOC);
    
    $nodeStmt = $db->prepare("SELECT * FROM workflow_nodes WHERE workflow_id = ?");
    $nodeStmt->bindValue(1, $id);
    $nodeResult = $nodeStmt->execute();
    $nodes = [];
    while ($row = $nodeResult->fetchArray(SQLITE3_ASSOC)) {
        $nodes[] = $row;
    }
    
    $edgeStmt = $db->prepare("SELECT * FROM workflow_edges WHERE workflow_id = ?");
    $edgeStmt->bindValue(1, $id);
    $edgeResult = $edgeStmt->execute();
    $edges = [];
    while ($row = $edgeResult->fetchArray(SQLITE3_ASSOC)) {
        $edges[] = $row;
    }
    
    $hasStart = false;
    $hasEnd = false;
    foreach ($nodes as $node) {
        if ($node['type'] === 'start') $hasStart = true;
        if ($node['type'] === 'end') $hasEnd = true;
    }
    
    if (!$hasStart) response(null, 400, '流程必须有且仅有一个开始节点');
    if (!$hasEnd) response(null, 400, '流程至少需要一个结束节点');
    
    $newVersion = $workflow['version'] + 1;
    
    $stmt = $db->prepare("INSERT INTO workflow_versions 
        (workflow_id, version, definition, change_log, is_active, created_by) 
        VALUES (?, ?, ?, ?, 1, ?)");
    $stmt->bindValue(1, $id);
    $stmt->bindValue(2, $newVersion);
    $stmt->bindValue(3, json_encode(['nodes' => $nodes, 'edges' => $edges]));
    $stmt->bindValue(4, $input['change_log'] ?? null);
    $stmt->bindValue(5, 1);
    $stmt->execute();
    
    $db->exec("UPDATE workflow_versions SET is_active = 0 WHERE workflow_id = $id AND id != " . $db->lastInsertRowID());
    $db->exec("UPDATE workflows SET status = 1, version = $newVersion WHERE id = $id");
    
    response(null, 0, '发布成功');
}

function disableWorkflow($id) {
    $db = getDb();
    $db->exec("UPDATE workflows SET status = 2 WHERE id = $id");
    response(null, 0, '已停用');
}

function enableWorkflow($id) {
    $db = getDb();
    $db->exec("UPDATE workflows SET status = 1 WHERE id = $id");
    response(null, 0, '已启用');
}

function deleteWorkflow($id) {
    $db = getDb();
    
    $instanceCount = $db->querySingle("SELECT COUNT(*) FROM workflow_instances WHERE workflow_id = $id");
    if ($instanceCount > 0) {
        response(null, 400, '该流程已有运行实例，无法删除');
    }
    
    $db->exec("DELETE FROM workflow_edges WHERE workflow_id = $id");
    $db->exec("DELETE FROM workflow_nodes WHERE workflow_id = $id");
    $db->exec("DELETE FROM workflow_versions WHERE workflow_id = $id");
    $db->exec("DELETE FROM workflows WHERE id = $id");
    
    response(null, 0, '删除成功');
}
