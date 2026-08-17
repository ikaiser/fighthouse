<template>
  <div class="screen-center">
    <div class="card" style="width: 100%; max-width: 380px">
      <p class="brand-mark" style="margin-bottom: var(--space-2)">FIGHT <span>HOUSE</span></p>
      <h1 style="font-size: 28px">Accesso amministratore</h1>
      <form @submit.prevent="submit">
        <div class="field">
          <label for="username">Username</label>
          <input id="username" v-model="username" type="text" required autocomplete="username" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
          />
        </div>
        <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
          {{ loading ? 'Accesso in corso…' : 'Accedi' }}
        </button>
        <p v-if="error" class="error">{{ error }}</p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const auth = useAuthStore()
const router = useRouter()

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(username.value, password.value)
    router.push('/admin/athletes')
  } catch (e) {
    error.value = 'Credenziali non valide'
  } finally {
    loading.value = false
  }
}
</script>
