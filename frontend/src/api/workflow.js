import request from '../utils/request'

export const getWorkflowList = (params) => {
  return request({
    url: '/workflows',
    method: 'get',
    params
  })
}

export const getWorkflowOptions = () => {
  return request({
    url: '/workflows/options',
    method: 'get'
  })
}

export const getWorkflow = (id) => {
  return request({
    url: `/workflows/${id}`,
    method: 'get'
  })
}

export const getWorkflowDefinition = (id) => {
  return request({
    url: `/workflows/${id}/definition`,
    method: 'get'
  })
}

export const createWorkflow = (data) => {
  return request({
    url: '/workflows',
    method: 'post',
    data
  })
}

export const updateWorkflow = (id, data) => {
  return request({
    url: `/workflows/${id}`,
    method: 'put',
    data
  })
}

export const deleteWorkflow = (id) => {
  return request({
    url: `/workflows/${id}`,
    method: 'delete'
  })
}

export const saveWorkflowDesign = (id, data) => {
  return request({
    url: `/workflows/${id}/design`,
    method: 'post',
    data
  })
}

export const publishWorkflow = (id, data) => {
  return request({
    url: `/workflows/${id}/publish`,
    method: 'post',
    data
  })
}

export const disableWorkflow = (id) => {
  return request({
    url: `/workflows/${id}/disable`,
    method: 'post'
  })
}

export const enableWorkflow = (id) => {
  return request({
    url: `/workflows/${id}/enable`,
    method: 'post'
  })
}
