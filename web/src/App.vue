<template>
  <router-view />
  <ConfirmDialog />
  <UserFormModal />
</template>

<script setup>
import ConfirmDialog from './components/ConfirmDialog.vue'
import UserFormModal from './components/UserFormModal.vue'
</script>
