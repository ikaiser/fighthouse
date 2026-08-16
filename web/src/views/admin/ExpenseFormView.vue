<template>
  <div class="expense-form">
    <h1>Nuova spesa</h1>
    <form @submit.prevent="submit">
      <label>Importo (€)</label>
      <input v-model.number="importo" type="number" step="0.01" min="0.01" required />

      <label>Data</label>
      <input v-model="dataSpesa" type="date" required />

      <label>Note</label>
      <input v-model="note" placeholder="opzionale" />

      <label v-if="maxRedeemable > 0">
        <input type="checkbox" v-model="useWallet" />
        Usa {{ maxRedeemable }}€ di wallet accumulato
      </label>
      <p v-else class="hint">Nessun blocco da 25€ disponibile per lo scarico wallet al momento.</p>

      <button type="submit">Registra spesa</button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiClient } from '../../api/client'

const route = useRoute()
const router = useRouter()
const userId = route.query.user_id

const importo = ref(null)
const dataSpesa = ref(new Date().toISOString().slice(0, 10))
const note = ref('')
const maxRedeemable = ref(0)
const useWallet = ref(false)
const error = ref('')

async function refreshMax() {
  const { data } = await apiClient.get(`/users/${userId}/wallet`)
  maxRedeemable.value = data.max_redeemable
}

async function submit() {
  error.value = ''
  try {
    await apiClient.post('/expenses', {
      user_id: userId,
      importo: importo.value,
      data_spesa: dataSpesa.value,
      note: note.value || null,
      wallet_usato: useWallet.value ? maxRedeemable.value : 0,
    })
    router.push(`/admin/users/${userId}`)
  } catch (e) {
    error.value = e.response?.data?.error || 'errore durante il salvataggio'
  }
}

onMounted(refreshMax)
</script>
