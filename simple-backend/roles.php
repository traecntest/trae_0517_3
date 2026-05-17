<?php
function getAllRoles() {
    $db = getDb();
    $result = $db->query("SELECT * FROM roles WHERE status = 1 ORDER BY id");
    $roles = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $roles[] = $row;
    }
    response($roles);
}

function getRoles($params) {
    $db = getDb();
    $page = $params['page'] ?? 1;
    $pageSize = $params['pageSize'] ?? 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = [];
    $bindValues = [];
    
    if (!empty($params['keyword'])) {
        $where[] = "(name LIKE ? OR display_name LIKE ?)";
        $keyword = "%{$params['keyword']}%";
        $bindValues[] = $keyword;
        $bindValues[] = $keyword;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $db->prepare("SELECT * FROM roles $whereClause ORDER BY id DESC LIMIT ? OFFSET ?");
    foreach ($bindValues as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }
    $stmt->bindValue(count($bindValues) + 1, (int)$pageSize, SQLITE3_INTEGER);
    $stmt->bindValue(count($bindValues) + 2, (int)$offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $roles = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $permStmt = $db->prepare("SELECT p.* FROM permissions p 
            INNER JOIN permission_role pr ON p.id = pr.permission_id 
            WHERE pr.role_id = ?");
        $permStmt->bindValue(1, $row['id']);
        $permResult = $permStmt->execute();
        $permissions = [];
        while ($permRow = $permResult->fetchArray(SQLITE3_ASSOC)) {
            $permissions[] = $permRow;
        }
        $row['permissions'] = $permissions;
        $roles[] = $row;
    }
    
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM roles $whereClause");
    foreach ($bindValues as $i => $val) {
        $countStmt->bindValue($i + 1, $val);
    }
    $countResult = $countStmt->execute();
    $total = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
    
    response([
        'data' => $roles,
        'total' => (int)$total,
        'current_page' => (int)$page,
        'per_page' => (int)$pageSize
    ]);
}

function getRole($id) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->bindValue(1, $id);
    $result = $stmt->execute();
    $role = $result->fetchArray(SQLITE3_ASSOC);
    
    $permStmt = $db->prepare("SELECT p.* FROM permissions p 
        INNER JOIN permission_role pr ON p.id = pr.permission_id 
        WHERE pr.role_id = ?");
    $permStmt->bindValue(1, $id);
    $permResult = $permStmt->execute();
    $permissions = [];
    while ($row = $permResult->fetchArray(SQLITE3_ASSOC)) {
        $permissions[] = $row;
    }
    $role['permissions'] = $permissions;
    
    response($role);
}

function createRole($input) {
    $db = getDb();
    $stmt = $db->prepare("INSERT INTO roles (name, display_name, description, status) VALUES (?, ?, ?, 1)");
    $stmt->bindValue(1, $input['name']);
    $stmt->bindValue(2, $input['display_name']);
    $stmt->bindValue(3, $input['description'] ?? '');
    $stmt->execute();
    
    $roleId = $db->lastInsertRowID();
    
    if (!empty($input['permissions'])) {
        foreach ($input['permissions'] as $permId) {
            $permStmt = $db->prepare("INSERT INTO permission_role (role_id, permission_id) VALUES (?, ?)");
            $permStmt->bindValue(1, $roleId);
            $permStmt->bindValue(2, $permId);
            $permStmt->execute();
        }
    }
    
    response(null, 0, '创建成功');
}

function updateRole($id, $input) {
    $db = getDb();
    
    $fields = [];
    $bindValues = [];
    
    if (isset($input['name'])) {
        $fields[] = 'name = ?';
        $bindValues[] = $input['name'];
    }
    if (isset($input['display_name'])) {
        $fields[] = 'display_name = ?';
        $bindValues[] = $input['display_name'];
    }
    if (isset($input['description'])) {
        $fields[] = 'description = ?';
        $bindValues[] = $input['description'];
    }
    if (isset($input['status'])) {
        $fields[] = 'status = ?';
        $bindValues[] = $input['status'];
    }
    
    if (!empty($fields)) {
        $sql = "UPDATE roles SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        foreach ($bindValues as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($bindValues) + 1, $id);
        $stmt->execute();
    }
    
    if (isset($input['permissions'])) {
        $db->exec("DELETE FROM permission_role WHERE role_id = $id");
        foreach ($input['permissions'] as $permId) {
            $permStmt = $db->prepare("INSERT INTO permission_role (role_id, permission_id) VALUES (?, ?)");
            $permStmt->bindValue(1, $id);
            $permStmt->bindValue(2, $permId);
            $permStmt->execute();
        }
    }
    
    response(null, 0, '更新成功');
}

function deleteRole($id) {
    $db = getDb();
    $db->exec("DELETE FROM permission_role WHERE role_id = $id");
    $db->exec("DELETE FROM role_user WHERE role_id = $id");
    $db->exec("DELETE FROM roles WHERE id = $id");
    response(null, 0, '删除成功');
}

function getAllPermissions() {
    $db = getDb();
    $result = $db->query("SELECT * FROM permissions ORDER BY 'group', id");
    $permissions = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $permissions[] = $row;
    }
    response($permissions);
}

function getPermissions() {
    $db = getDb();
    $result = $db->query("SELECT * FROM permissions ORDER BY 'group', id");
    $groups = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $group = $row['group'] ?: '其他';
        if (!isset($groups[$group])) {
            $groups[$group] = [];
        }
        $groups[$group][] = $row;
    }
    response($groups);
}
