<template>
  <div class="flex flex-col min-h-screen">
    <!-- Header com padrão africano -->
    <header class="sticky top-0 z-50 bg-bc-navy border-b-2 border-bc-gold shadow-lg">
      <!-- Faixa decorativa africana -->
      <div class="african-pattern-bar h-1.5"></div>

      <div class="container mx-auto px-4 py-3">
        <div class="flex items-center justify-between gap-4">
          <!-- Logo -->
          <!-- Logo -->
          <RouterLink to="/" class="flex items-center gap-2 flex-shrink-0">
            <div class="w-10 h-10 bg-bc-gold rounded-lg flex items-center justify-center">
              <span class="text-white font-bold text-lg leading-none">BC</span>
            </div>
            <div class="hidden sm:block">
              <span class="text-white font-bold text-xl tracking-wide">BECONNECT</span>
              <p class="text-white/50 text-xs">Mercado Virtual</p>
            </div>
          </RouterLink>

          <!-- Barra de pesquisa -->
          <div class="flex-1 max-w-xl">
            <form @submit.prevent="goSearch" class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Pesquisar produtos, marcas, modelos..."
                class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-2 pr-12 text-white placeholder-white/40 text-sm focus:outline-none focus:border-bc-gold focus:bg-white/20"
              />
              <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-bc-gold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
              </button>
            </form>
          </div>

          <!-- Acções -->
          <div class="flex items-center gap-3">
            <!-- Queima de stock -->
            <RouterLink to="/queima-de-stock" class="hidden sm:flex items-center gap-1 text-xs font-bold text-bc-gold hover:text-orange-300 border border-bc-gold/50 px-3 py-1.5 rounded-lg transition hover:border-bc-gold">
              ⚡ Queima
            </RouterLink>

            <!-- Sino de notificações -->
            <NotificationBell v-if="authStore.isAuthenticated" />

            <!-- Carrinho -->
            <RouterLink to="/conta/carrinho" class="relative text-white/70 hover:text-bc-gold transition">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
              <span v-if="cartStore.totalItems > 0" class="absolute -top-2 -right-2 bg-bc-gold text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                {{ cartStore.totalItems }}
              </span>
            </RouterLink>

            <!-- User menu -->
            <div v-if="authStore.isAuthenticated" class="relative">
              <button @click="showMenu = !showMenu" class="flex items-center gap-2 text-white hover:text-bc-gold transition">
                <div class="w-8 h-8 bg-bc-gold rounded-full flex items-center justify-center">
                  <span class="text-white text-sm font-bold">{{ authStore.user?.name?.charAt(0) }}</span>
                </div>
              </button>
              <div v-if="showMenu" class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-xl transition-all z-50">
                <RouterLink @click="showMenu = false" to="/conta" class="block px-4 py-2 text-sm text-bc-light hover:text-bc-gold hover:bg-orange-50 rounded-t-xl">Minha Conta</RouterLink>
                <RouterLink @click="showMenu = false" to="/conta/pedidos" class="block px-4 py-2 text-sm text-bc-light hover:text-bc-gold hover:bg-orange-50">Pedidos</RouterLink>
                <RouterLink v-if="authStore.isPosEmployee || authStore.isStoreOwner" @click="showMenu = false" to="/pos" class="block px-4 py-2 text-sm text-bc-gold font-semibold hover:bg-orange-50">Acessar POS</RouterLink>
                <RouterLink v-if="authStore.isStoreOwner" @click="showMenu = false" to="/loja" class="block px-4 py-2 text-sm text-bc-gold font-semibold hover:bg-orange-50">Gerir Loja</RouterLink>
                <button @click="logout" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-50 rounded-b-xl">Sair</button>
              </div>
            </div>
            <div v-else class="flex gap-2">
              <RouterLink to="/login" class="text-white/80 hover:text-white text-sm font-medium transition px-2">Entrar</RouterLink>
              <RouterLink to="/registar" class="btn-gold text-sm px-4 py-2">Registar</RouterLink>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Conteúdo principal -->
    <main class="flex-1">
      <RouterView />
    </main>

    <!-- Footer -->
    <footer class="bg-bc-navy border-t border-white/10 mt-16">
      <div class="african-pattern-bar h-1"></div>
      <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-sm">
          <div>
            <h4 class="text-bc-gold font-semibold mb-3">Beconnect</h4>
            <p class="text-white/50">O maior mercado virtual de Moçambique. Compra de todo o país, entrega na tua porta.</p>
          </div>
          <div>
            <h4 class="text-bc-gold font-semibold mb-3">Comprador</h4>
            <nav class="space-y-1">
              <RouterLink to="/lojas" class="block text-white/50 hover:text-bc-gold">Ver Lojas</RouterLink>
              <RouterLink to="/pesquisa" class="block text-white/50 hover:text-bc-gold">Pesquisar</RouterLink>
              <RouterLink to="/queima-de-stock" class="block text-bc-gold hover:text-orange-300 font-medium">⚡ Queima de Stock</RouterLink>
              <RouterLink to="/conta/pedidos" class="block text-white/50 hover:text-bc-gold">Meus Pedidos</RouterLink>
            </nav>
          </div>
          <div>
            <h4 class="text-bc-gold font-semibold mb-3">Vendedor</h4>
            <nav class="space-y-1">
              <RouterLink to="/registar?role=store_owner" class="block text-white/50 hover:text-bc-gold">Criar Loja</RouterLink>
              <RouterLink to="/loja" class="block text-white/50 hover:text-bc-gold">Gerir Loja</RouterLink>
            </nav>
          </div>
          <div>
            <h4 class="text-bc-gold font-semibold mb-3">Suporte</h4>
            <p class="text-white/50">📞 +258 84 000 0000</p>
            <p class="text-white/50">✉ suporte@beconnect.co.mz</p>
          </div>
        </div>
        <div class="mt-6 pt-4 border-t border-white/10 text-center text-white/30 text-xs">
          © {{ new Date().getFullYear() }} Beconnect — Feito com ❤ em Moçambique
        </div>
      </div>
    </footer>

    <!-- Modais globais -->
    <LoginModal />
    <CartAddModal />

    <!-- Botão flutuante de Feedback / Reclamações -->
    <FeedbackButton />

    <!-- Bottom nav (mobile) -->
    <nav class="fixed bottom-0 left-0 right-0 bg-bc-gold border-t border-bc-dark shadow-lg flex md:hidden z-50">
      <RouterLink to="/" class="flex-1 flex flex-col items-center py-2 text-bc-dark hover:text-bc-navy text-xs gap-0.5">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
        Início
      </RouterLink>
      <RouterLink to="/pesquisa" class="flex-1 flex flex-col items-center py-2 text-bc-dark hover:text-bc-navy text-xs gap-0.5">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        Pesquisar
      </RouterLink>
      <RouterLink to="/conta/carrinho" class="flex-1 flex flex-col items-center py-2 text-bc-dark hover:text-bc-navy text-xs gap-0.5 relative">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span v-if="cartStore.totalItems > 0" class="absolute top-1 right-6 bg-bc-orange text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">{{ cartStore.totalItems }}</span>
        Carrinho
      </RouterLink>
      <RouterLink to="/conta" class="flex-1 flex flex-col items-center py-2 text-bc-dark hover:text-bc-navy text-xs gap-0.5">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Conta
      </RouterLink>
    </nav>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import { useCartStore } from '../../stores/cart.js'
import LoginModal from '../LoginModal.vue'
import CartAddModal from '../CartAddModal.vue'
import NotificationBell from '../NotificationBell.vue'
import FeedbackButton from '../FeedbackButton.vue'

const router = useRouter()
const authStore = useAuthStore()
const cartStore = useCartStore()
const searchQuery = ref('')
const showMenu = ref(false)

if (authStore.isAuthenticated) {
  cartStore.fetchCart()
}

async function logout() {
  showMenu.value = false
  await authStore.logout()
  router.push('/')
}

function goSearch() {
  if (searchQuery.value.trim()) {
    router.push({ name: 'search', query: { q: searchQuery.value } })
  }
}
</script>
