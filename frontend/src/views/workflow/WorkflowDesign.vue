<template>
  <div class="designer-container">
    <div class="designer-header">
      <div class="header-left">
        <el-button @click="handleBack">
          <el-icon><ArrowLeft /></el-icon>返回
        </el-button>
        <div class="workflow-info">
          <h3>{{ workflow?.name }}</h3>
          <el-tag size="small" :type="workflow?.status === 0 ? 'info' : 'success'">
            {{ workflow?.status === 0 ? '草稿' : '已发布' }}
          </el-tag>
        </div>
      </div>
      <div class="header-right">
        <el-button @click="handleReset">重置</el-button>
        <el-button type="primary" @click="handleSave">保存</el-button>
        <el-button
          type="success"
          :disabled="hasChanges === false"
          @click="handlePublish"
        >
          发布
        </el-button>
      </div>
    </div>

    <div class="designer-body">
      <div class="node-palette">
        <div class="palette-title">节点组件</div>
        <div
          v-for="nodeType in nodeTypes"
          :key="nodeType.type"
          class="palette-item"
          draggable="true"
          @dragstart="onDragStart($event, nodeType)"
        >
          <div class="node-icon" :style="{ background: nodeType.color }">
            <el-icon :size="18"><component :is="nodeType.icon" /></el-icon>
          </div>
          <span>{{ nodeType.label }}</span>
        </div>
      </div>

      <div
        class="canvas-container"
        ref="canvasRef"
        @drop="onDrop"
        @dragover.prevent
        @click="onCanvasClick"
      >
        <div class="canvas" ref="canvasInnerRef">
          <svg class="edges-layer" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <marker
                id="arrowhead"
                markerWidth="10"
                markerHeight="7"
                refX="9"
                refY="3.5"
                orient="auto"
              >
                <polygon points="0 0, 10 3.5, 0 7" fill="#999" />
              </marker>
              <marker
                id="arrowhead-active"
                markerWidth="10"
                markerHeight="7"
                refX="9"
                refY="3.5"
                orient="auto"
              >
                <polygon points="0 0, 10 3.5, 0 7" fill="#409eff" />
              </marker>
            </defs>
            <g v-for="edge in edges" :key="edge.edge_id">
              <path
                :d="getEdgePath(edge)"
                :stroke="selectedEdgeId === edge.edge_id ? '#409eff' : '#999'"
                stroke-width="2"
                fill="none"
                :marker-end="selectedEdgeId === edge.edge_id ? 'url(#arrowhead-active)' : 'url(#arrowhead)'"
                @click.stop="selectEdge(edge)"
                class="edge-path"
              />
              <foreignObject
                v-if="edge.label"
                :x="getLabelPosition(edge).x"
                :y="getLabelPosition(edge).y"
                width="80"
                height="24"
              >
                <div class="edge-label">{{ edge.label }}</div>
              </foreignObject>
            </g>
          </svg>

          <div
            v-for="node in nodes"
            :key="node.node_id"
            class="workflow-node"
            :class="{ selected: selectedNodeId === node.node_id }"
            :style="{
              left: node.x + 'px',
              top: node.y + 'px',
              width: node.width + 'px',
              height: node.height + 'px'
            }"
            @click.stop="selectNode(node)"
            @mousedown="onNodeMouseDown($event, node)"
          >
            <div class="node-header" :style="{ background: getNodeColor(node.type) }">
              <el-icon :size="14"><component :is="getNodeIcon(node.type)" /></el-icon>
              <span class="node-title">{{ node.name }}</span>
            </div>
            <div class="node-body">
              <span>{{ getNodeTypeLabel(node.type) }}</span>
            </div>
            <div
              v-if="node.type !== 'start'"
              class="node-port input"
              @mousedown.stop="onPortMouseDown($event, node, 'input')"
            />
            <div
              v-if="node.type !== 'end'"
              class="node-port output"
              @mousedown.stop="onPortMouseDown($event, node, 'output')"
            />
          </div>

          <svg v-if="tempEdge" class="temp-edge-layer">
            <path
              :d="tempEdge.path"
              stroke="#409eff"
              stroke-width="2"
              stroke-dasharray="5,5"
              fill="none"
            />
          </svg>
        </div>
      </div>

      <div class="property-panel">
        <div class="panel-title" v-if="selectedNode">
          <el-icon><Setting /></el-icon>节点属性
        </div>
        <div class="panel-title" v-else-if="selectedEdge">
          <el-icon><Connection /></el-icon>连线属性
        </div>
        <div class="panel-title" v-else>
          <el-icon><InfoFilled /></el-icon>流程属性
        </div>

        <div class="panel-content" v-if="selectedNode">
          <el-form label-width="80px" size="small">
            <el-form-item label="节点名称">
              <el-input v-model="selectedNode.name" @input="markChanged" />
            </el-form-item>
            <el-form-item label="节点类型">
              <el-tag :type="getNodeTagType(selectedNode.type)" size="small">
                {{ getNodeTypeLabel(selectedNode.type) }}
              </el-tag>
            </el-form-item>
            <template v-if="selectedNode.type === 'approval'">
              <el-form-item label="审批人">
                <el-select v-model="selectedNode.config.assignee_id" @change="markChanged" style="width: 100%">
                  <el-option
                    v-for="user in userOptions"
                    :key="user.id"
                    :label="user.name"
                    :value="user.id"
                  />
                </el-select>
              </el-form-item>
            </template>
            <template v-if="selectedNode.type === 'condition'">
              <el-form-item label="条件表达式">
                <el-input
                  v-model="selectedNode.config.expression"
                  type="textarea"
                  :rows="3"
                  placeholder="如: {amount} > 1000"
                  @input="markChanged"
                />
              </el-form-item>
            </template>
            <template v-if="selectedNode.type === 'automation'">
              <el-form-item label="自动化动作">
                <el-select v-model="selectedNode.config.action" @change="markChanged" style="width: 100%">
                  <el-option label="发送通知" value="notify" />
                  <el-option label="调用Webhook" value="webhook" />
                  <el-option label="更新数据" value="update_data" />
                </el-select>
              </el-form-item>
            </template>
            <el-form-item>
              <el-button type="danger" @click="deleteNode">删除节点</el-button>
            </el-form-item>
          </el-form>
        </div>

        <div class="panel-content" v-else-if="selectedEdge">
          <el-form label-width="80px" size="small">
            <el-form-item label="标签">
              <el-input v-model="selectedEdge.label" @input="markChanged" />
            </el-form-item>
            <el-form-item label="条件表达式">
              <el-input
                v-model="selectedEdge.condition"
                type="textarea"
                :rows="3"
                placeholder="如: {status} == 'approved'"
                @input="markChanged"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="danger" @click="deleteEdge">删除连线</el-button>
            </el-form-item>
          </el-form>
        </div>

        <div class="panel-content" v-else>
          <el-form label-width="80px" size="small">
            <el-form-item label="流程名称">
              <el-input v-model="form.name" />
            </el-form-item>
            <el-form-item label="流程描述">
              <el-input v-model="form.description" type="textarea" :rows="3" />
            </el-form-item>
            <el-form-item label="节点数">
              <span>{{ nodes.length }}</span>
            </el-form-item>
            <el-form-item label="连线数">
              <span>{{ edges.length }}</span>
            </el-form-item>
          </el-form>
        </div>
      </div>
    </div>

    <el-dialog v-model="publishDialogVisible" title="发布流程" width="500px">
      <el-form :model="publishForm" label-width="100px">
        <el-form-item label="更新说明">
          <el-input v-model="publishForm.change_log" type="textarea" :rows="4" placeholder="请输入本次更新的内容说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="publishDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmPublish">确认发布</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, nextTick, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  ArrowLeft,
  Setting,
  Connection,
  InfoFilled,
  VideoPlay,
  VideoPause,
  User,
  SwitchButton,
  CopyDocument,
  MagicStick,
  Timer,
  Folder
} from '@element-plus/icons-vue'
import { getWorkflow, saveWorkflowDesign, publishWorkflow } from '../../api/workflow'
import { getUserOptions } from '../../api/user'

