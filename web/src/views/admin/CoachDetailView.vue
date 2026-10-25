<template>
  <AdminLayout v-if="coach">
    <div
      class="page-header"
      style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4)"
    >
      <h1>{{ coach.nome }} {{ coach.cognome }}</h1>
      <button type="button" class="btn btn-secondary" @click="editCoach">Modifica</button>
    </div>
    <p class="hint">
      Telefono: {{ coach.telefono }} — Email: {{ coach.email }}
      <template v-if="coach.data_nascita"> — Nato il: {{ coach.data_nascita }}</template>
    </p>

    <div class="balance-hero" style="margin: var(--space-5) 0">
      <p class="balance-label">Saldo wallet</p>
      <p class="balance-value">{{ wallet.wallet_balance }}€</p>
    </div>

    <h2>Accredita wallet</h2>
    <div class="card">
      <form style="display: flex; align-items: flex-end; gap: var(--space-3); flex-wrap: wrap" @submit.prevent="creditWallet">
        <div class="field" style="margin: 0">
          <label for="cw-importo">Importo spesa (€)</label>
          <input id="cw-importo" v-model="creditImporto" type="number" min="0.01" step="0.01" required />
        </div>
        <button type="submit" class="btn btn-primary" :disabled="crediting">
          Accredita 5% ({{ creditPreview }}€)
        </button>
        <p v-if="creditError" class="error" style="margin: 0">{{ creditError }}</p>
      </form>
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
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { apiClient } from '../../api/client'
import AdminLayout from '../../components/AdminLayout.vue'
import { formatDateTime } from '../../utils/date'
import { openCoachForm } from '../../composables/useCoachFormModal'

const route = useRoute()
const coach = ref(null)
const wallet = ref({ wallet_balance: 0, transactions: [] })
const athletes = ref([])
const creditImporto = ref('')
const crediting = ref(false)
const creditError = ref('')

const creditPreview = computed(() => {
  const importo = Number(creditImporto.value)
  return importo > 0 ? (importo * 0.05).toFixed(2) : '0.00'
})

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

async function creditWallet() {
  creditError.value = ''
  crediting.value = true
  try {
    const { data } = await apiClient.post(`/coaches/${coach.value.id}/wallet/credit`, {
      importo: Number(creditImporto.value),
    })
    wallet.value = data
    creditImporto.value = ''
  } catch (e) {
    creditError.value = e.response?.data?.error || 'errore durante l\'accredito'
  } finally {
    crediting.value = false
  }
}
</script>
