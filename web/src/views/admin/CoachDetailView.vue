<template>
  <AdminLayout v-if="coach">
    <div
      class="page-header"
      style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4)"
    >
      <h1>{{ coach.nome }} {{ coach.cognome }}</h1>
      <button type="button" class="btn btn-secondary" @click="editCoach">Modifica</button>
    </div>
    <p class="hint">Telefono: {{ coach.telefono }} — Email: {{ coach.email }}</p>

    <div class="balance-hero" style="margin: var(--space-5) 0">
      <p class="balance-label">Saldo wallet</p>
      <p class="balance-value">{{ wallet.wallet_balance }}€</p>
    </div>

    <h2>Storico movimenti wallet</h2>
    <div class="card">
      <ul>
        <li v-for="(t, i) in wallet.transactions" :key="i" class="list-item">
          <span class="hint">{{ formatDateTime(t.created_at) }}</span> — {{ t.tipo }}
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

    <h2>Atleti assegnati</h2>
    <div class="card">
      <ul>
        <li v-for="a in athletes" :key="a.id" class="list-item">
          <router-link :to="`/admin/athletes/${a.id}`">{{ a.nome }} {{ a.cognome }}</router-link>
          <span class="hint"> — {{ a.telefono }}</span>
        </li>
        <li v-if="!athletes.length" class="list-item hint">Nessun atleta assegnato.</li>
      </ul>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { apiClient } from '../../api/client'
import AdminLayout from '../../components/AdminLayout.vue'
import { formatDateTime } from '../../utils/date'
import { openCoachForm } from '../../composables/useCoachFormModal'

const route = useRoute()
const coach = ref(null)
const wallet = ref({ wallet_balance: 0, transactions: [] })
const athletes = ref([])

onMounted(async () => {
  const id = route.params.id
  const [coachRes, walletRes, athletesRes] = await Promise.all([
    apiClient.get(`/coaches/${id}`),
    apiClient.get(`/coaches/${id}/wallet`),
    apiClient.get('/athletes'),
  ])
  coach.value = coachRes.data
  wallet.value = walletRes.data
  athletes.value = athletesRes.data.filter((a) => a.coach_id === Number(id))
})

async function editCoach() {
  const updated = await openCoachForm(coach.value.id)
  if (updated) coach.value = updated
}
</script>
