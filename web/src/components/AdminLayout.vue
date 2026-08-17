<template>
  <div>
    <header class="admin-topbar">
      <router-link to="/admin/athletes" class="brand-mark">FIGHT <span>HOUSE</span></router-link>
      <nav>
        <router-link to="/admin/athletes">Atleti</router-link>
        <router-link to="/admin/coaches">Coach</router-link>
        <button type="button" class="btn btn-secondary btn-sm" @click="logout">Esci</button>
      </nav>
    </header>
    <main class="container">
      <slot />
    </main>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  router.push('/admin/login')
}
</script>
