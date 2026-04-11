<template>
  <div class="min-h-screen flex items-center justify-center px-4 bg-bc-dark">
    <div class="w-full max-w-lg">
      <div class="card-african p-8 space-y-6">
        <div class="text-center">
          <h1 class="text-bc-gold text-3xl font-bold mb-2">Escolher acesso</h1>
          <p class="text-bc-muted">A tua conta está registada como funcionário do POS desta loja.</p>
        </div>

        <div class="rounded-3xl border border-bc-gold/20 bg-bc-dark p-6 space-y-4">
          <p class="text-bc-light text-sm">Loja:</p>
          <h2 class="text-black text-xl font-semibold">{{ storeName }}</h2>
          <p class="text-bc-muted text-sm">Podes usar o Beconnected como cliente normal ou entrar no painel POS desta loja.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <button @click="enterPos" class="btn-ghost w-full py-3 text-sm">
            Entrar no POS
          </button>
          <button @click="enterCustomer" class="btn-secondary w-full py-3 text-sm">
            Continuar como cliente
          </button>
        </div>

        <p class="text-bc-muted text-xs text-center">
          Podes sempre voltar ao POS mais tarde, enquanto o teu utilizador mantiver acesso de funcionário.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const storeName = computed(() => {
  return authStore.user?.pos_employee?.store?.name
    || authStore.user?.pos_employee?.store?.slug
    || 'a loja'
})

onMounted(() => {
  if (!authStore.user?.pos_employee) {
    router.replace(route.query.redirect || '/')
  }
})

function enterPos() {
  router.push('/pos')
}

function enterCustomer() {
  router.push(route.query.redirect || '/')
}
</script>
