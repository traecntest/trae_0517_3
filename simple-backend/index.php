<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';
require_once 'users.php';
require_once 'roles.php';
require_once 'workflows.php';
require_once 'instances.php';
require_once 'tasks.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

$input = json_decode(file_get_contents('php://input'), true) ?? [];

function response($data, $code = 0, $message = '') {
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $authRoutes = ['/auth/login'];
    $needsAuth = !in_array($uri, $authRoutes);
    
    if ($needsAuth) {
        $userId = authenticate();
    }

    switch (true) {
        case $uri === '/auth/login' && $method === 'POST':
            login($input);
            break;

        case $uri === '/auth/logout' && $method === 'POST':
            logout();
            break;

        case $uri === '/auth/userinfo' && $method === 'GET':
            getUserInfo($userId);
            break;

        case preg_match('#^/users/options$#', $uri) && $method === 'GET':
            getUserOptions();
            break;

        case preg_match('#^/users/password$#', $uri) && $method === 'PUT':
            updatePassword($userId, $input);
            break;

        case preg_match('#^/users/(\d+)$#', $uri, $matches) && $method === 'GET':
            getUser($matches[1]);
            break;

        case preg_match('#^/users/(\d+)$#', $uri, $matches) && $method === 'PUT':
            updateUser($matches[1], $input);
            break;

        case preg_match('#^/users/(\d+)$#', $uri, $matches) && $method === 'DELETE':
            deleteUser($matches[1]);
            break;

        case preg_match('#^/users$#', $uri) && $method === 'GET':
            getUsers($_GET);
            break;

        case preg_match('#^/users$#', $uri) && $method === 'POST':
            createUser($input);
            break;

        case preg_match('#^/roles/all$#', $uri) && $method === 'GET':
            getAllRoles();
            break;

        case preg_match('#^/roles/(\d+)$#', $uri, $matches) && $method === 'GET':
            getRole($matches[1]);
            break;

        case preg_match('#^/roles/(\d+)$#', $uri, $matches) && $method === 'PUT':
            updateRole($matches[1], $input);
            break;

        case preg_match('#^/roles/(\d+)$#', $uri, $matches) && $method === 'DELETE':
            deleteRole($matches[1]);
            break;

        case preg_match('#^/roles$#', $uri) && $method === 'GET':
            getRoles($_GET);
            break;

        case preg_match('#^/roles$#', $uri) && $method === 'POST':
            createRole($input);
            break;

        case preg_match('#^/permissions/all$#', $uri) && $method === 'GET':
            getAllPermissions();
            break;

        case preg_match('#^/permissions$#', $uri) && $method === 'GET':
            getPermissions();
            break;

        case preg_match('#^/workflows/options$#', $uri) && $method === 'GET':
            getWorkflowOptions();
            break;

        case preg_match('#^/workflows/(\d+)/definition$#', $uri, $matches) && $method === 'GET':
            getWorkflowDefinition($matches[1]);
            break;

        case preg_match('#^/workflows/(\d+)/design$#', $uri, $matches) && $method === 'POST':
            saveWorkflowDesign($matches[1], $input);
            break;

        case preg_match('#^/workflows/(\d+)/publish$#', $uri, $matches) && $method === 'POST':
            publishWorkflow($matches[1], $input);
            break;

        case preg_match('#^/workflows/(\d+)/disable$#', $uri, $matches) && $method === 'POST':
            disableWorkflow($matches[1]);
            break;

        case preg_match('#^/workflows/(\d+)/enable$#', $uri, $matches) && $method === 'POST':
            enableWorkflow($matches[1]);
            break;

        case preg_match('#^/workflows/(\d+)$#', $uri, $matches) && $method === 'GET':
            getWorkflow($matches[1]);
            break;

        case preg_match('#^/workflows/(\d+)$#', $uri, $matches) && $method === 'PUT':
            updateWorkflow($matches[1], $input);
            break;

        case preg_match('#^/workflows/(\d+)$#', $uri, $matches) && $method === 'DELETE':
            deleteWorkflow($matches[1]);
            break;

        case preg_match('#^/workflows$#', $uri) && $method === 'GET':
            getWorkflows($_GET);
            break;

        case preg_match('#^/workflows$#', $uri) && $method === 'POST':
            createWorkflow($input, $userId);
            break;

        case preg_match('#^/workflow-instances/my$#', $uri) && $method === 'GET':
            getMyInstances($userId, $_GET);
            break;

        case preg_match('#^/workflow-instances/(\d+)/flowchart$#', $uri, $matches) && $method === 'GET':
            getInstanceFlowChart($matches[1]);
            break;

        case preg_match('#^/workflow-instances/(\d+)/cancel$#', $uri, $matches) && $method === 'POST':
            cancelInstance($matches[1]);
            break;

        case preg_match('#^/workflow-instances/(\d+)$#', $uri, $matches) && $method === 'GET':
            getInstance($matches[1]);
            break;

        case preg_match('#^/workflow-instances$#', $uri) && $method === 'GET':
            getInstances($_GET);
            break;

        case preg_match('#^/workflow-instances$#', $uri) && $method === 'POST':
            createInstance($input, $userId);
            break;

        case preg_match('#^/workflow-tasks/pending$#', $uri) && $method === 'GET':
            getPendingTasks($userId, $_GET);
            break;

        case preg_match('#^/workflow-tasks/completed$#', $uri) && $method === 'GET':
            getCompletedTasks($userId, $_GET);
            break;

        case preg_match('#^/workflow-tasks/my$#', $uri) && $method === 'GET':
            getMyTasks($userId, $_GET);
            break;

        case preg_match('#^/workflow-tasks/(\d+)/approve$#', $uri, $matches) && $method === 'POST':
            approveTask($matches[1], $input, $userId);
            break;

        case preg_match('#^/workflow-tasks/(\d+)/reject$#', $uri, $matches) && $method === 'POST':
            rejectTask($matches[1], $input, $userId);
            break;

        case preg_match('#^/workflow-tasks/(\d+)$#', $uri, $matches) && $method === 'GET':
            getTask($matches[1]);
            break;

        case preg_match('#^/workflow-tasks$#', $uri) && $method === 'GET':
            getTasks($_GET);
            break;

        default:
            response(null, 404, 'Not Found');
    }
} catch (Exception $e) {
    response(null, 500, $e->getMessage());
}
