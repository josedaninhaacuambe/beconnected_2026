<template>
  <div class="min-h-screen flex items-center justify-center px-4 bg-bc-dark">
    <div class="text-center max-w-md">
      <div class="text-8xl mb-6">🔒</div>
      <h1 class="text-bc-gold font-bold text-3xl mb-2">Acesso Negado</h1>
      <p class="text-bc-muted mb-2">Não tens permissão para aceder a esta página.</p>
      <p v-if="reason" class="text-bc-muted/70 text-sm mb-8">{{ reason }}</p>
      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <button @click="$router.back()" class="btn-ghost px-6 py-2.5 text-sm">← Voltar</button>
        <RouterLink to="/" class="btn-gold px-6 py-2.5 text-sm">Ir para a Página Inicial</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const reason = computed(() => {
  const needed = route.query.role
  if (needed === 'store_owner') return 'Esta área é exclusiva para donos de loja.'
  if (needed === 'admin') return 'Esta área é exclusiva para administradores.'
  if (needed === 'auth') return 'Precisas de iniciar sessão para continuar.'
  return ''
})
</script>
