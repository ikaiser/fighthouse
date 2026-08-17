<template>
  <div v-if="state.visible" class="modal-backdrop" @click.self="cancel">
    <div class="modal-card card" style="max-width: 480px">
      <p class="modal-title">{{ state.athleteId ? 'Modifica atleta' : 'Nuovo atleta' }}</p>
      <form @submit.prevent="submit">
        <div class="field">
          <label for="af-nome">Nome</label>
          <input id="af-nome" v-model="form.nome" type="text" required />
        </div>
        <div class="field">
          <label for="af-cognome">Cognome</label>
          <input id="af-cognome" v-model="form.cognome" type="text" required />
        </div>
        <div class="field">
          <label for="af-telefono">Telefono</label>
          <input id="af-telefono" v-model="form.telefono" type="tel" required />
        </div>
        <div class="field">
          <label for="af-email">Email</label>
          <input id="af-email" v-model="form.email" type="email" required />
        </div>
        <div class="field">
          <label for="af-data-nascita">Data di nascita</label>
          <input id="af-data-nascita" v-model="form.data_nascita" type="date" required />
        </div>
        <div class="field">
          <label for="af-coach">Coach</label>
          <select id="af-coach" v-model="form.coach_id" required>
            <option value="" disabled>Seleziona un coach</option>
            <option v-for="c in coaches" :key="c.id" :value="c.id">{{ c.nome }} {{ c.cognome }}</option>
          </select>
          <p v-if="!coaches.length" class="hint">
            Nessun coach censito: creane uno prima da "Coach" nel menu.
          </p>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-5)">
          <button type="button" class="btn btn-secondary" @click="cancel">Annulla</button>
          <button type="submit" class="btn btn-primary" :disabled="saving || !coaches.length">
            {{ state.athleteId ? 'Salva modifiche' : 'Aggiungi atleta' }}
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
import { resolveAthleteForm, useAthleteFormState } from '../composables/useAthleteFormModal'

const state = useAthleteFormState()
const form = reactive({ nome: '', cognome: '', telefono: '', email: '', data_nascita: '', coach_id: '' })
const coaches = ref([])
const saving = ref(false)
const error = ref('')

watch(
  () => state.visible,
  async (visible) => {
    error.value = ''
    if (!visible) return

    const { data } = await apiClient.get('/coaches')
    coaches.value = data

    if (state.athleteId) {
      const { data: athlete } = await apiClient.get(`/athletes/${state.athleteId}`)
      form.nome = athlete.nome
      form.cognome = athlete.cognome
      form.telefono = athlete.telefono
      form.email = athlete.email
      form.data_nascita = athlete.data_nascita
      form.coach_id = athlete.coach_id
    } else {
      form.nome = ''
      form.cognome = ''
      form.telefono = ''
      form.email = ''
      form.data_nascita = ''
      form.coach_id = coaches.value[0]?.id ?? ''
    }
  },
)

function cancel() {
  resolveAthleteForm(null)
}

async function submit() {
  error.value = ''
  saving.value = true
  try {
    const { data } = state.athleteId
      ? await apiClient.put(`/athletes/${state.athleteId}`, form)
      : await apiClient.post('/athletes', form)
    resolveAthleteForm(data)
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