const route = useRoute()
const router = useRouter()
const workflowId = route.params.id

const canvasRef = ref()
const canvasInnerRef = ref()
const workflow = ref(null)
const nodes = ref([])
const edges = ref([])
const selectedNodeId = ref(null)
const selectedEdgeId = ref(null)
const hasChanges = ref(false)
const publishDialogVisible = ref(false)
const userOptions = ref([])
let nodeCounter = 1
let edgeCounter = 1
let isDragging = false
let dragNode = null
let dragOffset = { x: 0, y: 0 }
let isConnecting = false
let tempEdge = null
let connectStart = null

const form = reactive({ name: '', description: '' })
const publishForm = reactive({ change_log: '' })

const nodeTypes = [
  { type: 'start', label: '开始', icon: 'VideoPlay', color: '#52c41a' },
  { type: 'end', label: '结束', icon: 'VideoPause', color: '#ff4d4f' },
  { type: 'approval', label: '审批节点', icon: 'User', color: '#1890ff' },
  { type: 'condition', label: '条件分支', icon: 'SwitchButton', color: '#faad14' },
  { type: 'parallel', label: '并行网关', icon: 'CopyDocument', color: '#13c2c2' },
  { type: 'automation', label: '自动节点', icon: 'MagicStick', color: '#722ed1' },
  { type: 'delay', label: '延时节点', icon: 'Timer', color: '#fa8c16' }
]

