import { reactive } from 'vue'

const state = reactive({
  visible: false,
  coachId: null,
  resolve: null,
})

// coachId null = crea un nuovo coach, altrimenti modifica quello esistente.
// Risolve con il coach salvato, o null se l'operazione è stata annullata.
export function openCoachForm(coachId = null) {
  state.coachId = coachId
  state.visible = true
  return new Promise((resolve) => {
    state.resolve = resolve
  })
}

export function useCoachFormState() {
  return state
}

export function resolveCoachForm(coach) {
  state.visible = false
  state.resolve?.(coach ?? null)
  state.resolve = null
}
