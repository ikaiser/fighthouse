<template>
  <AdminLayout>
    <h1>{{ isEdit ? 'Modifica utente' : 'Nuovo utente' }}</h1>

    <div class="card" style="max-width: 480px">
      <form @submit.prevent="submit">
        <div class="field">
          <label for="nome">Nome</label>
          <input id="nome" v-model="form.nome" type="text" required />
        </div>
        <div class="field">
          <label for="cognome">Cognome</label>
          <input id="cognome" v-model="form.cognome" type="text" required />
        </div>
        <div class="field">
          <label for="telefono">Telefono</label>
          <input id="telefono" v-model="form.telefono" type="tel" required />
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" v-model="form.email" type="email" required />
        </div>
        <div class="field">
          <label for="data_nascita">Data di nascita</label>
          <input id="data_nascita" v-model="form.data_nascita" type="date" required />
        </div>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          {{ isEdit ? 'Salva modifiche' : 'Aggiungi utente' }}
        </button>
        <router-link
          v-if="isEdit"
          :to="`/admin/users/${route.params.id}`"
          class="btn btn-secondary"
          style="margin-left: var(--space-3)"
          >Annulla</router-link
        >
        <p v-if="error" class="error">{{ error }}</p>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiClient } from '../../api/client'
import AdminLayout from '../../components/AdminLayout.vue'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => !!route.params.id)

const form = reactive({ nome: '', cognome: '', telefono: '', email: '', data_nascita: '' })
const saving = ref(false)
const error = ref('')

async function loadExisting() {
  const { data } = await apiClient.get(`/users/${route.params.id}`)
  form.nome = data.nome
  form.cognome = data.cognome
  form.telefono = data.telefono
  form.email = data.email
  form.data_nascita = data.data_nascita
}

async function submit() {
  error.value = ''
  saving.value = true
  try {
    if (isEdit.value) {
      await apiClient.put(`/users/${route.params.id}`, form)
      router.push(`/admin/users/${route.params.id}`)
    } else {
      const { data } = await apiClient.post('/users', form)
      router.push(`/admin/users/${data.id}`)
    }
  } catch (e) {
    error.value = e.response?.data?.error || 'errore durante il salvataggio'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (isEdit.value) loadExisting()
})
</script>
