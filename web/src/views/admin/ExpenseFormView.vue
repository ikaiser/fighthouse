<template>
  <AdminLayout>
    <h1>Nuova spesa</h1>

    <div class="card" style="max-width: 480px">
      <form @submit.prevent="submit">
        <div class="field">
          <label for="importo">Importo (€)</label>
          <input id="importo" v-model.number="importo" type="number" step="0.01" min="0.01" required />
        </div>

        <div class="field">
          <label for="data_spesa">Data</label>
          <input id="data_spesa" v-model="dataSpesa" type="date" required />
        </div>

        <div class="field">
          <label for="note">Note</label>
          <input id="note" v-model="note" type="text" placeholder="opzionale" />
        </div>

        <div v-if="maxRedeemable > 0" class="field field-checkbox">
          <input id="use_wallet" type="checkbox" v-model="useWallet" />
          <label for="use_wallet" style="margin: 0; text-transform: none; font-size: 14px; color: var(--text)">
            Usa <span class="amount amount-positive">{{ maxRedeemable }}€</span> di wallet accumulato
          </label>
        </div>
        <p v-else class="hint">Nessun blocco da 25€ disponibile per lo scarico wallet al momento.</p>

        <button type="submit" class="btn btn-primary">Registra spesa</button>
        <p v-if="error" class="error">{{ error }}</p>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiClient } from '../../api/client'
import AdminLayout from '../../components/AdminLayout.vue'

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
