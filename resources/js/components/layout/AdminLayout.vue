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
        <p class="text-red-100 text-xs">Painel de Administração</p>
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
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-white text-xl">☰</button>
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
import { ref } from 'vue'

const mobileMenuOpen = ref(false)

const navItems = [
  { to: '/admin', icon: '📊', label: 'Visão Geral' },
  { to: '/admin/utilizadores', icon: '👥', label: 'Utilizadores' },
  { to: '/admin/lojas', icon: '🏪', label: 'Lojas' },
  { to: '/admin/comissoes', icon: '💰', label: 'Comissões' },
  { to: '/admin/feedbacks', icon: '💬', label: 'Feedbacks' },
]
</script>
