<template>
  <AdminLayout>
    <div
      class="page-header"
      style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4)"
    >
      <h1>Atleti</h1>
      <button type="button" class="btn btn-primary" @click="createAthlete">Nuovo atleta</button>
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
              <th>Atleta</th>
              <th>Telefono</th>
              <th>Coach</th>
              <th>Wallet</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in athletes" :key="a.id">
              <td>
                <router-link :to="`/admin/athletes/${a.id}`">{{ a.nome }} {{ a.cognome }}</router-link>
              </td>
              <td class="hint">{{ a.telefono }}</td>
              <td class="hint">{{ a.coach?.nome }} {{ a.coach?.cognome }}</td>
              <td><span class="wallet-badge">{{ a.wallet_balance }}€</span></td>
              <td class="actions-cell">
                <div class="user-row-actions" style="justify-content: flex-end">
                  <router-link :to="`/admin/expenses/new?user_id=${a.id}`" class="btn btn-primary btn-sm"
                    >+ Spesa</router-link
                  >
                  <ActionMenu label="Azioni">
                    <router-link :to="`/admin/athletes/${a.id}`">Vedi</router-link>
                    <button type="button" @click="editAthlete(a)">Modifica</button>
                    <button type="button" class="danger" @click="deleteAthlete(a)">Elimina</button>
                  </ActionMenu>
                </div>
              </td>
            </tr>
            <tr v-if="!athletes.length">
              <td colspan="5" class="hint">Nessun atleta trovato.</td>
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
import { openAthleteForm } from '../../composables/useAthleteFormModal'

const athletes = ref([])
const search = ref('')

async function load() {
  const { data } = await apiClient.get('/athletes', { params: { q: search.value || undefined } })
  athletes.value = data
}

async function createAthlete() {
  const athlete = await openAthleteForm(null)
  if (athlete) await load()
}

async function editAthlete(a) {
  const updated = await openAthleteForm(a.id)
  if (updated) await load()
}

async function deleteAthlete(a) {
  const ok = await confirmDialog({
    title: 'Eliminare atleta?',
    message: `${a.nome} ${a.cognome} e il suo storico spese verranno eliminati definitivamente. L'operazione non è reversibile.`,
    confirmLabel: 'Elimina',
    cancelLabel: 'Annulla',
  })
  if (!ok) return
  await apiClient.delete(`/athletes/${a.id}`)
  await load()
}

onMounted(load)
</script>
