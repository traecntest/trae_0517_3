<template>
  <div class="instance-detail">
    <div class="detail-header">
      <el-button @click="$router.back()">
        <el-icon><ArrowLeft /></el-icon>返回
      </el-button>
      <div class="header-info">
        <h2>{{ instance?.title }}</h2>
        <div class="meta">
          <el-tag :type="getStatusType(instance?.status)">{{ getStatusText(instance?.status) }}</el-tag>
          <span>流程: {{ instance?.workflow?.name }}</span>
          <span>发起人: {{ instance?.starter?.name }}</span>
          <span>发起时间: {{ formatDate(instance?.started_at) }}</span>
        </div>
      </div>
      <div class="header-actions" v-if="currentTask">
        <el-button type="primary" @click="handleApprove">
          <el-icon><Check /></el-icon>同意
        </el-button>
        <el-button type="danger" @click="handleReject">
          <el-icon><Close /></el-icon>驳回
        </el-button>
      </div>
    </div>

    <el-row :gutter="20" class="detail-body">
      <el-col :span="16">
        <el-card>
          <template #header>流程图</template>
          <div class="flow-chart" ref="flowChartRef">
            <svg width="100%" height="400">
              <defs>
                <marker
                  id="arrowhead-chart"
                  markerWidth="10"
                  markerHeight="7"
                  refX="9"
                  refY="3.5"
                  orient="auto"
                >
                  <polygon points="0 0, 10 3.5, 0 7" fill="#999" />
                </marker>
              </defs>
              <g v-for="edge in edges" :key="edge.edge_id">
                <path
                  :d="getEdgePath(edge)"
                  stroke="#999"
                  stroke-width="2"
                  fill="none"
                  marker-end="url(#arrowhead-chart)"
                />
              </g>
              <g v-for="node in nodes" :key="node.node_id" :transform="`translate(${node.x}, ${node.y})`">
                <rect
                  :width="node.width"
                  :height="node.height"
                  :fill="getNodeFill(node)"
                  stroke="#e6e6e6"
                  stroke-width="2"
                  rx="8"
                />
                <text
                  :x="node.width / 2"
                  :y="node.height / 2 + 4"
                  text-anchor="middle"
                  fill="#333"
                  font-size="12"
                >{{ node.name }}</text>
              </g>
            </svg>
          </div>
        </el-card>
      </el-col>

      <el-col :span="8">
        <el-card class="timeline-card">
          <template #header>审批记录</template>
          <el-timeline>
            <el-timeline-item
              v-for="log in logs"
              :key="log.id"
              :timestamp="formatDate(log.created_at)"
              :type="getTimelineType(log.action)"
            >
              <h4>{{ getActionText(log.action) }}</h4>
              <p>操作人: {{ log.operator?.name }}</p>
              <p v-if="log.comment" class="comment">{{ log.comment }}</p>
            </el-timeline-item>
          </el-timeline>
        </el-card>
      </el-col>
    </el-row>

    <el-dialog v-model="approveDialogVisible" title="审批意见" width="500px">
      <el-form :model="approveForm" label-width="80px">
        <el-form-item label="审批意见">
          <el-input v-model="approveForm.comment" type="textarea" :rows="4" placeholder="请输入审批意见（选填）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="approveDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmApprove">确认同意</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="rejectDialogVisible" title="驳回理由" width="500px">
      <el-form :model="rejectForm" :rules="rejectRules" ref="rejectFormRef" label-width="80px">
        <el-form-item label="驳回理由" prop="comment">
          <el-input v-model="rejectForm.comment" type="textarea" :rows="4" placeholder="请输入驳回理由" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rejectDialogVisible = false">取消</el-button>
        <el-button type="danger" @click="confirmReject">确认驳回</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, Check, Close } from '@element-plus/icons-vue'
import dayjs from 'dayjs'
import { getInstance, getInstanceFlowChart } from '../../api/instance'
import { approveTask, rejectTask, getPendingTasks } from '../../api/task'

const route = useRoute()
const instanceId = route.params.id
const flowChartRef = ref()
const rejectFormRef = ref()

