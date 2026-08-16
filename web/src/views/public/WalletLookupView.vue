<template>
  <div class="wallet-lookup">
    <h1>Il tuo wallet</h1>
    <form @submit.prevent="lookup">
      <input v-model="telefono" placeholder="Numero di telefono" required />
      <button type="submit">Vedi saldo</button>
    </form>

    <p v-if="error" class="error">{{ error }}</p>

    <div v-if="result">
      <h2>Ciao {{ result.nome }}!</h2>
      <p class="balance">Saldo wallet: <strong>{{ result.wallet_balance }}€</strong></p>
      <h3>Storico movimenti</h3>
      <ul>
        <li v-for="(t, i) in result.transactions" :key="i">
          {{ t.created_at }} — {{ t.tipo }}: {{ t.importo }}€
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { apiClient } from '../../api/client'

const telefono = ref('')
const result = ref(null)
const error = ref('')

async function lookup() {
  error.value = ''
  result.value = null
  try {
    const { data } = await apiClient.post('/wallet/lookup', { telefono: telefono.value })
    result.value = data
  } catch (e) {
    error.value = e.response?.data?.error || 'numero non trovato'
  }
}
</script>
