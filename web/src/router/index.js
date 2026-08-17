import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/admin/LoginView.vue'
import AthletesListView from '../views/admin/AthletesListView.vue'
import AthleteDetailView from '../views/admin/AthleteDetailView.vue'
import CoachesListView from '../views/admin/CoachesListView.vue'
import CoachDetailView from '../views/admin/CoachDetailView.vue'
import ExpenseFormView from '../views/admin/ExpenseFormView.vue'
import WalletLookupView from '../views/public/WalletLookupView.vue'

const routes = [
  { path: '/', redirect: '/admin/login' },
  { path: '/admin/login', component: LoginView },
  { path: '/admin', redirect: '/admin/athletes' },
  { path: '/admin/athletes', component: AthletesListView },
  { path: '/admin/athletes/:id', component: AthleteDetailView },
  { path: '/admin/coaches', component: CoachesListView },
  { path: '/admin/coaches/:id', component: CoachDetailView },
  { path: '/admin/expenses/new', component: ExpenseFormView },
  { path: '/wallet', component: WalletLookupView },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const isProtected = to.path.startsWith('/admin') && to.path !== '/admin/login'
  if (!isProtected) return

  if (!auth.checked) {
    await auth.checkSession()
  }
  if (!auth.isAuthenticated) {
    return '/admin/login'
  }
})
