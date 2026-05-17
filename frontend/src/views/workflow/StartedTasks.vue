<template>
  <div class="page-container">
    <el-card>
      <el-table :data="tableData" v-loading="loading" border>
        <el-table-column type="index" width="60" label="序号" />
        <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
        <el-table-column prop="workflow.name" label="流程名称" width="150" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
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
import { getMyInstances, cancelInstance } from '../../api/instance'

const router = useRouter()
const loading = ref(false)
const tableData = ref([])

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

const loadData = async () => {
  loading.value = true
  try {
    const res = await getMyInstances({
      page: pagination.page,
      pageSize: pagination.pageSize
    })
    tableData.value = res.data
    pagination.total = res.total
  } finally {
    loading.value = false
  }
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
  loadData()
})
</script>

<style scoped>
.page-container {
  padding: 0;
}

.pagination {
  margin-top: 20px;
  justify-content: flex-end;
}
</style>
