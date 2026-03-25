<template>
  <div class="flex min-h-screen">
    <!-- Sidebar admin -->
    <aside class="hidden md:flex flex-col w-64 bg-white border-r border-gray-200 shadow-sm fixed top-0 bottom-0 overflow-y-auto">
      <div class="p-4 border-b border-white/10 bg-bc-navy">
        <RouterLink to="/" class="flex items-center gap-2 mb-1">
          <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
            <span class="text-white font-bold text-sm">BC</span>
          </div>
          <span class="text-white font-bold text-lg">ADMIN</span>
        </RouterLink>
        <div class="flex items-center justify-between mt-2">
          <p class="text-red-100 text-xs">Painel de Administração</p>
          <button @click="showAlertPanel = !showAlertPanel" class="relative p-1">
            <span class="text-white text-base">🔔</span>
            <span v-if="alertCount > 0" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">{{ alertCount }}</span>
          </button>
        </div>
        <!-- Alert dropdown -->
        <div v-if="showAlertPanel && alertCount > 0" class="mt-2 bg-bc-dark/95 border border-red-500/30 rounded-xl p-3 text-xs space-y-1">
          <p v-if="alerts.expiring_visibility > 0" class="text-yellow-300">⚠ {{ alerts.expiring_visibility }} plano(s) a expirar em 7 dias</p>
          <p v-if="alerts.unresolved_orders > 0" class="text-red-300">🚨 {{ alerts.unresolved_orders }} pedido(s) com reembolso pendente</p>
        </div>
      </div>

      <nav class="flex-1 p-4 space-y-1">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :class="['flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition',
            $route.path === item.to ? 'bg-red-50 text-red-600 font-semibold' : 'text-gray-600 hover:text-red-600 hover:bg-gray-100']"
        >
          <span>{{ item.icon }}</span>
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="p-4 border-t border-gray-200 space-y-2">
        <RouterLink to="/loja" class="flex items-center gap-2 text-gray-500 hover:text-bc-gold text-sm">
          <span>🏪</span> Painel da Loja
        </RouterLink>
        <RouterLink to="/" class="flex items-center gap-2 text-gray-500 hover:text-bc-gold text-sm">
          <span>🏠</span> Ir para o site
        </RouterLink>
      </div>
    </aside>

    <!-- Conteúdo -->
    <div class="flex-1 md:ml-64">
      <!-- Topbar mobile -->
      <header class="md:hidden sticky top-0 bg-bc-navy p-4 flex items-center justify-between z-40 border-b border-white/10">
        <RouterLink to="/" class="text-white font-bold">← Admin</RouterLink>
        <div class="flex items-center gap-3">
          <button @click="showAlertPanel = !showAlertPanel" class="relative p-1">
            <span class="text-white text-base">🔔</span>
            <span v-if="alertCount > 0" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">{{ alertCount }}</span>
          </button>
          <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-white text-xl">☰</button>
        </div>
      </header>

      <!-- Menu mobile -->
      <div v-if="mobileMenuOpen" class="md:hidden fixed inset-0 bg-black/50 z-50" @click="mobileMenuOpen = false">
        <div class="bg-white w-64 h-full p-4 shadow-xl" @click.stop>
          <nav class="space-y-1">
            <RouterLink
              v-for="item in navItems"
              :key="item.to"
              :to="item.to"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-xl"
            >
              <span>{{ item.icon }}</span> {{ item.label }}
            </RouterLink>
          </nav>
        </div>
      </div>

      <main class="p-0">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '../../stores/auth.js'
import { useAdminAlerts } from '../../composables/useAdminAlerts.js'

const mobileMenuOpen = ref(false)
const showAlertPanel = ref(false)

const auth = useAuthStore()
const { alertCount, alerts } = useAdminAlerts()

const navItems = computed(() => {
  const all = [
    { to: '/admin', icon: '📊', label: 'Visão Geral', fullAdminOnly: true },
    { to: '/admin/utilizadores', icon: '👥', label: 'Utilizadores', fullAdminOnly: true },
    { to: '/admin/lojas', icon: '🏪', label: 'Lojas', fullAdminOnly: true },
    { to: '/admin/colaboradores', icon: '🤝', label: 'Colaboradores', fullAdminOnly: true },
    { to: '/admin/visibilidade', icon: '📡', label: 'Visibilidade', permission: 'manage_visibility' },
    { to: '/admin/pedidos', icon: '📦', label: 'Pedidos', permission: 'manage_orders' },
    { to: '/admin/entregas', icon: '🚚', label: 'Entregas', permission: 'manage_deliveries' },
    { to: '/admin/comissoes', icon: '💰', label: 'Comissões', fullAdminOnly: true },
    { to: '/admin/feedbacks', icon: '💬', label: 'Feedbacks', fullAdminOnly: true },
  ]

  return all.filter(item => {
    if (auth.isFullAdmin) return true
    if (item.fullAdminOnly) return false
    if (item.permission) return auth.hasPermission(item.permission)
    return true
  })
})
</script>
