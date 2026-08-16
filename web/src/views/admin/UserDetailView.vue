<template>
  <AdminLayout v-if="user">
    <h1>{{ user.nome }} {{ user.cognome }}</h1>
    <p class="hint">Telefono: {{ user.telefono }} — Email: {{ user.email }}</p>

    <div class="balance-hero" style="margin: var(--space-5) 0">
      <p class="balance-label">Saldo wallet</p>
      <p class="balance-value">{{ wallet.wallet_balance }}€</p>
    </div>

    <router-link :to="`/admin/expenses/new?user_id=${user.id}`" class="btn btn-primary"
      >Nuova spesa</router-link
    >

    <h2>Storico movimenti wallet</h2>
    <div class="card">
      <ul>
        <li v-for="(t, i) in wallet.transactions" :key="i" class="list-item">
          <span class="hint">{{ t.created_at }}</span> — {{ t.tipo }}
          <span
            class="amount"
            :class="t.importo >= 0 ? 'amount-positive' : 'amount-negative'"
            style="float: right"
          >
            {{ t.importo >= 0 ? '+' : '' }}{{ t.importo }}€
          </span>
        </li>
        <li v-if="!wallet.transactions.length" class="list-item hint">Nessun movimento.</li>
      </ul>
    </div>

    <h2>Storico spese</h2>
    <div class="card">
      <ul>
        <li v-for="e in expenses" :key="e.id" class="list-item">
          {{ e.data_spesa }} — {{ e.importo }}€
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
</script>
