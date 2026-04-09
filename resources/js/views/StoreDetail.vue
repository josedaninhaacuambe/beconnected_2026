<template>
  <div v-if="store" class="pb-mobile">
    <!-- Banner da loja -->
    <div class="relative h-48 bg-bc-surface overflow-hidden">
      <AppImg v-if="store.banner" :src="`/storage/${store.banner}`" class="w-full h-full object-cover" />
      <div v-else class="w-full h-full african-pattern-bar opacity-20"></div>
      <div class="absolute inset-0 bg-gradient-to-t from-bc-dark/80"></div>

      <div class="absolute bottom-4 left-4 flex items-center gap-3">
        <div class="w-16 h-16 bg-bc-dark rounded-xl border-2 border-bc-gold/30 flex items-center justify-center">
          <AppImg v-if="store.logo" :src="`/storage/${store.logo}`" class="w-full h-full rounded-xl object-cover" />
          <span v-else class="text-bc-gold text-xl font-bold">{{ store.name.charAt(0) }}</span>
        </div>
        <div>
          <h1 class="text-white font-bold text-xl">{{ store.name }}</h1>
          <div class="flex items-center gap-2 text-sm">
            <span class="text-yellow-400">★ {{ store.rating?.toFixed(1) }}</span>
            <span class="text-white/60">·</span>
            <span class="text-white/80">{{ store.category?.name }}</span>
            <span v-if="store.is_featured" class="badge-featured">DESTAQUE</span>
          </div>
        </div>
      </div>
    </div>

    <div class="container mx-auto px-4 py-6">
      <!-- Info + Scan & Go -->
      <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex flex-wrap gap-3 text-sm text-bc-muted">
          <span>📍 {{ store.city?.name }}, {{ store.province?.name }}</span>
          <span v-if="store.phone">📞 {{ store.phone }}</span>
          <span v-if="store.accepts_delivery">🚚 Entrega em ~{{ store.estimated_delivery_minutes }}min</span>
        </div>
        <!-- Botão Scan & Go -->
        <RouterLink :to="`/comprar-na-loja/${store.slug}`"
          class="flex items-center gap-2 px-4 py-2 rounded-xl bg-bc-gold text-white text-sm font-bold shadow hover:opacity-90 transition flex-shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2"/>
            <rect x="7" y="7" width="3" height="3" rx="0.5"/><rect x="14" y="7" width="3" height="3" rx="0.5"/><rect x="7" y="14" width="3" height="3" rx="0.5"/>
            <path d="M14 14h3v3"/>
          </svg>
          Scan &amp; Go
        </RouterLink>
      </div>

      <!-- Secções da loja (tabs) -->
      <div v-if="sections.length > 0" class="flex gap-2 overflow-x-auto pb-2 mb-5 scrollbar-hide">
        <button
          @click="activeSection = null; loadProducts()"
          :class="['flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition', activeSection === null ? 'bg-bc-gold text-bc-dark' : 'bg-bc-surface-2 text-bc-muted hover:text-bc-light']"
        >Todos</button>
        <button
          v-for="s in sections" :key="s.id"
          @click="activeSection = s.id; loadProducts()"
          :class="['flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition', activeSection === s.id ? 'bg-bc-gold text-bc-dark' : 'bg-bc-surface-2 text-bc-muted hover:text-bc-light']"
        >{{ s.icon }} {{ s.name }}</button>
      </div>

      <!-- Filtro de produtos -->
      <div class="flex gap-3 mb-6">
        <input v-model="search" @input="debouncedLoad" type="text" placeholder="Pesquisar nesta loja..." class="input-african flex-1" />
        <select v-model="sortBy" @change="loadProducts" class="select-african w-40">
          <option value="featured">Destaque</option>
          <option value="newest">Mais recentes</option>
          <option value="price_asc">Preço ↑</option>
          <option value="price_desc">Preço ↓</option>
          <option value="rating">Avaliação</option>
        </select>
      </div>

      <!-- Produtos COM PREÇO (dentro da loja) -->
      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <div v-for="i in 8" :key="i" class="card-african overflow-hidden">
          <div class="skeleton h-40"></div>
          <div class="p-3 space-y-2">
            <div class="skeleton h-4"></div>
            <div class="skeleton h-5 w-1/2"></div>
          </div>
        </div>
      </div>

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <div
          v-for="product in products"
          :key="product.id"
          class="card-african overflow-hidden hover:border-bc-gold/60 transition"
        >
          <RouterLink :to="`/lojas/${store.slug}/produtos/${product.slug}`">
            <div class="h-40 bg-bc-surface-2 overflow-hidden relative">
              <AppImg :src="product.images?.[0] ? (product.images[0].startsWith('http') ? product.images[0] : `/storage/${product.images[0]}`) : ''" class="w-full h-full object-cover" />
              <span v-if="!product.stock || product.stock.quantity === 0" class="absolute inset-0 bg-black/60 flex items-center justify-center text-red-400 font-bold text-xs">Esgotado</span>
            </div>
            <div class="p-3">
              <h3 class="text-bc-light text-sm font-medium line-clamp-2 mb-1">{{ product.name }}</h3>
              <p v-if="product.brand" class="text-bc-muted text-xs mb-1">{{ product.brand?.name }}</p>
              <div class="flex items-center justify-between">
                <span class="price-tag">{{ formatPrice(product.price) }}</span>
                <span v-if="product.compare_price" class="text-bc-muted text-xs line-through">{{ formatPrice(product.compare_price) }}</span>
              </div>
              <p class="text-bc-muted text-xs mt-1">★ {{ product.rating?.toFixed(1) }} ({{ product.total_reviews }})</p>
            </div>
          </RouterLink>
          <div class="px-3 pb-3">
            <button
              @click="addToCart(product)"
              :disabled="!product.stock || product.stock.quantity === 0"
              class="btn-green w-full py-2 text-sm"
            >
              🛒 Adicionar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div v-else class="flex flex-col items-center justify-center min-h-screen gap-6">
    <!-- Spinner animado -->
    <div class="relative w-20 h-20">
      <!-- Anel externo -->
      <div class="absolute inset-0 rounded-full border-4 border-bc-gold/10"></div>
      <!-- Anel a girar -->
      <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-bc-gold animate-spin"></div>
      <!-- Anel interior a girar ao contrário -->
      <div class="absolute inset-2 rounded-full border-4 border-transparent border-b-bc-gold/50" style="animation: spin 1.4s linear infinite reverse;"></div>
      <!-- Ícone central -->
      <div class="absolute inset-0 flex items-center justify-center">
        <span class="text-2xl">🏪</span>
      </div>
    </div>

    <!-- Texto -->
    <div class="text-center">
      <p class="text-bc-light font-semibold text-base">A carregar loja...</p>
      <p class="text-bc-muted text-xs mt-1">Por favor aguarde</p>
    </div>

    <!-- Barra de progresso animada -->
    <div class="w-48 h-1 bg-bc-surface-2 rounded-full overflow-hidden">
      <div class="h-full bg-gradient-to-r from-bc-gold to-bc-orange rounded-full" style="animation: loading-bar 1.5s ease-in-out infinite;"></div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../stores/auth.js'
