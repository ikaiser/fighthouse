import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/admin/LoginView.vue'
import UsersListView from '../views/admin/UsersListView.vue'
import UserDetailView from '../views/admin/UserDetailView.vue'

const routes = [
  { path: '/admin/login', component: LoginView },
  { path: '/admin', redirect: '/admin/users' },
  { path: '/admin/users', component: UsersListView },
  { path: '/admin/users/:id', component: UserDetailView },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.path.startsWith('/admin') && to.path !== '/admin/login' && !auth.isAuthenticated) {
    return '/admin/login'
  }
})
