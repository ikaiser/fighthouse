import { reactive } from 'vue'

const state = reactive({
  visible: false,
  title: '',
  message: '',
  confirmLabel: 'Conferma',
  cancelLabel: 'Annulla',
  resolve: null,
})

export function confirmDialog({ title, message, confirmLabel = 'Conferma', cancelLabel = 'Annulla' }) {
  state.title = title
  state.message = message
  state.confirmLabel = confirmLabel
  state.cancelLabel = cancelLabel
  state.visible = true
  return new Promise((resolve) => {
    state.resolve = resolve
  })
}

export function useConfirmState() {
  return state
}

export function resolveConfirm(value) {
  state.visible = false
  state.resolve?.(value)
  state.resolve = null
}
