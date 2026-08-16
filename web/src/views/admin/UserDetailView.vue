<template>
  <AdminLayout v-if="user">
    <div
      class="page-header"
      style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4)"
    >
      <h1>{{ user.nome }} {{ user.cognome }}</h1>
      <div class="user-row-actions">
        <router-link :to="`/admin/expenses/new?user_id=${user.id}`" class="btn btn-primary"
          >Nuova spesa</router-link
        >
        <button type="button" class="btn btn-secondary" @click="editUser">Modifica</button>
      </div>
    </div>
    <p class="hint">Telefono: {{ user.telefono }} — Email: {{ user.email }}</p>

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
import { openUserForm } from '../../composables/useUserFormModal'

const route = useRoute()
const user = ref(null)
const wallet = ref({ wallet_balance: 0, transactions: [] })
const expenses = ref([])

onMounted(async () => {
  const id = route.params.id
  const [userRes, walletRes, expensesRes] = await Promise.all([
    apiClient.get(`/users/${id}`),
    apiClient.get(`/users/${id}/wallet`),
    apiClient.get('/expenses', { params: { user_id: id } }),
  ])
  user.value = userRes.data
  wallet.value = walletRes.data
  expenses.value = expensesRes.data
})

async function editUser() {
  const updated = await openUserForm(user.value.id)
  if (updated) user.value = updated
}
</script>
