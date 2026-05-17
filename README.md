# 企业级低代码流程编排平台

基于 Laravel + Vue 3 + Element Plus 构建的企业级低代码流程编排平台系统。

## 功能特性

### 🎯 核心模块

**1. 用户管理模块**
- 用户登录/登出（JWT认证）
- 用户CRUD管理
- 用户角色分配
- 密码修改
- 用户状态管理

**2. 角色权限管理**
- 角色CRUD管理
- 权限CRUD管理
- 角色权限分配
- 权限分组管理

**3. 流程管理模块**
- 流程定义CRUD
- 流程版本管理
- 流程发布/停用
- 流程分类管理

**4. 流程编排设计器**
- 可视化拖拽式流程设计
- 丰富的节点类型：开始、结束、审批、条件分支、并行网关、自动节点、延时节点
- 节点连线和条件设置
- 节点属性配置（审批人、条件表达式、自动化动作等）
- 流程设计实时保存

**5. 流程实例管理**
- 启动流程实例
- 流程实例列表查询
- 流程图实时展示（高亮当前节点）
- 流程审批记录
- 流程取消功能

**6. 任务中心**
- 待办任务列表
- 已办任务列表
- 我发起的流程
- 任务审批（同意/驳回）
- 任务转交

## 技术架构

### 后端
- **语言**: PHP 8.2
- **框架**: Laravel 11 架构思想（简化版实现）
- **数据库**: SQLite（可切换MySQL）
- **认证**: JWT (JSON Web Token)
- **权限**: RBAC 权限模型

### 前端
- **框架**: Vue 3 (Composition API)
- **UI组件**: Element Plus
- **路由**: Vue Router 4
- **状态管理**: Pinia
- **HTTP客户端**: Axios
- **流程图**: SVG 自定义实现
- **图表**: ECharts

## 快速开始

### 环境要求
- PHP >= 8.2
- Node.js >= 18
- NPM >= 9
- SQLite 扩展

### 启动服务

```bash
# 方式一：直接启动
cd /mnt/github/trae_0517_3

# 启动后端服务
cd simple-backend
php -S 0.0.0.0:8000

# 启动前端服务（新开终端）
cd ../frontend
npm run dev

# 方式二：使用脚本
./start.sh    # 启动服务
./stop.sh     # 停止服务
```

### 访问地址
- 前端: http://localhost:3000
- 后端API: http://localhost:8000/api

### 默认账号

| 用户名 | 密码 | 角色 | 说明 |
|--------|------|------|------|
| admin | admin123 | 超级管理员 | 拥有所有权限 |
| zhangsan | 123456 | 普通用户 | 市场经理 |
| lisi | 123456 | 普通用户 | 财务主管 |
| wangwu | 123456 | 普通用户 | 总经理 |

## 项目结构

```
lowcode-workflow-platform/
├── backend/                    # Laravel 后端源码（完整架构）
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/    # API控制器
│   │   │   ├── Middleware/         # 中间件
│   │   │   └── Kernel.php          # HTTP内核
│   │   ├── Models/                 # 数据模型
│   │   ├── Services/               # 业务服务层
│   │   └── Exceptions/             # 异常处理
│   ├── config/                     # 配置文件
│   ├── database/
│   │   ├── migrations/             # 数据库迁移
│   │   └── seeders/                # 数据填充
│   ├── routes/                     # 路由定义
│   └── public/                     # 入口文件
├── simple-backend/             # 简化版后端（当前运行）
│   ├── index.php                # API入口
│   ├── config.php               # 配置
│   ├── db.php                   # 数据库初始化
│   ├── auth.php                 # 认证模块
│   ├── users.php                # 用户管理
│   ├── roles.php                # 角色权限管理
│   ├── workflows.php            # 流程管理
│   ├── instances.php            # 流程实例
│   └── tasks.php                # 任务管理
├── frontend/                   # Vue 3 前端
│   ├── src/
│   │   ├── api/                 # API接口定义
│   │   ├── views/               # 页面组件
│   │   ├── router/              # 路由配置
│   │   ├── store/               # 状态管理
│   │   ├── utils/               # 工具函数
│   │   ├── components/          # 公共组件
│   │   └── main.js              # 入口文件
│   ├── index.html
│   ├── vite.config.js
│   └── package.json
├── docker-compose.yml           # Docker 配置
├── start.sh                     # 启动脚本
└── stop.sh                      # 停止脚本
```

## API 接口列表

