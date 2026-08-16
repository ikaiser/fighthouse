<template>
  <div class="users-list">
    <h1>Utenti</h1>
    <input v-model="search" placeholder="Cerca per nome, cognome, telefono" @input="load" />

    <form @submit.prevent="createUser" class="new-user">
      <input v-model="form.nome" placeholder="Nome" required />
      <input v-model="form.cognome" placeholder="Cognome" required />
      <input v-model="form.telefono" placeholder="Telefono" required />
      <input v-model="form.email" placeholder="Email" required />
      <input v-model="form.data_nascita" type="date" required />
      <button type="submit">Aggiungi utente</button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>

    <ul>
      <li v-for="u in users" :key="u.id">
        <router-link :to="`/admin/users/${u.id}`">{{ u.nome }} {{ u.cognome }} — {{ u.telefono }}</router-link>
        (saldo: {{ u.wallet_balance }}€)
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { apiClient } from '../../api/client'

const users = ref([])
const search = ref('')
const error = ref('')
const form = reactive({ nome: '', cognome: '', telefono: '', email: '', data_nascita: '' })

async function load() {
  const { data } = await apiClient.get('/users', { params: { q: search.value || undefined } })
  users.value = data
}

async function createUser() {
  error.value = ''
  try {
    await apiClient.post('/users', form)
    Object.keys(form).forEach((k) => (form[k] = ''))
    await load()
  } catch (e) {
    error.value = e.response?.data?.error || 'errore durante la creazione'
  }
}

onMounted(load)
</script>
