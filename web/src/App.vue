<template>
  <router-view />
  <ConfirmDialog />
  <AthleteFormModal />
  <CoachFormModal />
</template>

<script setup>
import ConfirmDialog from './components/ConfirmDialog.vue'
import AthleteFormModal from './components/AthleteFormModal.vue'
import CoachFormModal from './components/CoachFormModal.vue'
</script>
