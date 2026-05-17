import request from '../utils/request'

export const getTaskList = (params) => {
  return request({
    url: '/workflow-tasks',
    method: 'get',
    params
  })
}

export const getMyTasks = (params) => {
  return request({
    url: '/workflow-tasks/my',
    method: 'get',
    params
  })
}

export const getPendingTasks = (params) => {
  return request({
    url: '/workflow-tasks/pending',
    method: 'get',
    params
  })
}

export const getCompletedTasks = (params) => {
  return request({
    url: '/workflow-tasks/completed',
    method: 'get',
    params
  })
}

export const getTask = (id) => {
  return request({
    url: `/workflow-tasks/${id}`,
    method: 'get'
  })
}

export const approveTask = (id, data) => {
  return request({
    url: `/workflow-tasks/${id}/approve`,
    method: 'post',
    data
  })
}

export const rejectTask = (id, data) => {
  return request({
    url: `/workflow-tasks/${id}/reject`,
    method: 'post',
    data
  })
}

export const claimTask = (id) => {
  return request({
    url: `/workflow-tasks/${id}/claim`,
    method: 'post'
  })
}

export const transferTask = (id, data) => {
  return request({
    url: `/workflow-tasks/${id}/transfer`,
    method: 'post',
    data
  })
}
