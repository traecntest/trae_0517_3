<?php
function getUsers($params) {
    $db = getDb();
    $page = $params['page'] ?? 1;
    $pageSize = $params['pageSize'] ?? 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = [];
    $bindValues = [];
    $bindIndex = 1;
    
    if (!empty($params['keyword'])) {
        $where[] = "(name LIKE ? OR username LIKE ? OR email LIKE ?)";
        $keyword = "%{$params['keyword']}%";
        $bindValues[] = $keyword;
        $bindValues[] = $keyword;
        $bindValues[] = $keyword;
        $bindIndex += 3;
    }
    
    if (isset($params['status']) && $params['status'] !== '') {
        $where[] = "status = ?";
        $bindValues[] = $params['status'];
        $bindIndex++;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $db->prepare("SELECT * FROM users $whereClause ORDER BY id DESC LIMIT ? OFFSET ?");
    foreach ($bindValues as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }
    $stmt->bindValue($bindIndex, (int)$pageSize, SQLITE3_INTEGER);
    $stmt->bindValue($bindIndex + 1, (int)$offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        unset($row['password']);
        
        $roleStmt = $db->prepare("SELECT r.* FROM roles r 
            INNER JOIN role_user ru ON r.id = ru.role_id 
            WHERE ru.user_id = ?");
        $roleStmt->bindValue(1, $row['id']);
        $roleResult = $roleStmt->execute();
        $roles = [];
        while ($roleRow = $roleResult->fetchArray(SQLITE3_ASSOC)) {
            $roles[] = $roleRow;
        }
        $row['roles'] = $roles;
        
        $users[] = $row;
    }
    
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM users $whereClause");
    foreach ($bindValues as $i => $val) {
        $countStmt->bindValue($i + 1, $val);
    }
    $countResult = $countStmt->execute();
    $total = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
    
    response([
        'data' => $users,
        'total' => (int)$total,
        'current_page' => (int)$page,
        'per_page' => (int)$pageSize
    ]);
}

function getUserOptions() {
    $db = getDb();
    $result = $db->query("SELECT id, name, username, department FROM users WHERE status = 1 ORDER BY name");
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    response($users);
}

function getUser($id) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bindValue(1, $id);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    unset($user['password']);
    
    $roleStmt = $db->prepare("SELECT r.* FROM roles r 
        INNER JOIN role_user ru ON r.id = ru.role_id 
        WHERE ru.user_id = ?");
    $roleStmt->bindValue(1, $id);
    $roleResult = $roleStmt->execute();
    $roles = [];
    while ($row = $roleResult->fetchArray(SQLITE3_ASSOC)) {
        $roles[] = $row;
    }
    $user['roles'] = $roles;
    
    response($user);
}

function createUser($input) {
    $db = getDb();
    
    $stmt = $db->prepare("INSERT INTO users (username, name, email, phone, department, position, password, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->bindValue(1, $input['username']);
    $stmt->bindValue(2, $input['name']);
    $stmt->bindValue(3, $input['email']);
    $stmt->bindValue(4, $input['phone'] ?? '');
    $stmt->bindValue(5, $input['department'] ?? '');
    $stmt->bindValue(6, $input['position'] ?? '');
    $stmt->bindValue(7, password_hash($input['password'], PASSWORD_DEFAULT));
    $stmt->execute();
    
    $userId = $db->lastInsertRowID();
    
    if (!empty($input['roles'])) {
        foreach ($input['roles'] as $roleId) {
            $roleStmt = $db->prepare("INSERT INTO role_user (user_id, role_id) VALUES (?, ?)");
            $roleStmt->bindValue(1, $userId);
            $roleStmt->bindValue(2, $roleId);
            $roleStmt->execute();
        }
    }
    
    response(null, 0, '创建成功');
}

function updateUser($id, $input) {
    $db = getDb();
    
    $fields = [];
    $bindValues = [];
    $bindIndex = 1;
    
    if (isset($input['name'])) {
        $fields[] = 'name = ?';
        $bindValues[] = $input['name'];
        $bindIndex++;
    }
    if (isset($input['email'])) {
        $fields[] = 'email = ?';
        $bindValues[] = $input['email'];
        $bindIndex++;
    }
    if (isset($input['phone'])) {
        $fields[] = 'phone = ?';
        $bindValues[] = $input['phone'];
        $bindIndex++;
    }
    if (isset($input['department'])) {
        $fields[] = 'department = ?';
        $bindValues[] = $input['department'];
        $bindIndex++;
    }
    if (isset($input['position'])) {
        $fields[] = 'position = ?';
        $bindValues[] = $input['position'];
        $bindIndex++;
    }
    if (isset($input['status'])) {
        $fields[] = 'status = ?';
        $bindValues[] = $input['status'];
        $bindIndex++;
    }
    if (!empty($input['password'])) {
        $fields[] = 'password = ?';
        $bindValues[] = password_hash($input['password'], PASSWORD_DEFAULT);
        $bindIndex++;
    }
    
    if (!empty($fields)) {
        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        foreach ($bindValues as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue($bindIndex, $id);
        $stmt->execute();
    }
    
    if (isset($input['roles'])) {
        $db->exec("DELETE FROM role_user WHERE user_id = $id");
        foreach ($input['roles'] as $roleId) {
            $roleStmt = $db->prepare("INSERT INTO role_user (user_id, role_id) VALUES (?, ?)");
            $roleStmt->bindValue(1, $id);
            $roleStmt->bindValue(2, $roleId);
            $roleStmt->execute();
        }
    }
    
    response(null, 0, '更新成功');
}

function deleteUser($id) {
    $db = getDb();
    $db->exec("DELETE FROM role_user WHERE user_id = $id");
    $db->exec("DELETE FROM users WHERE id = $id");
    response(null, 0, '删除成功');
}

function updatePassword($userId, $input) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bindValue(1, $userId);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!password_verify($input['old_password'], $user['password'])) {
        response(null, 400, '原密码错误');
    }
    
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bindValue(1, password_hash($input['new_password'], PASSWORD_DEFAULT));
    $stmt->bindValue(2, $userId);
    $stmt->execute();
    
    response(null, 0, '密码修改成功');
}
