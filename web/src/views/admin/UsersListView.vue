<template>
  <AdminLayout>
    <div
      class="page-header"
      style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4)"
    >
      <h1>Utenti</h1>
      <router-link to="/admin/users/new" class="btn btn-primary">Nuovo utente</router-link>
    </div>

    <div class="field">
      <label for="search">Cerca</label>
      <input id="search" v-model="search" type="text" placeholder="Nome, cognome o telefono" @input="load" />
    </div>

    <div class="card">
      <ul>
        <li v-for="u in users" :key="u.id" class="list-item user-row">
          <div>
            <router-link :to="`/admin/users/${u.id}`">{{ u.nome }} {{ u.cognome }}</router-link>
            <span class="hint"> — {{ u.telefono }}</span>
          </div>

          <div class="user-row-actions">
            <span class="wallet-badge">{{ u.wallet_balance }}€</span>
            <router-link :to="`/admin/expenses/new?user_id=${u.id}`" class="btn btn-primary btn-sm"
              >+ Spesa</router-link
            >
            <router-link :to="`/admin/users/${u.id}`" class="btn btn-secondary btn-sm">Vedi</router-link>
            <router-link :to="`/admin/users/${u.id}/edit`" class="btn btn-secondary btn-sm"
              >Modifica</router-link
            >
            <button type="button" class="btn btn-danger btn-sm" @click="deleteUser(u)">Elimina</button>
          </div>
        </li>
        <li v-if="!users.length" class="list-item hint">Nessun utente trovato.</li>
      </ul>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { apiClient } from '../../api/client'
import AdminLayout from '../../components/AdminLayout.vue'

const users = ref([])
const search = ref('')

async function load() {
  const { data } = await apiClient.get('/users', { params: { q: search.value || undefined } })
  users.value = data
}

async function deleteUser(u) {
  if (!confirm(`Eliminare l'utente ${u.nome} ${u.cognome}? L'operazione è irreversibile.`)) return
  await apiClient.delete(`/users/${u.id}`)
  await load()
}

onMounted(load)
</script>
