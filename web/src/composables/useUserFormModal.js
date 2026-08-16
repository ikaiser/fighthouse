import { reactive } from 'vue'

const state = reactive({
  visible: false,
  userId: null,
  resolve: null,
})

// userId null = crea un nuovo utente, altrimenti modifica quello esistente.
// Risolve con l'utente salvato, o null se l'operazione è stata annullata.
export function openUserForm(userId = null) {
  state.userId = userId
  state.visible = true
  return new Promise((resolve) => {
    state.resolve = resolve
  })
}

export function useUserFormState() {
  return state
}

export function resolveUserForm(user) {
  state.visible = false
  state.resolve?.(user ?? null)
  state.resolve = null
}
