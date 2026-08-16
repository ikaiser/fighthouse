<template>
  <div v-if="state.visible" class="modal-backdrop" @click.self="cancel">
    <div class="modal-card card">
      <p class="modal-title">{{ state.title }}</p>
      <p class="modal-message">{{ state.message }}</p>
      <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-5)">
        <button type="button" class="btn btn-secondary" @click="cancel">{{ state.cancelLabel }}</button>
        <button type="button" class="btn btn-danger" @click="confirm">
          {{ state.confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import { resolveConfirm, useConfirmState } from '../composables/useConfirm'

const state = useConfirmState()

function confirm() {
  resolveConfirm(true)
}

function cancel() {
  resolveConfirm(false)
}

function onKeydown(event) {
  if (event.key === 'Escape' && state.visible) cancel()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>
