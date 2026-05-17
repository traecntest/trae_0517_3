import { createRouter, createWebHashHistory } from 'vue-router'
import { useUserStore } from '../store/user'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/Login.vue'),
    meta: { title: '登录' }
  },
  {
    path: '/',
    component: () => import('../views/Layout.vue'),
    redirect: '/dashboard',
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('../views/Dashboard.vue'),
        meta: { title: '首页', icon: 'HomeFilled' }
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('../views/system/Users.vue'),
        meta: { title: '用户管理', icon: 'User', group: '系统管理' }
      },
      {
        path: 'roles',
        name: 'Roles',
        component: () => import('../views/system/Roles.vue'),
        meta: { title: '角色管理', icon: 'UserFilled', group: '系统管理' }
      },
      {
        path: 'permissions',
        name: 'Permissions',
        component: () => import('../views/system/Permissions.vue'),
        meta: { title: '权限管理', icon: 'Lock', group: '系统管理' }
      },
      {
        path: 'workflows',
        name: 'Workflows',
        component: () => import('../views/workflow/Workflows.vue'),
        meta: { title: '流程管理', icon: 'Share', group: '流程管理' }
      },
      {
        path: 'workflow/design/:id',
        name: 'WorkflowDesign',
        component: () => import('../views/workflow/WorkflowDesign.vue'),
        meta: { title: '流程设计', icon: 'Edit', hidden: true }
      },
      {
        path: 'instances',
        name: 'Instances',
        component: () => import('../views/workflow/Instances.vue'),
        meta: { title: '流程实例', icon: 'Document', group: '流程管理' }
      },
      {
        path: 'instance/:id',
        name: 'InstanceDetail',
        component: () => import('../views/workflow/InstanceDetail.vue'),
        meta: { title: '实例详情', icon: 'Document', hidden: true }
      },
      {
        path: 'tasks/pending',
        name: 'PendingTasks',
        component: () => import('../views/workflow/PendingTasks.vue'),
        meta: { title: '待办任务', icon: 'Clock', group: '任务中心' }
      },
      {
        path: 'tasks/completed',
        name: 'CompletedTasks',
        component: () => import('../views/workflow/CompletedTasks.vue'),
        meta: { title: '已办任务', icon: 'Check', group: '任务中心' }
      },
      {
        path: 'tasks/started',
        name: 'StartedTasks',
        component: () => import('../views/workflow/StartedTasks.vue'),
        meta: { title: '我发起的', icon: 'Promotion', group: '任务中心' }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const userStore = useUserStore()
  const token = localStorage.getItem('token')

  if (to.meta.requiresAuth && !token) {
    next('/login')
  } else if (to.path === '/login' && token) {
    next('/')
  } else {
    next()
  }
})

export default router
