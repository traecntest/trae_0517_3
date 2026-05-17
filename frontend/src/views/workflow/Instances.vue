<template>
  <div class="page-container">
    <el-card>
      <div class="toolbar">
        <el-form :inline="true" :model="searchForm" class="search-form">
          <el-form-item label="关键词">
            <el-input v-model="searchForm.keyword" placeholder="流程标题" clearable />
          </el-form-item>
          <el-form-item label="流程">
            <el-select v-model="searchForm.workflow_id" placeholder="全部" clearable style="width: 160px">
              <el-option
                v-for="wf in workflowOptions"
                :key="wf.id"
                :label="wf.name"
                :value="wf.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
              <el-option label="运行中" :value="0" />
              <el-option label="已完成" :value="1" />
              <el-option label="已驳回" :value="2" />
              <el-option label="已取消" :value="3" />
              <el-option label="已撤销" :value="4" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadData">查询</el-button>
            <el-button @click="resetSearch">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table :data="tableData" v-loading="loading" border>
        <el-table-column type="index" width="60" label="序号" />
        <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
        <el-table-column prop="workflow.name" label="流程名称" width="150" />
        <el-table-column label="流程图" width="80">
          <template #default="{ row }">
            <el-tag size="small" :style="{ background: row.workflow.color, borderColor: row.workflow.color }">
              <el-icon><component :is="row.workflow.icon || 'Share'" /></el-icon>
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="starter.name" label="发起人" width="100" />
        <el-table-column prop="started_at" label="发起时间" width="180">
          <template #default="{ row }">{{ formatDate(row.started_at) }}</template>
        </el-table-column>
        <el-table-column prop="ended_at" label="结束时间" width="180">
          <template #default="{ row }">{{ row.ended_at ? formatDate(row.ended_at) : '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" text @click="handleView(row)">查看</el-button>
            <el-button
              v-if="row.status === 0"
              type="danger"
              text
              @click="handleCancel(row)"
            >取消</el-button>
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import dayjs from 'dayjs'
import { getInstanceList, cancelInstance } from '../../api/instance'
import { getWorkflowOptions } from '../../api/workflow'

const router = useRouter()
const loading = ref(false)
const tableData = ref([])
const workflowOptions = ref([])

const searchForm = reactive({
  keyword: '',
  workflow_id: null,
  status: null
})

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

const formatDate = (date) => dayjs(date).format('YYYY-MM-DD HH:mm:ss')

const getStatusText = (status) => {
  const texts = ['运行中', '已完成', '已驳回', '已取消', '已撤销']
  return texts[status] || '未知'
}

const getStatusType = (status) => {
  const types = ['primary', 'success', 'danger', 'warning', 'info']
  return types[status] || 'info'
}

const loadWorkflows = async () => {
  try {
    workflowOptions.value = await getWorkflowOptions()
  } catch (error) {}
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getInstanceList({
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
  searchForm.workflow_id = null
  searchForm.status = null
  pagination.page = 1
  loadData()
}

const handleView = (row) => {
  router.push(`/instance/${row.id}`)
}

const handleCancel = (row) => {
  ElMessageBox.confirm(`确定要取消流程"${row.title}"吗？`, '提示', {
    type: 'warning'
  }).then(async () => {
    await cancelInstance(row.id)
    ElMessage.success('已取消')
    loadData()
  }).catch(() => {})
}

onMounted(() => {
  loadWorkflows()
  loadData()
})
</script>

<style scoped>
.page-container {
  padding: 0;
}

.toolbar {
  margin-bottom: 20px;
}

.search-form {
  margin: 0;
}

.pagination {
  margin-top: 20px;
  justify-content: flex-end;
}
</style>