const selectedNode = computed(() => nodes.value.find(n => n.node_id === selectedNodeId.value))
const selectedEdge = computed(() => edges.value.find(e => e.edge_id === selectedEdgeId.value))

const getNodeColor = (type) => nodeTypes.find(t => t.type === type)?.color || '#999'
const getNodeIcon = (type) => nodeTypes.find(t => t.type === type)?.icon || 'Setting'
const getNodeTypeLabel = (type) => nodeTypes.find(t => t.type === type)?.label || type

const getNodeTagType = (type) => {
  const types = {
    start: 'success',
    end: 'danger',
    approval: 'primary',
    condition: 'warning',
    parallel: 'info',
    automation: '',
    delay: 'warning'
  }
  return types[type] || 'info'
}

const loadWorkflow = async () => {
  try {
    const res = await getWorkflow(workflowId)
    workflow.value = res
    nodes.value = res.nodes || []
    edges.value = res.edges || []
    form.name = res.name
    form.description = res.description
    nodeCounter = Math.max(...nodes.value.map(n => parseInt(n.node_id.split('_')[1]) || 0), 0) + 1
    edgeCounter = Math.max(...edges.value.map(e => parseInt(e.edge_id.split('_')[1]) || 0), 0) + 1
  } catch (error) {}
}

const loadUsers = async () => {
  try {
    userOptions.value = await getUserOptions()
  } catch (error) {}
}

const onDragStart = (e, nodeType) => {
  e.dataTransfer.setData('nodeType', JSON.stringify(nodeType))
}

const onDrop = (e) => {
  e.preventDefault()
  const nodeTypeData = e.dataTransfer.getData('nodeType')
  if (!nodeTypeData) return

  const nodeType = JSON.parse(nodeTypeData)
  const rect = canvasInnerRef.value.getBoundingClientRect()
  const x = e.clientX - rect.left - 80
  const y = e.clientY - rect.top - 30

  const newNode = {
    node_id: `node_${nodeCounter++}`,
    name: nodeType.label,
    type: nodeType.type,
    x: Math.max(0, x),
    y: Math.max(0, y),
    width: 160,
    height: 60,
    config: {}
  }

  nodes.value.push(newNode)
  markChanged()
}

const selectNode = (node) => {
  selectedNodeId.value = node.node_id
  selectedEdgeId.value = null
}

const selectEdge = (edge) => {
  selectedEdgeId.value = edge.edge_id
  selectedNodeId.value = null
}

const onCanvasClick = () => {
  selectedNodeId.value = null
  selectedEdgeId.value = null
}

const onNodeMouseDown = (e, node) => {
  if (e.target.classList.contains('node-port')) return

  isDragging = true
  dragNode = node
  const rect = e.currentTarget.getBoundingClientRect()
  dragOffset = {
    x: e.clientX - rect.left,
    y: e.clientY - rect.top
  }

  document.addEventListener('mousemove', onNodeMouseMove)
  document.addEventListener('mouseup', onNodeMouseUp)
}

