import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/admin/LoginView.vue'
import UsersListView from '../views/admin/UsersListView.vue'
import UserDetailView from '../views/admin/UserDetailView.vue'
import UserFormView from '../views/admin/UserFormView.vue'
import ExpenseFormView from '../views/admin/ExpenseFormView.vue'
import WalletLookupView from '../views/public/WalletLookupView.vue'

const routes = [
  { path: '/', redirect: '/admin/login' },
  { path: '/admin/login', component: LoginView },
  { path: '/admin', redirect: '/admin/users' },
  { path: '/admin/users', component: UsersListView },
  { path: '/admin/users/new', component: UserFormView },
  { path: '/admin/users/:id', component: UserDetailView },
  { path: '/admin/users/:id/edit', component: UserFormView },
  { path: '/admin/expenses/new', component: ExpenseFormView },
  { path: '/wallet', component: WalletLookupView },
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
