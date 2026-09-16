<template>
  <div v-if="state.visible" class="modal-backdrop" @click.self="cancel">
    <div class="modal-card card" style="max-width: 480px">
      <p class="modal-title">{{ state.coachId ? 'Modifica coach' : 'Nuovo coach' }}</p>
      <form @submit.prevent="submit">
        <div class="field">
          <label for="cf-nome">Nome</label>
          <input id="cf-nome" v-model="form.nome" type="text" required />
        </div>
        <div class="field">
          <label for="cf-cognome">Cognome</label>
          <input id="cf-cognome" v-model="form.cognome" type="text" required />
        </div>
        <div class="field">
          <label for="cf-telefono">Telefono</label>
          <input id="cf-telefono" v-model="form.telefono" type="tel" required />
        </div>
        <div class="field">
          <label for="cf-email">Email</label>
          <input id="cf-email" v-model="form.email" type="email" required />
        </div>
        <div class="field">
          <label for="cf-data-nascita">Data di nascita</label>
          <input id="cf-data-nascita" v-model="form.data_nascita" type="date" />
        </div>

        <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-5)">
          <button type="button" class="btn btn-secondary" @click="cancel">Annulla</button>
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ state.coachId ? 'Salva modifiche' : 'Aggiungi coach' }}
          </button>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { apiClient } from '../api/client'
import { resolveCoachForm, useCoachFormState } from '../composables/useCoachFormModal'

const state = useCoachFormState()
const form = reactive({ nome: '', cognome: '', telefono: '', email: '', data_nascita: '' })
const saving = ref(false)
const error = ref('')

watch(
  () => state.visible,
  async (visible) => {
    error.value = ''
    if (!visible) return
    if (state.coachId) {
      const { data } = await apiClient.get(`/coaches/${state.coachId}`)
      form.nome = data.nome
      form.cognome = data.cognome
      form.telefono = data.telefono
      form.email = data.email
      form.data_nascita = data.data_nascita || ''
    } else {
      form.nome = ''
      form.cognome = ''
      form.telefono = ''
      form.email = ''
      form.data_nascita = ''
    }
  },
)

function cancel() {
  resolveCoachForm(null)
}

async function submit() {
  error.value = ''
  saving.value = true
  try {
    const { data } = state.coachId
      ? await apiClient.put(`/coaches/${state.coachId}`, form)
      : await apiClient.post('/coaches', form)
    resolveCoachForm(data)
  } catch (e) {
    error.value = e.response?.data?.error || 'errore durante il salvataggio'
  } finally {
    saving.value = false
  }
}

function onKeydown(event) {
  if (event.key === 'Escape' && state.visible) cancel()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>
