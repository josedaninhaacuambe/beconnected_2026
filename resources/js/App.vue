<template>
  <div id="beconnect-app" class="min-h-screen bg-bc-dark text-bc-light font-african">
    <RouterView />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from './stores/auth.js'

const authStore = useAuthStore()

onMounted(() => {
  authStore.initAuth()
})
</script>