const onNodeMouseMove = (e) => {
  if (!isDragging || !dragNode) return

  const rect = canvasInnerRef.value.getBoundingClientRect()
  dragNode.x = Math.max(0, e.clientX - rect.left - dragOffset.x)
  dragNode.y = Math.max(0, e.clientY - rect.top - dragOffset.y)
  markChanged()
}

const onNodeMouseUp = () => {
  isDragging = false
  dragNode = null
  document.removeEventListener('mousemove', onNodeMouseMove)
  document.removeEventListener('mouseup', onNodeMouseUp)
}

const onPortMouseDown = (e, node, portType) => {
  if (portType !== 'output') return

  isConnecting = true
  connectStart = { node, portType }
  const rect = e.currentTarget.getBoundingClientRect()
  const canvasRect = canvasInnerRef.value.getBoundingClientRect()

  tempEdge = {
    path: `M ${rect.left - canvasRect.left + 8} ${rect.top - canvasRect.top + 8} L ${rect.left - canvasRect.left + 8} ${rect.top - canvasRect.top + 8}`
  }

  document.addEventListener('mousemove', onConnectMouseMove)
  document.addEventListener('mouseup', onConnectMouseUp)
}

const onConnectMouseMove = (e) => {
  if (!isConnecting || !connectStart) return

  const rect = canvasInnerRef.value.getBoundingClientRect()
  const startRect = document.querySelector(`[data-node-id="${connectStart.node.node_id}"] .node-port.output`)?.getBoundingClientRect()

  if (startRect) {
    const startX = startRect.left - rect.left + 8
    const startY = startRect.top - rect.top + 8
    const endX = e.clientX - rect.left
    const endY = e.clientY - rect.top

    tempEdge.path = `M ${startX} ${startY} C ${startX + 50} ${startY}, ${endX - 50} ${endY}, ${endX} ${endY}`
  }
}

const onConnectMouseUp = (e) => {
  if (!isConnecting) return

  const target = e.target
  if (target.classList.contains('node-port') && target.classList.contains('input')) {
    const targetNodeEl = target.closest('.workflow-node')
    const targetNodeId = targetNodeEl?.getAttribute('data-node-id')
    const targetNode = nodes.value.find(n => n.node_id === targetNodeId)

    if (targetNode && targetNode.node_id !== connectStart.node.node_id) {
      const existingEdge = edges.value.find(
        e => e.source_node_id === connectStart.node.node_id && e.target_node_id === targetNode.node_id
      )

      if (!existingEdge) {
        edges.value.push({
          edge_id: `edge_${edgeCounter++}`,
          source_node_id: connectStart.node.node_id,
          target_node_id: targetNode.node_id,
          label: '',
          condition: null
        })
        markChanged()
      }
    }
  }

  isConnecting = false
  connectStart = null
  tempEdge = null
  document.removeEventListener('mousemove', onConnectMouseMove)
  document.removeEventListener('mouseup', onConnectMouseUp)
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

const getLabelPosition = (edge) => {
  const sourceNode = nodes.value.find(n => n.node_id === edge.source_node_id)
  const targetNode = nodes.value.find(n => n.node_id === edge.target_node_id)

  if (!sourceNode || !targetNode) return { x: 0, y: 0 }

  return {
    x: (sourceNode.x + targetNode.x) / 2 - 40,
    y: (sourceNode.y + targetNode.y) / 2 - 12
  }
}

const deleteNode = () => {
  if (!selectedNode.value) return
  if (selectedNode.value.type === 'start' || selectedNode.value.type === 'end') {
    ElMessage.warning('开始和结束节点不能删除')
    return
  }

  edges.value = edges.value.filter(
    e => e.source_node_id !== selectedNodeId.value && e.target_node_id !== selectedNodeId.value
  )
  nodes.value = nodes.value.filter(n => n.node_id !== selectedNodeId.value)
  selectedNodeId.value = null
  markChanged()
}

const deleteEdge = () => {
  if (!selectedEdge.value) return
  edges.value = edges.value.filter(e => e.edge_id !== selectedEdgeId.value)
  selectedEdgeId.value = null
  markChanged()
}

const markChanged = () => {
  hasChanges.value = true
}

const handleBack = () => {
  if (hasChanges.value) {
    ElMessageBox.confirm('设计内容有变更，确定要离开吗？', '提示', {
      type: 'warning'
    }).then(() => {
      router.back()
    }).catch(() => {})
  } else {
    router.back()
  }
}

const handleReset = () => {
  if (!hasChanges.value) return
  ElMessageBox.confirm('确定要重置到上次保存的状态吗？', '提示', {
    type: 'warning'
  }).then(async () => {
    await loadWorkflow()
    hasChanges.value = false
    ElMessage.success('已重置')
  }).catch(() => {})
}

const handleSave = async () => {
  try {
    await saveWorkflowDesign(workflowId, {
      nodes: nodes.value,
      edges: edges.value
    })
    hasChanges.value = false
    ElMessage.success('保存成功')
  } catch (error) {}
}

const handlePublish = () => {
  publishForm.change_log = ''
  publishDialogVisible.value = true
}

const confirmPublish = async () => {
  try {
    await handleSave()
    await publishWorkflow(workflowId, publishForm)
    hasChanges.value = false
    publishDialogVisible.value = false
    ElMessage.success('发布成功')
    router.back()
  } catch (error) {}
}

onMounted(async () => {
  await loadWorkflow()
  await loadUsers()
})
</script>

<style scoped>
.designer-container {
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f0f2f5;
}

.designer-header {
  height: 60px;
  background: #fff;
  border-bottom: 1px solid #e6e6e6;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.workflow-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.workflow-info h3 {
  margin: 0;
  font-size: 16px;
}

.designer-body {
  flex: 1;
  display: flex;
  overflow: hidden;
}

.node-palette {
  width: 200px;
  background: #fff;
  border-right: 1px solid #e6e6e6;
  padding: 16px;
  overflow-y: auto;
}

.palette-title {
  font-weight: 500;
  color: #333;
  margin-bottom: 16px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e6e6e6;
}

.palette-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 6px;
  cursor: grab;
  transition: all 0.2s;
  margin-bottom: 8px;
  border: 1px solid #e6e6e6;
}

