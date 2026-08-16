<template>
  <AdminLayout>
    <h1>Utenti</h1>

    <div class="field" style="max-width: 400px">
      <label for="search">Cerca</label>
      <input id="search" v-model="search" type="text" placeholder="Nome, cognome o telefono" @input="load" />
    </div>

    <div class="card" style="margin-bottom: var(--space-6)">
      <h2 style="margin-top: 0">Nuovo utente</h2>
      <form @submit.prevent="createUser">
        <div class="field">
          <label for="nome">Nome</label>
          <input id="nome" v-model="form.nome" type="text" required />
        </div>
        <div class="field">
          <label for="cognome">Cognome</label>
          <input id="cognome" v-model="form.cognome" type="text" required />
        </div>
        <div class="field">
          <label for="telefono">Telefono</label>
          <input id="telefono" v-model="form.telefono" type="tel" required />
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" v-model="form.email" type="email" required />
        </div>
        <div class="field">
          <label for="data_nascita">Data di nascita</label>
          <input id="data_nascita" v-model="form.data_nascita" type="date" required />
        </div>
        <button type="submit" class="btn btn-primary">Aggiungi utente</button>
        <p v-if="error" class="error">{{ error }}</p>
      </form>
    </div>

    <div class="card">
      <ul>
        <li v-for="u in users" :key="u.id" class="list-item">
          <router-link :to="`/admin/users/${u.id}`">{{ u.nome }} {{ u.cognome }}</router-link>
          <span class="hint"> — {{ u.telefono }}</span>
          <span class="amount amount-positive" style="float: right">{{ u.wallet_balance }}€</span>
        </li>
        <li v-if="!users.length" class="list-item hint">Nessun utente trovato.</li>
      </ul>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { apiClient } from '../../api/client'
import AdminLayout from '../../components/AdminLayout.vue'

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
