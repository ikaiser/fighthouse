<template>
  <AdminLayout v-if="athlete">
    <div
      class="page-header"
      style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4)"
    >
      <h1>{{ athlete.nome }} {{ athlete.cognome }}</h1>
      <div class="user-row-actions">
        <router-link :to="`/admin/expenses/new?user_id=${athlete.id}`" class="btn btn-primary"
          >Nuova spesa</router-link
        >
        <button type="button" class="btn btn-secondary" @click="editAthlete">Modifica</button>
      </div>
    </div>
    <p class="hint">
      Telefono: {{ athlete.telefono }} — Email: {{ athlete.email }} — Coach:
      {{ athlete.coach?.nome }} {{ athlete.coach?.cognome }}
    </p>

    <div class="balance-hero" style="margin: var(--space-5) 0">
      <p class="balance-label">Saldo wallet</p>
      <p class="balance-value">{{ wallet.wallet_balance }}€</p>
    </div>

    <h2>Storico spese</h2>
    <div class="card">
      <ul>
        <li v-for="e in expenses" :key="e.id" class="list-item">
          {{ formatDate(e.data_spesa) }} — {{ e.importo }}€
          <span v-if="e.wallet_usato > 0" class="hint"> (wallet usato: {{ e.wallet_usato }}€)</span>
        </li>
        <li v-if="!expenses.length" class="list-item hint">Nessuna spesa registrata.</li>
      </ul>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { apiClient } from '../../api/client'
import AdminLayout from '../../components/AdminLayout.vue'
import { formatDate } from '../../utils/date'
import { openAthleteForm } from '../../composables/useAthleteFormModal'

const route = useRoute()
const athlete = ref(null)
const wallet = ref({ wallet_balance: 0, transactions: [] })
const expenses = ref([])

onMounted(async () => {
  const id = route.params.id
  const [athleteRes, walletRes, expensesRes] = await Promise.all([
    apiClient.get(`/athletes/${id}`),
    apiClient.get(`/athletes/${id}/wallet`),
    apiClient.get('/expenses', { params: { user_id: id } }),
  ])
  athlete.value = athleteRes.data
  wallet.value = walletRes.data
  expenses.value = expensesRes.data
})

async function editAthlete() {
  const updated = await openAthleteForm(athlete.value.id)
  if (updated) athlete.value = updated
}
</script>
