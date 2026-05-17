<template>
  <div class="page-container">
    <el-card>
      <div class="toolbar">
        <div>
          <el-button type="primary" @click="handleAdd">
            <el-icon><Plus /></el-icon>新增权限
          </el-button>
        </div>
      </div>

      <el-collapse v-model="activeGroups">
        <el-collapse-item
          v-for="(perms, group) in groupedPermissions"
          :key="group"
          :name="group"
        >
          <template #title>
            <span class="group-title">
              <el-icon><Folder /></el-icon>
              {{ group }}
              <el-tag size="small" style="margin-left: 8px">{{ perms.length }}</el-tag>
            </span>
          </template>
          <el-table :data="perms" border size="small">
            <el-table-column prop="name" label="权限标识" width="200" />
            <el-table-column prop="display_name" label="权限名称" width="200" />
            <el-table-column prop="description" label="描述" />
            <el-table-column label="操作" width="150">
              <template #default="{ row }">
                <el-button type="primary" text @click="handleEdit(row)">编辑</el-button>
                <el-button type="danger" text @click="handleDelete(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-collapse-item>
      </el-collapse>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑权限' : '新增权限'"
      width="500px"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="权限标识" prop="name">
          <el-input v-model="form.name" :disabled="isEdit" placeholder="如: user:view" />
        </el-form-item>
        <el-form-item label="权限名称" prop="display_name">
          <el-input v-model="form.display_name" placeholder="如: 查看用户" />
        </el-form-item>
        <el-form-item label="分组" prop="group">
          <el-input v-model="form.group" placeholder="如: 用户管理" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Folder } from '@element-plus/icons-vue'
import {
  getPermissionList,
  createPermission,
  updatePermission,
  deletePermission
} from '../../api/role'

const tableData = ref([])
const dialogVisible = ref(false)
const formRef = ref()
const isEdit = ref(false)
const currentId = ref(null)
const activeGroups = ref([])

const form = reactive({
  name: '',
  display_name: '',
  group: 'default',
  description: ''
})

const rules = {
  name: [{ required: true, message: '请输入权限标识', trigger: 'blur' }],
  display_name: [{ required: true, message: '请输入权限名称', trigger: 'blur' }],
  group: [{ required: true, message: '请输入分组', trigger: 'blur' }]
}

const groupedPermissions = computed(() => {
  const groups = {}
  tableData.value.forEach(p => {
    const group = p.group || '其他'
    if (!groups[group]) {
      groups[group] = []
    }
    groups[group].push(p)
  })
  return groups
})

const loadData = async () => {
  try {
    const res = await getPermissionList()
    tableData.value = res.flat ? Object.values(res).flat() : []
    activeGroups.value = Object.keys(groupedPermissions.value)
  } catch (error) {}
}

const handleAdd = () => {
  isEdit.value = false
  currentId.value = null
  Object.assign(form, {
    name: '',
    display_name: '',
    group: 'default',
    description: ''
  })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  currentId.value = row.id
  Object.assign(form, {
    name: row.name,
    display_name: row.display_name,
    group: row.group,
    description: row.description
  })
  dialogVisible.value = true
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    if (isEdit.value) {
      await updatePermission(currentId.value, form)
      ElMessage.success('更新成功')
    } else {
      await createPermission(form)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (error) {}
}

const handleDelete = (row) => {
  ElMessageBox.confirm(`确定要删除权限"${row.display_name}"吗？`, '提示', {
    type: 'warning'
  }).then(async () => {
    await deletePermission(row.id)
    ElMessage.success('删除成功')
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

.toolbar {
  margin-bottom: 20px;
}

.group-title {
  display: flex;
  align-items: center;
  font-weight: 500;
}
</style>
