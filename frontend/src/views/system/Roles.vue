<template>
  <div class="page-container">
    <el-card>
      <div class="toolbar">
        <el-form :inline="true" :model="searchForm" class="search-form">
          <el-form-item label="关键词">
            <el-input v-model="searchForm.keyword" placeholder="角色名称" clearable />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadData">查询</el-button>
            <el-button @click="resetSearch">重置</el-button>
          </el-form-item>
        </el-form>
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>新增角色
        </el-button>
      </div>

      <el-table :data="tableData" v-loading="loading" border>
        <el-table-column type="index" width="60" label="序号" />
        <el-table-column prop="name" label="角色标识" width="150" />
        <el-table-column prop="display_name" label="角色名称" width="150" />
        <el-table-column prop="description" label="描述" />
        <el-table-column label="权限" width="200">
          <template #default="{ row }">
            <el-tag
              v-for="perm in row.permissions?.slice(0, 3)"
              :key="perm.id"
              size="small"
              style="margin-right: 4px; margin-bottom: 4px"
            >{{ perm.display_name }}</el-tag>
            <el-tag v-if="row.permissions?.length > 3" size="small" type="info">
              +{{ row.permissions.length - 3 }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status ? 'success' : 'danger'">
              {{ row.status ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" text @click="handleEdit(row)">编辑</el-button>
            <el-button type="primary" text @click="handlePermission(row)">权限</el-button>
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
      :title="isEdit ? '编辑角色' : '新增角色'"
      width="500px"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="角色标识" prop="name">
          <el-input v-model="form.name" :disabled="isEdit" placeholder="如: admin" />
        </el-form-item>
        <el-form-item label="角色名称" prop="display_name">
          <el-input v-model="form.display_name" placeholder="如: 超级管理员" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="permissionDialogVisible" title="分配权限" width="600px">
      <el-tree
        ref="treeRef"
        :data="permissionTree"
        show-checkbox
        node-key="id"
        :default-checked-keys="checkedPermissions"
        :props="{ label: 'display_name', children: 'children' }"
      />
      <template #footer>
        <el-button @click="permissionDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handlePermissionSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import dayjs from 'dayjs'
import {
  getRoleList,
  createRole,
  updateRole,
  deleteRole,
  getRole,
  getAllPermissions
} from '../../api/role'

const loading = ref(false)
const tableData = ref([])
const dialogVisible = ref(false)
const permissionDialogVisible = ref(false)
const formRef = ref()
const treeRef = ref()
const isEdit = ref(false)
const currentId = ref(null)
const allPermissions = ref([])
const checkedPermissions = ref([])

const searchForm = reactive({ keyword: '' })

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

const form = reactive({
  name: '',
  display_name: '',
  description: '',
  status: 1
})

const rules = {
  name: [{ required: true, message: '请输入角色标识', trigger: 'blur' }],
  display_name: [{ required: true, message: '请输入角色名称', trigger: 'blur' }]
}

const permissionTree = computed(() => {
  const groups = {}
  allPermissions.value.forEach(p => {
    const group = p.group || '其他'
    if (!groups[group]) {
      groups[group] = {
        id: `group_${group}`,
        display_name: group,
        children: []
      }
    }
    groups[group].children.push(p)
  })
  return Object.values(groups)
})

const formatDate = (date) => dayjs(date).format('YYYY-MM-DD HH:mm:ss')

const loadPermissions = async () => {
  try {
    allPermissions.value = await getAllPermissions()
  } catch (error) {}
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getRoleList({
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
  pagination.page = 1
  loadData()
}

const handleAdd = () => {
  isEdit.value = false
  currentId.value = null
  Object.assign(form, {
    name: '',
    display_name: '',
    description: '',
    status: 1
  })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  currentId.value = row.id
  Object.assign(form, {
    name: row.name,
    display_name: row.display_name,
    description: row.description,
    status: row.status
  })
  dialogVisible.value = true
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    if (isEdit.value) {
      await updateRole(currentId.value, form)
      ElMessage.success('更新成功')
    } else {
      await createRole(form)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (error) {}
}

const handleDelete = (row) => {
  ElMessageBox.confirm(`确定要删除角色"${row.display_name}"吗？`, '提示', {
    type: 'warning'
  }).then(async () => {
    await deleteRole(row.id)
    ElMessage.success('删除成功')
    loadData()
  }).catch(() => {})
}

const handlePermission = async (row) => {
  currentId.value = row.id
  try {
    const role = await getRole(row.id)
    checkedPermissions.value = role.permissions.map(p => p.id)
    permissionDialogVisible.value = true
  } catch (error) {}
}

const handlePermissionSubmit = async () => {
  const checkedKeys = treeRef.value.getCheckedKeys(true)
  const permissionIds = checkedKeys.filter(k => !String(k).startsWith('group_'))
  try {
    await updateRole(currentId.value, { permissions: permissionIds })
    ElMessage.success('权限分配成功')
    permissionDialogVisible.value = false
    loadData()
  } catch (error) {}
}

onMounted(() => {
  loadPermissions()
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
</style>