.palette-item:hover {
  background: #f0f7ff;
  border-color: #409eff;
}

.node-icon {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.canvas-container {
  flex: 1;
  overflow: auto;
  position: relative;
}

.canvas {
  min-width: 2000px;
  min-height: 1500px;
  background-image:
    linear-gradient(#e6e6e6 1px, transparent 1px),
    linear-gradient(90deg, #e6e6e6 1px, transparent 1px);
  background-size: 20px 20px;
  position: relative;
}

.edges-layer {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
}

.edges-layer .edge-path {
  pointer-events: stroke;
  cursor: pointer;
}

.temp-edge-layer {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
}

.workflow-node {
  position: absolute;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  cursor: move;
  transition: box-shadow 0.2s;
}

.workflow-node:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.workflow-node.selected {
  box-shadow: 0 0 0 2px #409eff;
}

.node-header {
  padding: 6px 12px;
  color: #fff;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.node-title {
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.node-body {
  padding: 8px 12px;
  font-size: 12px;
  color: #666;
}

.node-port {
  position: absolute;
  width: 16px;
  height: 16px;
  background: #fff;
  border: 2px solid #409eff;
  border-radius: 50%;
  cursor: crosshair;
  transition: all 0.2s;
  z-index: 10;
}

.node-port:hover {
  transform: scale(1.3);
  background: #409eff;
}

.node-port.input {
  left: -8px;
  top: 50%;
  transform: translateY(-50%);
}

.node-port.output {
  right: -8px;
  top: 50%;
  transform: translateY(-50%);
}

.edge-label {
  background: #fff;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  color: #666;
  text-align: center;
  border: 1px solid #e6e6e6;
}

.property-panel {
  width: 320px;
  background: #fff;
  border-left: 1px solid #e6e6e6;
  overflow-y: auto;
}

.panel-title {
  padding: 16px;
  font-weight: 500;
  color: #333;
  border-bottom: 1px solid #e6e6e6;
  display: flex;
  align-items: center;
  gap: 8px;
}

.panel-content {
  padding: 16px;
}
</style>
