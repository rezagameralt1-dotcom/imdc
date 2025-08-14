import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  timeout: 15000
})

export async function getPosts(page = 1) {
  const { data } = await api.get('/posts', { params: { page } })
  return data
}

export async function getHealth() {
  const { data } = await api.get('/health')
  return data
}

