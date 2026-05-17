import request from '../utils/request'

export const getInstanceList = (params) => {
  return request({
    url: '/workflow-instances',
    method: 'get',
    params
  })
}

export const getMyInstances = (params) => {
  return request({
    url: '/workflow-instances/my',
    method: 'get',
    params
  })
}

export const getInstance = (id) => {
  return request({
    url: `/workflow-instances/${id}`,
    method: 'get'
  })
}

export const getInstanceFlowChart = (id) => {
  return request({
    url: `/workflow-instances/${id}/flowchart`,
    method: 'get'
  })
}

export const createInstance = (data) => {
  return request({
    url: '/workflow-instances',
    method: 'post',
    data
  })
}

export const cancelInstance = (id) => {
  return request({
    url: `/workflow-instances/${id}/cancel`,
    method: 'post'
  })
}
