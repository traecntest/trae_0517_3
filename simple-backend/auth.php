<?php
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateJWT($payload) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $headerEncoded = base64url_encode(json_encode($header));
    
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_TTL;
    $payloadEncoded = base64url_encode(json_encode($payload));
    
    $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
    $signatureEncoded = base64url_encode($signature);
    
    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

function verifyJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    
    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
    
    $signature = base64url_decode($signatureEncoded);
    $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
    
    if (!hash_equals($expectedSignature, $signature)) return false;
    
    $payload = json_decode(base64url_decode($payloadEncoded), true);
    
    if (isset($payload['exp']) && $payload['exp'] < time()) return false;
    
    return $payload;
}

function authenticate() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = '';
    
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
    
    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    
    if (!$authHeader) {
        response(null, 401, '未登录或登录已过期');
    }
    
    if (strpos($authHeader, 'Bearer ') !== 0) {
        response(null, 401, 'Token格式错误');
    }
    
    $token = substr($authHeader, 7);
    $payload = verifyJWT($token);
    
    if (!$payload) {
        response(null, 401, 'Token无效或已过期');
    }
    
    return $payload['sub'];
}

function login($input) {
    $db = getDb();
    
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (!$username || !$password) {
        response(null, 400, '用户名和密码不能为空');
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bindValue(1, $username);
    $stmt->bindValue(2, $username);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$user) {
        response(null, 400, '用户名或密码错误');
    }
    
    if ($user['status'] != 1) {
        response(null, 400, '账户已被禁用');
    }
    
    if (!password_verify($password, $user['password'])) {
        response(null, 400, '用户名或密码错误');
    }
    
    $token = generateJWT(['sub' => $user['id'], 'name' => $user['name'], 'email' => $user['email']]);
    
    $stmt = $db->prepare("SELECT r.* FROM roles r 
        INNER JOIN role_user ru ON r.id = ru.role_id 
        WHERE ru.user_id = ?");
    $stmt->bindValue(1, $user['id']);
    $result = $stmt->execute();
    $roles = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $roles[] = $row;
    }
    
    unset($user['password']);
    $user['roles'] = $roles;
    
    response([
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => JWT_TTL,
        'user' => $user
    ]);
}

function logout() {
    response(null, 0, '退出成功');
}

function getUserInfo($userId) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bindValue(1, $userId);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    unset($user['password']);
    
    $stmt = $db->prepare("SELECT r.* FROM roles r 
        INNER JOIN role_user ru ON r.id = ru.role_id 
        WHERE ru.user_id = ?");
    $stmt->bindValue(1, $userId);
    $result = $stmt->execute();
    $roles = [];
    $roleIds = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $roles[] = $row;
        $roleIds[] = $row['id'];
    }
    
    $permissions = [];
    if (!empty($roleIds)) {
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $db->prepare("SELECT DISTINCT p.* FROM permissions p 
            INNER JOIN permission_role pr ON p.id = pr.permission_id 
            WHERE pr.role_id IN ($placeholders)");
        foreach ($roleIds as $i => $id) {
            $stmt->bindValue($i + 1, $id);
        }
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $permissions[] = $row['name'];
        }
    }
    
    response([
        'user' => $user,
        'roles' => array_column($roles, 'name'),
        'permissions' => $permissions
    ]);
}