import { useLoginModal } from '../composables/useLoginModal.js'
import { useCartModal } from '../composables/useCartModal.js'

const route = useRoute()
const authStore = useAuthStore()
const { open: openLoginModal } = useLoginModal()
const { open: openCartModal } = useCartModal()
const store = ref(null)
const products = ref([])
const sections = ref([])
const activeSection = ref(null)
const loading = ref(true)
const search = ref('')
const sortBy = ref('featured')

function formatPrice(v) {
  return new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' }).format(v || 0)
}

let searchTimeout = null
function debouncedLoad() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(loadProducts, 400)
}

async function loadProducts() {
  loading.value = true
  const { data } = await axios.get(`/stores/${route.params.slug}/products`, {
    params: { q: search.value, sort: sortBy.value, section_id: activeSection.value || undefined }
  })
  products.value = data.data
  loading.value = false
}

function addToCart(product) {
  if (!authStore.isAuthenticated) {
    openLoginModal({ afterLogin: () => openCartModal({ ...product, store: store.value }) })
    return
  }
  openCartModal({ ...product, store: store.value })
}

onMounted(async () => {
  const [storeRes, sectionsRes, productsRes] = await Promise.all([
    axios.get(`/stores/${route.params.slug}`),
    axios.get(`/stores/${route.params.slug}/sections`).catch(() => ({ data: [] })),
    axios.get(`/stores/${route.params.slug}/products`, { params: { sort: sortBy.value } }),
  ])
  store.value = storeRes.data
  sections.value = sectionsRes.data
  products.value = productsRes.data.data
  loading.value = false
})
</script>

<style scoped>
@keyframes loading-bar {
  0%   { width: 0%; margin-left: 0%; }
  50%  { width: 60%; margin-left: 20%; }
  100% { width: 0%; margin-left: 100%; }
}
</style>
