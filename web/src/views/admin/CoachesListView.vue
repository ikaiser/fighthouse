<template>
  <AdminLayout>
    <div
      class="page-header"
      style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4)"
    >
      <h1>Coach</h1>
      <button type="button" class="btn btn-primary" @click="createCoach">Nuovo coach</button>
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <div class="card" style="padding: 0; overflow: hidden">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Coach</th>
              <th>Telefono</th>
              <th>Wallet</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in coaches" :key="c.id">
              <td>
                <router-link :to="`/admin/coaches/${c.id}`">{{ c.nome }} {{ c.cognome }}</router-link>
              </td>
              <td class="hint">{{ c.telefono }}</td>
              <td><span class="wallet-badge">{{ c.wallet_balance }}€</span></td>
              <td class="actions-cell">
                <div class="user-row-actions" style="justify-content: flex-end">
                  <ActionMenu label="Azioni">
                    <router-link :to="`/admin/coaches/${c.id}`">Vedi</router-link>
                    <button type="button" @click="editCoach(c)">Modifica</button>
                    <button type="button" class="danger" @click="deleteCoach(c)">Elimina</button>
                  </ActionMenu>
                </div>
              </td>
            </tr>
            <tr v-if="!coaches.length">
              <td colspan="4" class="hint">Nessun coach trovato.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="lastPage > 1" class="user-row-actions" style="justify-content: flex-end; margin-top: var(--space-4)">
      <button type="button" class="btn btn-secondary btn-sm" :disabled="page <= 1" @click="goToPage(page - 1)">
        Precedente
      </button>
      <span class="hint">Pagina {{ page }} di {{ lastPage }}</span>
      <button type="button" class="btn btn-secondary btn-sm" :disabled="page >= lastPage" @click="goToPage(page + 1)">
        Successiva
      </button>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { apiClient } from '../../api/client'
import AdminLayout from '../../components/AdminLayout.vue'
import ActionMenu from '../../components/ActionMenu.vue'
import { confirmDialog } from '../../composables/useConfirm'
import { openCoachForm } from '../../composables/useCoachFormModal'

const PER_PAGE = 20

const coaches = ref([])
const error = ref('')
const page = ref(1)
const lastPage = ref(1)

async function load() {
  const { data } = await apiClient.get('/coaches', { params: { page: page.value, per_page: PER_PAGE } })
  coaches.value = data.data
  lastPage.value = data.meta.last_page
}

function goToPage(target) {
  page.value = target
  load()
}

async function createCoach() {
  const coach = await openCoachForm(null)
  if (coach) await load()
}

async function editCoach(c) {
  const updated = await openCoachForm(c.id)
  if (updated) await load()
}

async function deleteCoach(c) {
  const ok = await confirmDialog({
    title: 'Eliminare coach?',
    message: `${c.nome} ${c.cognome} verrà eliminato. Non è possibile se ha ancora atleti assegnati.`,
    confirmLabel: 'Elimina',
    cancelLabel: 'Annulla',
  })
  if (!ok) return
  error.value = ''
  try {
    await apiClient.delete(`/coaches/${c.id}`)
    await load()
  } catch (e) {
    error.value = e.response?.data?.error || "errore durante l'eliminazione"
  }
}

onMounted(load)
</script>
