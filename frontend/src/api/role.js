import request from '../utils/request'

export const getRoleList = (params) => {
  return request({
    url: '/roles',
    method: 'get',
    params
  })
}

export const getAllRoles = () => {
  return request({
    url: '/roles/all',
    method: 'get'
  })
}

export const getRole = (id) => {
  return request({
    url: `/roles/${id}`,
    method: 'get'
  })
}

export const createRole = (data) => {
  return request({
    url: '/roles',
    method: 'post',
    data
  })
}

export const updateRole = (id, data) => {
  return request({
    url: `/roles/${id}`,
    method: 'put',
    data
  })
}

export const deleteRole = (id) => {
  return request({
    url: `/roles/${id}`,
    method: 'delete'
  })
}

export const getPermissionList = () => {
  return request({
    url: '/permissions',
    method: 'get'
  })
}

export const getAllPermissions = () => {
  return request({
    url: '/permissions/all',
    method: 'get'
  })
}

export const createPermission = (data) => {
  return request({
    url: '/permissions',
    method: 'post',
    data
  })
}

export const updatePermission = (id, data) => {
  return request({
    url: `/permissions/${id}`,
    method: 'put',
    data
  })
}

export const deletePermission = (id) => {
  return request({
    url: `/permissions/${id}`,
    method: 'delete'
  })
}
