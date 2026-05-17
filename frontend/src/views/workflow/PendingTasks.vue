<template>
  <div class="page-container">
    <el-card>
      <el-table :data="tableData" v-loading="loading" border>
        <el-table-column type="index" width="60" label="序号" />
        <el-table-column prop="instance.title" label="流程标题" min-width="200" show-overflow-tooltip />
        <el-table-column prop="workflow.name" label="流程名称" width="150">
          <template #default="{ row }">{{ row.instance?.workflow?.name }}</template>
        </el-table-column>
        <el-table-column prop="node_name" label="节点名称" width="150" />
        <el-table-column prop="creator.name" label="发起人" width="100">
          <template #default="{ row }">{{ row.instance?.starter?.name }}</template>
        </el-table-column>
        <el-table-column prop="created_at" label="收到时间" width="180">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" text @click="handleView(row)">处理</el-button>
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
import dayjs from 'dayjs'
import { getPendingTasks } from '../../api/task'

const router = useRouter()
const loading = ref(false)
const tableData = ref([])

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

const formatDate = (date) => dayjs(date).format('YYYY-MM-DD HH:mm:ss')

const loadData = async () => {
  loading.value = true
  try {
    const res = await getPendingTasks({
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
  router.push(`/instance/${row.instance_id}`)
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
