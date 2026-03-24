<template>
  <div class="flex min-h-screen">
    <!-- Sidebar da loja -->
    <aside class="hidden md:flex flex-col w-64 bg-white border-r border-gray-200 shadow-sm fixed top-0 bottom-0 overflow-y-auto">
      <!-- Header da sidebar — fundo azul -->
      <div class="p-4 border-b border-white/10 bg-bc-navy">
        <RouterLink to="/" class="flex items-center gap-2 mb-1">
          <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
            <span class="text-white font-bold text-sm">BC</span>
          </div>
          <span class="text-white font-bold text-lg">BECONNECT</span>
        </RouterLink>
        <p class="text-blue-100 text-xs">Painel da Loja</p>
      </div>

      <nav class="flex-1 p-4 space-y-1">
        <RouterLink v-for="item in navItems" :key="item.to" :to="item.to" :class="['flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition', $route.path === item.to ? 'bg-bc-gold/10 text-bc-gold font-semibold' : 'text-gray-600 hover:text-bc-gold hover:bg-gray-100']">
          <span>{{ item.icon }}</span>
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="p-4 border-t border-gray-200">
        <RouterLink to="/" class="flex items-center gap-2 text-gray-500 hover:text-bc-gold text-sm">
          <span>🏠</span> Ir para o site
        </RouterLink>
      </div>
    </aside>

    <!-- Conteúdo -->
    <div class="flex-1 md:ml-64">
      <!-- Topbar mobile -->
      <header class="md:hidden sticky top-0 bg-bc-navy p-4 flex items-center justify-between z-40 border-b border-white/10">
        <RouterLink to="/" class="text-white font-bold">← Beconnect</RouterLink>
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-white text-xl">☰</button>
      </header>

      <!-- Menu mobile -->
      <div v-if="mobileMenuOpen" class="md:hidden fixed inset-0 bg-black/70 z-50" @click="mobileMenuOpen = false">
        <div class="bg-white w-64 h-full p-4 shadow-xl" @click.stop>
          <nav class="space-y-1">
            <RouterLink v-for="item in navItems" :key="item.to" :to="item.to" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-bc-gold hover:bg-gray-50 rounded-xl">
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
  { to: '/loja', icon: '📊', label: 'Dashboard' },
  { to: '/loja/produtos', icon: '🛍', label: 'Produtos' },
  { to: '/loja/pedidos', icon: '📦', label: 'Pedidos' },
  { to: '/loja/stock', icon: '📈', label: 'Stock' },
  { to: '/loja/stock/importar', icon: '📥', label: 'Importar Stock' },
  { to: '/loja/funcionarios', icon: '👥', label: 'Funcionários' },
  { to: '/loja/categorias', icon: '🗂', label: 'Secções' },
  { to: '/loja/queima-de-stock', icon: '⚡', label: 'Queima de Stock' },
  { to: '/loja/visibilidade', icon: '🚀', label: 'Visibilidade' },
  { to: '/loja/configuracoes', icon: '⚙', label: 'Configurações' },
  { to: '/pos', icon: '🛒', label: 'Ponto de Venda (POS)' },
]
</script>
