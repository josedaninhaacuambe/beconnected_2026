<template>
  <div class="min-h-screen flex flex-col" style="background:#F4F6F8;">
    <!-- Barra superior POS -->
    <header class="flex items-center justify-between px-4 py-2.5 shadow-sm flex-shrink-0" style="background:#1C2B3C;">
      <!-- Logo + Loja -->
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm" style="background:#F07820; color:white;">BC</div>
        <div>
          <p class="text-white font-bold text-sm leading-none">{{ storeName }}</p>
          <p class="text-white/50 text-xs">POS · {{ roleLabel }}</p>
        </div>
      </div>

      <!-- Nav tabs -->
      <nav class="hidden sm:flex items-center gap-1">
        <RouterLink
          v-for="tab in visibleTabs" :key="tab.to"
          :to="tab.to"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition"
          :class="isActive(tab.to)
            ? 'text-white'
            : 'text-white/50 hover:text-white/80 hover:bg-white/5'"
          :style="isActive(tab.to) ? 'background:#F07820' : ''"
        >
          <span>{{ tab.icon }}</span> {{ tab.label }}
        </RouterLink>
      </nav>

      <!-- Direita: online/offline + user -->
      <div class="flex items-center gap-3">
        <!-- Indicador offline + pending -->
        <div class="flex items-center gap-1.5">
          <span
            class="w-2 h-2 rounded-full"
            :class="isOnline ? 'bg-green-400' : 'bg-red-400'"
          ></span>
          <span class="text-white/60 text-xs hidden sm:block">{{ isOnline ? 'Online' : 'Offline' }}</span>
          <span v-if="pendingCount > 0" class="bg-yellow-400 text-black text-[10px] font-bold px-1.5 py-0.5 rounded-full">
            {{ pendingCount }} pendente{{ pendingCount > 1 ? 's' : '' }}
          </span>
        </div>

        <!-- Sync manual -->
        <button
          v-if="pendingCount > 0 && isOnline"
          @click="trySyncNow"
          :disabled="syncing"
          class="text-xs text-white/70 hover:text-white border border-white/20 rounded-lg px-2 py-1 transition"
        >
          {{ syncing ? '↻' : '↑' }} Sync
        </button>

        <!-- User -->
        <div class="flex items-center gap-2 cursor-pointer" @click="showMenu = !showMenu" style="position:relative">
          <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center text-white text-xs font-bold">
            {{ initial }}
          </div>
          <div v-if="showMenu" class="absolute right-0 top-9 bg-white rounded-xl shadow-xl border border-gray-100 w-40 z-50 py-1">
            <button @click="goHome" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">🏠 Ir para loja</button>
            <button @click="logout" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Sair</button>
          </div>
        </div>
      </div>
    </header>

    <!-- Mobile nav -->
    <nav class="sm:hidden flex border-b" style="background:#1C2B3C;">
      <RouterLink
        v-for="tab in visibleTabs" :key="tab.to"
        :to="tab.to"
        class="flex-1 flex flex-col items-center py-2 text-[10px] font-semibold transition gap-0.5"
        :class="isActive(tab.to) ? 'text-white' : 'text-white/40'"
      >
        <span class="text-base">{{ tab.icon }}</span>
        {{ tab.label }}
      </RouterLink>
    </nav>

    <!-- Mensagem sync -->
    <div v-if="syncMessage" class="text-center text-xs py-1.5 font-semibold" style="background:#F07820; color:white;">
      {{ syncMessage }}
    </div>

    <!-- Conteúdo -->
    <main class="flex-1 overflow-hidden">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useOfflinePos } from '@/composables/useOfflinePos'

const auth    = useAuthStore()
const route   = useRoute()
const router  = useRouter()
const showMenu = ref(false)

const { isOnline, pendingCount, syncing, syncMessage, trySyncNow } = useOfflinePos()

const storeName = computed(() => auth.user?.store?.name ?? auth.user?.name ?? 'Loja')
const initial   = computed(() => (auth.user?.name ?? 'U')[0].toUpperCase())

// Papel do utilizador no POS
const posRole = computed(() => {
  if (auth.user?.role === 'store_owner') return 'owner'
  return auth.user?.pos_role ?? 'cashier'
})

const roleLabel = computed(() => ({
  owner: 'Proprietário', manager: 'Gerente',
  cashier: 'Caixa', stock_keeper: 'Gestor de Stock', viewer: 'Visualizador'
}[posRole.value] ?? 'Funcionário'))

const allTabs = [
  { to: '/pos/terminal',   icon: '🛒', label: 'Venda',    roles: ['owner','manager','cashier'] },
  { to: '/pos/stock',      icon: '📦', label: 'Stock',    roles: ['owner','manager','stock_keeper'] },
  { to: '/pos/reports',    icon: '📊', label: 'Relatórios', roles: ['owner','manager'] },
  { to: '/pos/employees',  icon: '👥', label: 'Equipa',   roles: ['owner'] },
]

const visibleTabs = computed(() => allTabs.filter(t => t.roles.includes(posRole.value)))

function isActive(path) {
  return route.path.startsWith(path)
}

function goHome()  { router.push('/'); showMenu.value = false }
async function logout() { await auth.logout(); router.push('/') }
</script>
