<template>
  <div class="min-h-screen flex items-center justify-center p-4" style="background:#0F1923;">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex w-14 h-14 rounded-2xl items-center justify-center font-black text-xl mb-3" style="background:#F07820; color:white;">BC</div>
        <h1 class="text-white font-black text-2xl">Escolher Loja</h1>
        <p class="text-white/50 text-sm mt-1">Tens {{ stores.length }} lojas. Qual pretendes gerir?</p>
      </div>

      <!-- Lista de lojas -->
      <div class="space-y-3 mb-6">
        <button
          v-for="store in stores"
          :key="store.id"
          @click="select(store)"
          class="w-full flex items-center gap-4 p-4 rounded-2xl border-2 transition text-left"
          :class="selected?.id === store.id
            ? 'border-bc-gold bg-bc-gold/10'
            : 'border-white/10 bg-white/5 hover:border-white/30'"
        >
          <!-- Logo da loja -->
          <div class="w-12 h-12 rounded-xl overflow-hidden flex-shrink-0 bg-white/10 flex items-center justify-center">
            <AppImg
              v-if="store.logo"
              :src="`/storage/${store.logo}`"
              type="store"
              class="w-12 h-12 object-cover"
            />
            <span v-else class="text-white/40 text-xl">🏪</span>
          </div>

          <div class="flex-1 min-w-0">
            <p class="text-white font-bold truncate">{{ store.name }}</p>
            <span
              class="text-xs px-2 py-0.5 rounded-full"
              :class="store.status === 'active' ? 'bg-green-900 text-green-400' : 'bg-yellow-900 text-yellow-400'"
            >
              {{ store.status === 'active' ? '✓ Activa' : '⏳ Pendente' }}
            </span>
          </div>

          <div v-if="selected?.id === store.id" class="text-bc-gold text-xl flex-shrink-0">✓</div>
        </button>
      </div>

      <!-- Botão confirmar -->
      <button
        @click="confirm"
        :disabled="!selected"
        class="w-full py-3.5 rounded-2xl font-black text-white text-base transition disabled:opacity-40"
        style="background:#F07820;"
      >
        Entrar na loja
      </button>

      <!-- Criar nova loja -->
      <div class="text-center mt-4">
        <RouterLink to="/loja/nova" class="text-white/40 text-sm hover:text-bc-gold transition">
          + Registar nova loja
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

const stores   = computed(() => auth.allStores)
const selected = ref(stores.value[0] ?? null)

function select(store) {
  selected.value = store
}

function confirm() {
  if (!selected.value) return
  auth.setActiveStore(selected.value)
  router.push('/loja')
}
</script>
