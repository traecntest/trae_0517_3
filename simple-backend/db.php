<?php
function getDb() {
    $db = new SQLite3(DB_PATH);
    $db->enableExceptions(true);
    return $db;
}

function initDb() {
    if (file_exists(DB_PATH)) {
        return;
    }

    $db = getDb();

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE,
        name VARCHAR(100),
        email VARCHAR(100) UNIQUE,
        phone VARCHAR(20),
        department VARCHAR(100),
        position VARCHAR(100),
        avatar VARCHAR(255),
        email_verified_at DATETIME,
        password VARCHAR(255),
        remember_token VARCHAR(100),
        status TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(50) UNIQUE,
        display_name VARCHAR(100),
        description VARCHAR(255),
        status TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) UNIQUE,
        display_name VARCHAR(100),
        description VARCHAR(255),
        'group' VARCHAR(50) DEFAULT 'default',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS role_user (
        role_id INTEGER,
        user_id INTEGER,
        PRIMARY KEY (role_id, user_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS permission_role (
        permission_id INTEGER,
        role_id INTEGER,
        PRIMARY KEY (permission_id, role_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS workflows (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100),
        code VARCHAR(50) UNIQUE,
        description TEXT,
        category VARCHAR(50) DEFAULT 'default',
        icon VARCHAR(50),
        color VARCHAR(20) DEFAULT '#1890ff',
        type TINYINT DEFAULT 1,
        status TINYINT DEFAULT 0,
        version INTEGER DEFAULT 1,
        created_by INTEGER,
        updated_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS workflow_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        workflow_id INTEGER,
        version INTEGER,
        definition TEXT,
        change_log TEXT,
        is_active TINYINT DEFAULT 0,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS workflow_nodes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        workflow_id INTEGER,
        node_id VARCHAR(50),
        name VARCHAR(100),
        type VARCHAR(50),
        config TEXT,
        x INTEGER DEFAULT 0,
        y INTEGER DEFAULT 0,
        width INTEGER DEFAULT 160,
        height INTEGER DEFAULT 60,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS workflow_edges (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        workflow_id INTEGER,
        edge_id VARCHAR(50),
        source_node_id VARCHAR(50),
        target_node_id VARCHAR(50),
        label VARCHAR(100),
        condition TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS workflow_instances (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        workflow_id INTEGER,
        workflow_version INTEGER,
        business_type VARCHAR(100),
        business_id VARCHAR(50),
        title VARCHAR(200),
        description TEXT,
        status TINYINT DEFAULT 0,
        started_by INTEGER,
        started_at DATETIME,
        ended_at DATETIME,
        current_node_id INTEGER,
        variables TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS workflow_tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        instance_id INTEGER,
        node_id VARCHAR(50),
        node_name VARCHAR(100),
        assignee_id INTEGER,
        assignee_type VARCHAR(50) DEFAULT 'user',
        status TINYINT DEFAULT 0,
        comment TEXT,
        created_by INTEGER,
        claimed_at DATETIME,
        completed_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS workflow_instance_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        instance_id INTEGER,
        node_id VARCHAR(50),
        action VARCHAR(50),
        comment TEXT,
        operator_id INTEGER,
        extra TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $userPass = password_hash('123456', PASSWORD_DEFAULT);

    $db->exec("INSERT INTO users (username, name, email, phone, department, position, password, status) VALUES 
        ('admin', '系统管理员', 'admin@example.com', '13800138000', '信息技术部', '系统管理员', '$adminPass', 1),
        ('zhangsan', '张三', 'zhangsan@example.com', '13800138001', '市场部', '市场经理', '$userPass', 1),
        ('lisi', '李四', 'lisi@example.com', '13800138002', '财务部', '财务主管', '$userPass', 1),
        ('wangwu', '王五', 'wangwu@example.com', '13800138003', '总经理办公室', '总经理', '$userPass', 1)");

    $db->exec("INSERT INTO roles (name, display_name, description, status) VALUES 
        ('admin', '超级管理员', '拥有所有权限', 1),
        ('user', '普通用户', '基础用户权限', 1)");

    $perms = [
        ['user:view', '查看用户', '用户管理'],
        ['user:create', '创建用户', '用户管理'],
        ['user:update', '编辑用户', '用户管理'],
        ['user:delete', '删除用户', '用户管理'],
        ['role:view', '查看角色', '角色管理'],
        ['role:create', '创建角色', '角色管理'],
        ['role:update', '编辑角色', '角色管理'],
        ['role:delete', '删除角色', '角色管理'],
        ['workflow:view', '查看流程', '流程管理'],
        ['workflow:create', '创建流程', '流程管理'],
        ['workflow:update', '编辑流程', '流程管理'],
        ['workflow:delete', '删除流程', '流程管理'],
        ['workflow:design', '流程设计', '流程管理'],
        ['workflow:publish', '发布流程', '流程管理'],
        ['instance:view', '查看实例', '流程实例'],
        ['instance:create', '发起流程', '流程实例'],
        ['instance:cancel', '取消流程', '流程实例'],
        ['task:view', '查看任务', '任务管理'],
        ['task:approve', '审批任务', '任务管理'],
        ['task:reject', '驳回任务', '任务管理'],
        ['task:transfer', '转交任务', '任务管理'],
    ];

    foreach ($perms as $perm) {
        $stmt = $db->prepare("INSERT INTO permissions (name, display_name, 'group') VALUES (?, ?, ?)");
        $stmt->bindValue(1, $perm[0]);
        $stmt->bindValue(2, $perm[1]);
        $stmt->bindValue(3, $perm[2]);
        $stmt->execute();
    }

    $db->exec("INSERT INTO role_user (role_id, user_id) VALUES (1, 1), (2, 2), (2, 3), (2, 4)");

    for ($i = 1; $i <= 21; $i++) {
        $db->exec("INSERT INTO permission_role (permission_id, role_id) VALUES ($i, 1)");
    }

    for ($i = 16; $i <= 21; $i++) {
        $db->exec("INSERT INTO permission_role (permission_id, role_id) VALUES ($i, 2)");
    }

    $db->exec("INSERT INTO workflows (name, code, description, category, icon, color, type, status, version, created_by) VALUES 
        ('请假审批', 'leave_approval', '员工请假审批流程', 'OA审批', 'Document', '#52c41a', 1, 1, 1, 1),
        ('报销审批', 'expense_approval', '费用报销审批流程', 'OA审批', 'Money', '#faad14', 1, 1, 1, 1),
        ('采购申请', 'purchase_request', '采购申请审批流程', '业务流程', 'ShoppingCart', '#1890ff', 2, 1, 1, 1)");

    $db->exec("INSERT INTO workflow_nodes (workflow_id, node_id, name, type, x, y) VALUES 
        (1, 'start_1', '开始', 'start', 200, 50),
        (1, 'node_1', '部门经理审批', 'approval', 200, 150),
        (1, 'node_2', '人事审批', 'approval', 200, 250),
        (1, 'end_1', '结束', 'end', 200, 350),
        (2, 'start_1', '开始', 'start', 200, 50),
        (2, 'node_1', '部门经理审批', 'approval', 200, 150),
        (2, 'node_2', '财务审批', 'approval', 200, 250),
        (2, 'node_3', '总经理审批', 'approval', 200, 350),
        (2, 'end_1', '结束', 'end', 200, 450),
        (3, 'start_1', '开始', 'start', 200, 50),
        (3, 'node_1', '部门经理审批', 'approval', 200, 150),
        (3, 'end_1', '结束', 'end', 200, 250)");

    $db->exec("INSERT INTO workflow_edges (workflow_id, edge_id, source_node_id, target_node_id) VALUES 
        (1, 'edge_1', 'start_1', 'node_1'),
        (1, 'edge_2', 'node_1', 'node_2'),
        (1, 'edge_3', 'node_2', 'end_1'),
        (2, 'edge_1', 'start_1', 'node_1'),
        (2, 'edge_2', 'node_1', 'node_2'),
        (2, 'edge_3', 'node_2', 'node_3'),
        (2, 'edge_4', 'node_3', 'end_1'),
        (3, 'edge_1', 'start_1', 'node_1'),
        (3, 'edge_2', 'node_1', 'end_1')");

    $db->close();
}

initDb();
