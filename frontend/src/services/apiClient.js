import axios from 'axios'
import { installMockAdapter } from './mockAdapter.js'

export const TOKEN_KEY = 'synapse_token'

const baseURL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

const apiClient = axios.create({
  baseURL,
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)

  if (token) {
    config.headers = config.headers ?? {}
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      localStorage.removeItem(TOKEN_KEY)
    }

    return Promise.reject(error)
  },
)

if (import.meta.env.VITE_USE_MOCK === 'true') {
  installMockAdapter(apiClient)
}

export default apiClient
