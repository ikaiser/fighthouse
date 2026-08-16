<template>
  <div class="screen-center">
    <div class="container-narrow" style="width: 100%; padding: var(--space-5) 0">
      <p class="brand-mark" style="text-align: center; margin-bottom: var(--space-5)">
        FIGHT <span>HOUSE</span>
      </p>

      <div class="card">
        <h1 style="font-size: 24px">Il tuo wallet</h1>
        <form @submit.prevent="lookup">
          <div class="field">
            <label for="telefono">Numero di telefono</label>
            <input id="telefono" v-model="telefono" type="tel" required autocomplete="tel" />
          </div>
          <button type="submit" class="btn btn-primary btn-block">Vedi saldo</button>
        </form>
        <p v-if="error" class="error">{{ error }}</p>
      </div>

      <div v-if="result" style="margin-top: var(--space-5)">
        <div class="balance-hero">
          <p class="balance-label">Ciao {{ result.nome }}, il tuo saldo è</p>
          <p class="balance-value">{{ result.wallet_balance }}€</p>
        </div>

        <div class="card" style="margin-top: var(--space-5)">
          <h2 style="margin-top: 0">Storico movimenti</h2>
          <ul>
            <li v-for="(t, i) in result.transactions" :key="i" class="list-item">
              <span class="hint">{{ formatDateTime(t.created_at) }}</span> — {{ t.tipo }}
              <span
                class="amount"
                :class="t.importo >= 0 ? 'amount-positive' : 'amount-negative'"
                style="float: right"
              >
                {{ t.importo >= 0 ? '+' : '' }}{{ t.importo }}€
              </span>
            </li>
            <li v-if="!result.transactions.length" class="list-item hint">Nessun movimento.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { apiClient } from '../../api/client'
import { formatDateTime } from '../../utils/date'

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
