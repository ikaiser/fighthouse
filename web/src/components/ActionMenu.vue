<template>
  <div class="action-menu-trigger" ref="triggerEl">
    <button type="button" class="btn btn-secondary btn-sm" @click.stop="toggle">{{ label }}</button>
    <Teleport to="body">
      <div v-if="open" ref="menuEl" class="action-menu-list" :style="menuStyle" @click="close">
        <slot />
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'

defineProps({ label: { type: String, default: 'Azioni' } })

const open = ref(false)
const triggerEl = ref(null)
const menuEl = ref(null)
const menuStyle = reactive({ position: 'fixed', top: '0px', left: '0px' })

function toggle() {
  if (open.value) {
    open.value = false
    return
  }
  const rect = triggerEl.value.getBoundingClientRect()
  menuStyle.top = `${rect.bottom + 8}px`
  menuStyle.left = `${Math.max(8, rect.right - 170)}px`
  open.value = true
}

function close() {
  open.value = false
}

function onDocClick(event) {
  if (!open.value) return
  const insideTrigger = triggerEl.value?.contains(event.target)
  const insideMenu = menuEl.value?.contains(event.target)
  if (!insideTrigger && !insideMenu) open.value = false
}

function onKeydown(event) {
  if (event.key === 'Escape') open.value = false
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
  window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  window.removeEventListener('keydown', onKeydown)
})
</script>
