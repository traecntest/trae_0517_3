<template>
  <div class="page-container">
    <el-card>
      <div class="toolbar">
        <el-form :inline="true" :model="searchForm" class="search-form">
          <el-form-item label="关键词">
            <el-input v-model="searchForm.keyword" placeholder="名称/编码" clearable />
          </el-form-item>
          <el-form-item label="类型">
            <el-select v-model="searchForm.type" placeholder="全部" clearable style="width: 140px">
              <el-option label="审批流程" :value="1" />
              <el-option label="业务流程" :value="2" />
              <el-option label="自动化流程" :value="3" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
              <el-option label="草稿" :value="0" />
              <el-option label="已发布" :value="1" />
              <el-option label="已停用" :value="2" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadData">查询</el-button>
            <el-button @click="resetSearch">重置</el-button>
          </el-form-item>
        </el-form>
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>新建流程
        </el-button>
      </div>

      <el-table :data="tableData" v-loading="loading" border>
        <el-table-column type="index" width="60" label="序号" />
        <el-table-column label="图标" width="80">
          <template #default="{ row }">
            <div
              class="workflow-icon"
              :style="{ background: row.color }"
            >
              <el-icon :size="24"><component :is="row.icon || 'Share'" /></el-icon>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="名称" width="150" />
        <el-table-column prop="code" label="编码" width="150" />
        <el-table-column prop="category" label="分类" width="120" />
        <el-table-column label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="getTypeTagType(row.type)">{{ getTypeText(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="版本" width="80">
          <template #default="{ row }">V{{ row.version }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="creator.name" label="创建人" width="100" />
        <el-table-column prop="created_at" label="创建时间" width="180">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 0" type="primary" text @click="handleDesign(row)">设计</el-button>
            <el-button v-if="row.status === 0" type="success" text @click="handlePublish(row)">发布</el-button>
            <el-button v-if="row.status === 1" type="danger" text @click="handleDisable(row)">停用</el-button>
            <el-button v-if="row.status === 2" type="success" text @click="handleEnable(row)">启用</el-button>
            <el-button type="primary" text @click="handleStart(row)" v-if="row.status === 1">启动</el-button>
            <el-button type="primary" text @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" text @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.pageSize"
        :page-sizes="[10, 20, 50, 100]"
        :total="pagination.total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadData"
        @current-change="loadData"
        class="pagination"
      />
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑流程' : '新建流程'"
      width="600px"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="流程名称" prop="name">
              <el-input v-model="form.name" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="流程编码" prop="code">
              <el-input v-model="form.code" :disabled="isEdit" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="流程类型" prop="type">
              <el-select v-model="form.type" style="width: 100%">
                <el-option label="审批流程" :value="1" />
                <el-option label="业务流程" :value="2" />
                <el-option label="自动化流程" :value="3" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="分类" prop="category">
              <el-input v-model="form.category" placeholder="如: OA审批" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="图标" prop="icon">
              <el-input v-model="form.icon" placeholder="Share" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="颜色" prop="color">
              <el-color-picker v-model="form.color" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="描述" prop="description">
              <el-input v-model="form.description" type="textarea" :rows="3" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="publishDialogVisible" title="发布流程" width="500px">
      <el-form ref="publishFormRef" :model="publishForm" label-width="100px">
        <el-form-item label="更新说明">
          <el-input v-model="publishForm.change_log" type="textarea" :rows="4" placeholder="请输入本次更新的内容说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="publishDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmPublish">确认发布</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="startDialogVisible" title="启动流程" width="500px">
      <el-form ref="startFormRef" :model="startForm" :rules="startRules" label-width="100px">
        <el-form-item label="流程标题" prop="title">
          <el-input v-model="startForm.title" placeholder="请输入流程标题" />
        </el-form-item>
        <el-form-item label="流程描述">
          <el-input v-model="startForm.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="startDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmStart">启动</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Share } from '@element-plus/icons-vue'
import dayjs from 'dayjs'
import {
  getWorkflowList,
  createWorkflow,
  updateWorkflow,
  deleteWorkflow,
  publishWorkflow,
  disableWorkflow,
  enableWorkflow
} from '../../api/workflow'
import { createInstance } from '../../api/instance'

const router = useRouter()
const loading = ref(false)
const tableData = ref([])
const dialogVisible = ref(false)
const publishDialogVisible = ref(false)
const startDialogVisible = ref(false)
const formRef = ref()
const publishFormRef = ref()
const startFormRef = ref()
const isEdit = ref(false)
const currentId = ref(null)

const searchForm = reactive({
  keyword: '',
  type: null,
  status: null
})

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

const form = reactive({
  name: '',
  code: '',
  description: '',
  category: 'default',
  icon: 'Share',
  color: '#1890ff',
  type: 1
})

const rules = {
  name: [{ required: true, message: '请输入流程名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入流程编码', trigger: 'blur' }],
  type: [{ required: true, message: '请选择流程类型', trigger: 'change' }]
}

const publishForm = reactive({ change_log: '' })
const startForm = reactive({ title: '', description: '' })
const startRules = {
  title: [{ required: true, message: '请输入流程标题', trigger: 'blur' }]
}

const formatDate = (date) => dayjs(date).format('YYYY-MM-DD HH:mm:ss')

const getTypeText = (type) => {
  const texts = ['', '审批流程', '业务流程', '自动化流程']
  return texts[type] || '未知'
}

const getTypeTagType = (type) => {
  const types = ['', 'primary', 'success', 'warning']
  return types[type] || 'info'
}

const getStatusText = (status) => {
  const texts = ['草稿', '已发布', '已停用']
  return texts[status] || '未知'
}

const getStatusTagType = (status) => {
  const types = ['info', 'success', 'danger']
  return types[status] || 'info'
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getWorkflowList({
      ...searchForm,
      page: pagination.page,
      pageSize: pagination.pageSize
    })
    tableData.value = res.data
    pagination.total = res.total
  } finally {
    loading.value = false
  }
}

const resetSearch = () => {
  searchForm.keyword = ''
  searchForm.type = null
  searchForm.status = null
  pagination.page = 1
  loadData()
}

const handleAdd = () => {
  isEdit.value = false
  currentId.value = null
  Object.assign(form, {
    name: '',
    code: '',
    description: '',
    category: 'default',
    icon: 'Share',
    color: '#1890ff',
    type: 1
  })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  currentId.value = row.id
  Object.assign(form, {
    name: row.name,
    code: row.code,
    description: row.description,
    category: row.category,
    icon: row.icon,
    color: row.color,
    type: row.type
  })
  dialogVisible.value = true
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    if (isEdit.value) {
      await updateWorkflow(currentId.value, form)
      ElMessage.success('更新成功')
    } else {
      await createWorkflow(form)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (error) {}
}

const handleDelete = (row) => {
  ElMessageBox.confirm(`确定要删除流程"${row.name}"吗？`, '提示', {
    type: 'warning'
  }).then(async () => {
    await deleteWorkflow(row.id)
    ElMessage.success('删除成功')
    loadData()
  }).catch(() => {})
}

const handleDesign = (row) => {
  router.push(`/workflow/design/${row.id}`)
}

const handlePublish = (row) => {
  currentId.value = row.id
  publishForm.change_log = ''
  publishDialogVisible.value = true
}

const confirmPublish = async () => {
  try {
    await publishWorkflow(currentId.value, publishForm)
    ElMessage.success('发布成功')
    publishDialogVisible.value = false
    loadData()
  } catch (error) {}
}

const handleDisable = async (row) => {
  ElMessageBox.confirm(`确定要停用流程"${row.name}"吗？`, '提示', {
    type: 'warning'
  }).then(async () => {
    await disableWorkflow(row.id)
    ElMessage.success('已停用')
    loadData()
  }).catch(() => {})
}

const handleEnable = async (row) => {
  await enableWorkflow(row.id)
  ElMessage.success('已启用')
  loadData()
}

const handleStart = (row) => {
  currentId.value = row.id
  startForm.title = ''
  startForm.description = ''
  startDialogVisible.value = true
}

const confirmStart = async () => {
  try {
    await startFormRef.value.validate()
    await createInstance({
      workflow_id: currentId.value,
      title: startForm.title,
      description: startForm.description
    })
    ElMessage.success('流程启动成功')
    startDialogVisible.value = false
    router.push('/instances')
  } catch (error) {}
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.page-container {
  padding: 0;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}

.search-form {
  margin: 0;
}

.pagination {
  margin-top: 20px;
  justify-content: flex-end;
}

.workflow-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}
</style>