const instance = ref(null)
const nodes = ref([])
const edges = ref([])
const logs = ref([])
const currentTask = ref(null)
const approveDialogVisible = ref(false)
const rejectDialogVisible = ref(false)

const approveForm = reactive({ comment: '' })
const rejectForm = reactive({ comment: '' })
const rejectRules = {
  comment: [{ required: true, message: '请输入驳回理由', trigger: 'blur' }]
}

const formatDate = (date) => dayjs(date).format('YYYY-MM-DD HH:mm:ss')

const getStatusText = (status) => {
  const texts = ['运行中', '已完成', '已驳回', '已取消', '已撤销']
  return texts[status] || '未知'
}

const getStatusType = (status) => {
  const types = ['primary', 'success', 'danger', 'warning', 'info']
  return types[status] || 'info'
}

const getActionText = (action) => {
  const texts = {
    start: '流程启动',
    create_task: '创建任务',
    approve: '审批通过',
    reject: '审批驳回',
    complete: '流程完成',
    cancel: '流程取消',
    automation: '自动化执行'
  }
  return texts[action] || action
}

const getTimelineType = (action) => {
  const types = {
    start: 'primary',
    create_task: 'warning',
    approve: 'success',
    reject: 'danger',
    complete: 'success',
    cancel: 'info',
    automation: ''
  }
  return types[action] || ''
}

const getNodeFill = (node) => {
  const colors = {
    pending: '#f5f5f5',
    approved: '#f0f9eb',
    rejected: '#fef0f0',
    current: '#ecf5ff'
  }
  return colors[node.flow_status] || '#f5f5f5'
}

const getEdgePath = (edge) => {
  const sourceNode = nodes.value.find(n => n.node_id === edge.source_node_id)
  const targetNode = nodes.value.find(n => n.node_id === edge.target_node_id)
  if (!sourceNode || !targetNode) return ''

  const x1 = sourceNode.x + sourceNode.width
  const y1 = sourceNode.y + sourceNode.height / 2
  const x2 = targetNode.x
  const y2 = targetNode.y + targetNode.height / 2
  const midX = (x1 + x2) / 2

  return `M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}`
}

const loadData = async () => {
  try {
    const [instData, flowData] = await Promise.all([
      getInstance(instanceId),
      getInstanceFlowChart(instanceId)
    ])
    instance.value = instData
    nodes.value = flowData.nodes
    edges.value = flowData.edges
    logs.value = instData.logs

    const pendingTasks = await getPendingTasks({ pageSize: 100 })
    currentTask.value = pendingTasks.data?.find(t => t.instance_id == instanceId)
  } catch (error) {}
}

const handleApprove = () => {
  approveForm.comment = ''
  approveDialogVisible.value = true
}

const handleReject = () => {
  rejectForm.comment = ''
  rejectDialogVisible.value = true
}

const confirmApprove = async () => {
  try {
    await approveTask(currentTask.value.id, approveForm)
    ElMessage.success('审批成功')
    approveDialogVisible.value = false
    loadData()
  } catch (error) {}
}

const confirmReject = async () => {
  try {
    await rejectFormRef.value.validate()
    await rejectTask(currentTask.value.id, rejectForm)
    ElMessage.success('已驳回')
    rejectDialogVisible.value = false
    loadData()
  } catch (error) {}
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.instance-detail {
  padding: 0;
}

.detail-header {
  background: #fff;
  padding: 16px 20px;
  margin-bottom: 20px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 20px;
}

.header-info {
  flex: 1;
}

.header-info h2 {
  margin: 0 0 8px 0;
  font-size: 18px;
}

.meta {
  display: flex;
  gap: 16px;
  color: #666;
  font-size: 13px;
}

.detail-body {
  margin-bottom: 20px;
}

.flow-chart {
  background: #fafafa;
  border-radius: 8px;
  overflow: auto;
}

.timeline-card :deep(.el-timeline-item__timestamp) {
  color: #999;
  font-size: 12px;
}

.comment {
  color: #666;
  background: #f5f7fa;
  padding: 8px 12px;
  border-radius: 4px;
  margin-top: 8px;
}
</style>
