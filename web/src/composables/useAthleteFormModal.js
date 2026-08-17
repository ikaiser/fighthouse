import { reactive } from 'vue'

const state = reactive({
  visible: false,
  athleteId: null,
  resolve: null,
})

// athleteId null = crea un nuovo atleta, altrimenti modifica quello esistente.
// Risolve con l'atleta salvato, o null se l'operazione è stata annullata.
export function openAthleteForm(athleteId = null) {
  state.athleteId = athleteId
  state.visible = true
  return new Promise((resolve) => {
    state.resolve = resolve
  })
}

export function useAthleteFormState() {
  return state
}

export function resolveAthleteForm(athlete) {
  state.visible = false
  state.resolve?.(athlete ?? null)
  state.resolve = null
}
