import request from '../utils/request'

export const getUserList = (params) => {
  return request({
    url: '/users',
    method: 'get',
    params
  })
}

export const getUserOptions = () => {
  return request({
    url: '/users/options',
    method: 'get'
  })
}

export const getUser = (id) => {
  return request({
    url: `/users/${id}`,
    method: 'get'
  })
}

export const createUser = (data) => {
  return request({
    url: '/users',
    method: 'post',
    data
  })
}

export const updateUser = (id, data) => {
  return request({
    url: `/users/${id}`,
    method: 'put',
    data
  })
}

export const deleteUser = (id) => {
  return request({
    url: `/users/${id}`,
    method: 'delete'
  })
}

export const updatePassword = (data) => {
  return request({
    url: '/users/password',
    method: 'put',
    data
  })
}
