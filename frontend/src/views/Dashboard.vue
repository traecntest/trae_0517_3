<template>
  <div class="dashboard">
    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-info">
              <div class="stat-value">{{ stats.workflows }}</div>
              <div class="stat-label">流程总数</div>
            </div>
            <div class="stat-icon workflow">
              <el-icon :size="32"><Share /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-info">
              <div class="stat-value">{{ stats.runningInstances }}</div>
              <div class="stat-label">运行中实例</div>
            </div>
            <div class="stat-icon instance">
              <el-icon :size="32"><Document /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-info">
              <div class="stat-value">{{ stats.pendingTasks }}</div>
              <div class="stat-label">待办任务</div>
            </div>
            <div class="stat-icon task">
              <el-icon :size="32"><Clock /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-info">
              <div class="stat-value">{{ stats.users }}</div>
              <div class="stat-label">系统用户</div>
            </div>
            <div class="stat-icon user">
              <el-icon :size="32"><User /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-card class="chart-card">
          <template #header>
            <span>流程分类统计</span>
          </template>
          <div ref="categoryChartRef" class="chart"></div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card class="chart-card">
          <template #header>
            <span>实例状态分布</span>
          </template>
          <div ref="statusChartRef" class="chart"></div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="mt-20">
      <el-col :span="12">
        <el-card class="list-card">
          <template #header>
            <span>最近待办</span>
            <el-button type="primary" text @click="$router.push('/tasks/pending')">
              查看全部
            </el-button>
          </template>
          <el-table :data="recentTasks" style="width: 100%">
            <el-table-column prop="instance.title" label="流程标题" />
            <el-table-column prop="node_name" label="节点名称" />
            <el-table-column prop="created_at" label="创建时间" width="180">
              <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button type="primary" text @click="handleTask(row)">处理</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card class="list-card">
          <template #header>
            <span>我发起的</span>
            <el-button type="primary" text @click="$router.push('/tasks/started')">
              查看全部
            </el-button>
          </template>
          <el-table :data="recentInstances" style="width: 100%">
            <el-table-column prop="title" label="标题" />
            <el-table-column prop="workflow.name" label="流程" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="getStatusType(row.status)">{{ getStatusText(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="创建时间" width="180">
              <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import * as echarts from 'echarts'
import dayjs from 'dayjs'
import { Share, Document, Clock, User } from '@element-plus/icons-vue'
import { getWorkflowList } from '../api/workflow'
import { getMyInstances } from '../api/instance'
import { getPendingTasks } from '../api/task'
import { getUserList } from '../api/user'

const router = useRouter()
const categoryChartRef = ref()
const statusChartRef = ref()
let categoryChart = null
let statusChart = null

const stats = reactive({
  workflows: 0,
  runningInstances: 0,
  pendingTasks: 0,
  users: 0
})

const recentTasks = ref([])
const recentInstances = ref([])

const formatDate = (date) => dayjs(date).format('YYYY-MM-DD HH:mm')

const getStatusType = (status) => {
  const types = ['info', 'success', 'danger', 'warning', 'info']
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = ['运行中', '已完成', '已驳回', '已取消', '已撤销']
  return texts[status] || '未知'
}

const handleTask = (row) => {
  router.push('/instance/' + row.instance_id)
}

const initCharts = () => {
  categoryChart = echarts.init(categoryChartRef.value)
  statusChart = echarts.init(statusChartRef.value)

  categoryChart.setOption({
    tooltip: { trigger: 'item' },
    legend: { bottom: '0', left: 'center' },
    series: [{
      type: 'pie',
      radius: ['40%', '70%'],
      avoidLabelOverlap: false,
      itemStyle: { borderRadius: 10, borderColor: '#fff', borderWidth: 2 },
      label: { show: false },
      emphasis: { label: { show: true, fontSize: 16, fontWeight: 'bold' } },
      labelLine: { show: false },
      data: [
        { value: 10, name: '审批流程' },
        { value: 5, name: '业务流程' },
        { value: 3, name: '自动化流程' }
      ]
    }]
  })

  statusChart.setOption({
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
    xAxis: { type: 'category', data: ['运行中', '已完成', '已驳回', '已取消', '已撤销'] },
    yAxis: { type: 'value' },
    series: [{
      type: 'bar',
      data: [12, 25, 3, 5, 2],
      itemStyle: {
        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
          { offset: 0, color: '#83bff6' },
          { offset: 1, color: '#188df0' }
        ])
      }
    }]
  })

  window.addEventListener('resize', () => {
    categoryChart.resize()
    statusChart.resize()
  })
}

const loadData = async () => {
  try {
    const [workflows, instances, tasks, users] = await Promise.all([
      getWorkflowList({ pageSize: 1000 }),
      getMyInstances({ pageSize: 5 }),
      getPendingTasks({ pageSize: 5 }),
      getUserList({ pageSize: 1000 })
    ])
    stats.workflows = workflows.total || 0
    stats.users = users.total || 0
    stats.pendingTasks = tasks.total || 0
    recentTasks.value = tasks.data || []
    recentInstances.value = instances.data || []
    stats.runningInstances = instances.total || 0
  } catch (error) {}
}

onMounted(() => {
  nextTick(() => {
    initCharts()
  })
  loadData()
})
</script>

<style scoped>
.dashboard {
  padding: 0;
}

.stats-row {
  margin-bottom: 20px;
}

.stat-card {
  border-radius: 8px;
}

.stat-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.stat-value {
  font-size: 32px;
  font-weight: bold;
  color: #333;
  line-height: 1.2;
}

.stat-label {
  color: #999;
  margin-top: 8px;
  font-size: 14px;
}

.stat-icon {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.stat-icon.workflow {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon.instance {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-icon.task {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-icon.user {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.chart-card,
.list-card {
  border-radius: 8px;
}

.chart {
  height: 300px;
}

.mt-20 {
  margin-top: 20px;
}
</style>