### 认证接口
| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/auth/login | 用户登录 |
| POST | /api/auth/logout | 用户登出 |
| GET | /api/auth/userinfo | 获取用户信息 |

### 用户管理
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/users | 用户列表 |
| GET | /api/users/options | 用户选项 |
| GET | /api/users/{id} | 用户详情 |
| POST | /api/users | 创建用户 |
| PUT | /api/users/{id} | 更新用户 |
| DELETE | /api/users/{id} | 删除用户 |
| PUT | /api/users/password | 修改密码 |

### 角色权限
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/roles | 角色列表 |
| GET | /api/roles/all | 所有角色 |
| POST | /api/roles | 创建角色 |
| PUT | /api/roles/{id} | 更新角色 |
| DELETE | /api/roles/{id} | 删除角色 |
| GET | /api/permissions | 权限列表 |
| GET | /api/permissions/all | 所有权限 |

### 流程管理
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/workflows | 流程列表 |
| GET | /api/workflows/options | 流程选项 |
| GET | /api/workflows/{id} | 流程详情 |
| POST | /api/workflows | 创建流程 |
| PUT | /api/workflows/{id} | 更新流程 |
| DELETE | /api/workflows/{id} | 删除流程 |
| POST | /api/workflows/{id}/design | 保存流程设计 |
| POST | /api/workflows/{id}/publish | 发布流程 |
| POST | /api/workflows/{id}/disable | 停用流程 |
| POST | /api/workflows/{id}/enable | 启用流程 |
| GET | /api/workflows/{id}/definition | 获取流程定义 |

### 流程实例
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/workflow-instances | 实例列表 |
| GET | /api/workflow-instances/my | 我发起的 |
| GET | /api/workflow-instances/{id} | 实例详情 |
| POST | /api/workflow-instances | 启动流程 |
| POST | /api/workflow-instances/{id}/cancel | 取消流程 |
| GET | /api/workflow-instances/{id}/flowchart | 获取流程图 |

### 任务管理
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/workflow-tasks/pending | 待办任务 |
| GET | /api/workflow-tasks/completed | 已办任务 |
| GET | /api/workflow-tasks/my | 我的任务 |
| POST | /api/workflow-tasks/{id}/approve | 审批同意 |
| POST | /api/workflow-tasks/{id}/reject | 审批驳回 |

## 核心功能说明

### 流程设计器使用
1. 进入"流程管理" → 点击"新建流程"
2. 填写流程基本信息后保存
3. 点击"设计"按钮进入设计器
4. 从左侧节点面板拖拽节点到画布
5. 点击节点右侧连接点拖拽到下一节点左侧连接点建立连线
6. 点击节点在右侧面板配置属性
7. 保存设计后点击"发布"使流程生效

### 流程审批
1. 发布后的流程可在"流程管理"中点击"启动"
2. 填写流程标题和描述后提交
3. 审批人可在"待办任务"中看到待处理任务
4. 点击进入详情可同意或驳回
5. 可在流程图中实时查看审批进度

## 数据库表结构

- **users**: 用户表
- **roles**: 角色表
- **permissions**: 权限表
- **role_user**: 用户角色关联表
- **permission_role**: 角色权限关联表
- **workflows**: 流程定义表
- **workflow_versions**: 流程版本表
- **workflow_nodes**: 流程节点表
- **workflow_edges**: 流程连线表
- **workflow_instances**: 流程实例表
- **workflow_tasks**: 审批任务表
- **workflow_instance_logs**: 流程操作日志表

## 特色功能

✅ **可视化设计**: 拖拽式流程设计，所见即所得  
✅ **多节点支持**: 审批、条件、并行、自动等多种节点类型  
✅ **版本管理**: 流程发布版本管理，支持回滚  
✅ **RBAC权限**: 完善的角色权限控制体系  
✅ **实时追踪**: 流程图实时高亮显示当前节点  
✅ **操作日志**: 完整的流程操作审计日志  
✅ **响应式设计**: 适配不同屏幕尺寸  
✅ **企业级特性**: 多部门、多职位、审批转交等

## 扩展开发

### 添加新的节点类型
1. 在 `simple-backend/workflows.php` 中添加节点类型处理逻辑
2. 在 `frontend/src/views/workflow/WorkflowDesign.vue` 的 `nodeTypes` 数组中添加节点配置
3. 添加对应的图标和样式

### 自定义流程动作
1. 在 `simple-backend/instances.php` 的流程引擎中添加动作处理
2. 在节点配置中添加对应配置项

## License

MIT
