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

    <div class="card" style="padding: 0; overflow: hidden">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Utente</th>
              <th>Telefono</th>
              <th>Wallet</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td>
                <router-link :to="`/admin/users/${u.id}`">{{ u.nome }} {{ u.cognome }}</router-link>
              </td>
              <td class="hint">{{ u.telefono }}</td>
              <td><span class="wallet-badge">{{ u.wallet_balance }}€</span></td>
              <td class="actions-cell">
                <div class="user-row-actions" style="justify-content: flex-end">
                  <router-link :to="`/admin/expenses/new?user_id=${u.id}`" class="btn btn-primary btn-sm"
                    >+ Spesa</router-link
                  >
                  <ActionMenu label="Azioni">
                    <router-link :to="`/admin/users/${u.id}`">Vedi</router-link>
                    <router-link :to="`/admin/users/${u.id}/edit`">Modifica</router-link>
                    <button type="button" class="danger" @click="deleteUser(u)">Elimina</button>
                  </ActionMenu>
                </div>
              </td>
            </tr>
            <tr v-if="!users.length">
              <td colspan="4" class="hint">Nessun utente trovato.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { apiClient } from '../../api/client'
import AdminLayout from '../../components/AdminLayout.vue'
import ActionMenu from '../../components/ActionMenu.vue'
import { confirmDialog } from '../../composables/useConfirm'

const users = ref([])
const search = ref('')

async function load() {
  const { data } = await apiClient.get('/users', { params: { q: search.value || undefined } })
  users.value = data
}

async function deleteUser(u) {
  const ok = await confirmDialog({
    title: 'Eliminare utente?',
    message: `${u.nome} ${u.cognome} e il suo storico spese verranno eliminati definitivamente. L'operazione non è reversibile.`,
    confirmLabel: 'Elimina',
    cancelLabel: 'Annulla',
  })
  if (!ok) return
  await apiClient.delete(`/users/${u.id}`)
  await load()
}

onMounted(load)
</script>
