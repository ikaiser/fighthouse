<template>
  <div v-if="user" class="user-detail">
    <h1>{{ user.nome }} {{ user.cognome }}</h1>
    <p>Telefono: {{ user.telefono }} — Email: {{ user.email }}</p>
    <p class="balance">Saldo wallet: <strong>{{ wallet.wallet_balance }}€</strong></p>
    <router-link :to="`/admin/expenses/new?user_id=${user.id}`">Nuova spesa</router-link>

    <h2>Storico movimenti wallet</h2>
    <ul>
      <li v-for="(t, i) in wallet.transactions" :key="i">
        {{ t.created_at }} — {{ t.tipo }}: {{ t.importo }}€ (saldo dopo: {{ t.saldo_dopo }}€)
      </li>
    </ul>

    <h2>Storico spese</h2>
    <ul>
      <li v-for="e in expenses" :key="e.id">
        {{ e.data_spesa }} — {{ e.importo }}€ (wallet usato: {{ e.wallet_usato }}€)
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { apiClient } from '../../api/client'

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
