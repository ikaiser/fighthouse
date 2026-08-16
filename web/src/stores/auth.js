import { defineStore } from 'pinia'
import { apiClient } from '../api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    isAuthenticated: false,
  }),
  actions: {
    async login(username, password) {
      await apiClient.post('/admin/login', { username, password })
      this.isAuthenticated = true
    },
    async logout() {
      await apiClient.post('/admin/logout')
      this.isAuthenticated = false
    },
  },
})
