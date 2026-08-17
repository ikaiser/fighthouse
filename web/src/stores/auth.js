import { defineStore } from 'pinia'
import { apiClient } from '../api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    isAuthenticated: false,
    checked: false,
  }),
  actions: {
    async login(username, password) {
      await apiClient.post('/admin/login', { username, password })
      this.isAuthenticated = true
      this.checked = true
    },
    async logout() {
      await apiClient.post('/admin/logout')
      this.isAuthenticated = false
      this.checked = true
    },
    // Verifica la sessione lato server: necessario perché lo stato in memoria
    // (isAuthenticated) si resetta ad ogni reload/apertura diretta di un URL,
    // mentre il cookie di sessione PHP resta valido.
    async checkSession() {
      try {
        await apiClient.get('/admin/me')
        this.isAuthenticated = true
      } catch (e) {
        this.isAuthenticated = false
      } finally {
        this.checked = true
      }
    },
  },
})
