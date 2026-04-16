<template>
  <div class="pb-mobile">
    <!-- Hero Banner -->
    <div class="w-full">
      <picture>
        <!-- Mobile: imagem vertical -->
        <source media="(max-width: 767px)" :srcset="'/images/Queima-Mobile.png'" />
        <!-- Desktop: imagem horizontal -->
        <source media="(min-width: 768px)" :srcset="'/images/Queima-stock01.png'" />
        <img
          :src="'/images/Queima-stock01.png'"
          alt="Grande Queima de Stock"
          class="w-full h-auto block"
          fetchpriority="high"
        />
      </picture>
    </div>

    <!-- Contador de ofertas activas -->
    <div class="bg-bc-dark border-b border-red-500/20 px-4 py-5">
      <div class="flex justify-center gap-6 flex-wrap">
        <div class="bg-red-500/20 border border-red-500/40 rounded-xl px-5 py-3 text-center">
          <p class="text-3xl font-black text-white">{{ products.length }}</p>
          <p class="text-red-300 text-xs uppercase tracking-wide">Ofertas Activas</p>
        </div>
        <div class="bg-bc-gold/10 border border-bc-gold/30 rounded-xl px-5 py-3 text-center">
          <p class="text-3xl font-black text-bc-gold">{{ uniqueStores }}</p>
          <p class="text-bc-muted text-xs uppercase tracking-wide">Lojas a Participar</p>
        </div>
        <div v-if="bestDiscount > 0" class="bg-green-500/10 border border-green-500/30 rounded-xl px-5 py-3 text-center">
          <p class="text-3xl font-black text-green-400">−{{ bestDiscount }}%</p>
          <p class="text-bc-muted text-xs uppercase tracking-wide">Melhor Desconto</p>
        </div>
      </div>
    </div>

    <!-- Filtro por loja -->
    <div v-if="products.length > 0" class="sticky top-0 z-10 bg-bc-dark/90 backdrop-blur border-b border-bc-gold/10 px-4 py-3">
      <div class="container mx-auto flex items-center gap-3 overflow-x-auto">
        <button
          @click="filterStore = null"
          :class="['px-3 py-1.5 rounded-full text-xs whitespace-nowrap border transition', !filterStore ? 'bg-bc-gold text-bc-dark border-bc-gold font-bold' : 'border-bc-gold/30 text-bc-muted hover:border-bc-gold']"
        >Todas ({{ products.length }})</button>
        <button
          v-for="store in storeList"
          :key="store.id"
          @click="filterStore = store.id"
          :class="['px-3 py-1.5 rounded-full text-xs whitespace-nowrap border transition', filterStore === store.id ? 'bg-bc-gold text-bc-dark border-bc-gold font-bold' : 'border-bc-gold/30 text-bc-muted hover:border-bc-gold']"
        >{{ store.name }} ({{ store.count }})</button>
      </div>
    </div>

    <div class="container mx-auto px-4 py-8">

      <!-- Loading -->
      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <div v-for="i in 8" :key="i" class="card-african overflow-hidden">
          <div class="skeleton h-44"></div>
          <div class="p-3 space-y-2">
            <div class="skeleton h-4 w-3/4"></div>
            <div class="skeleton h-3 w-1/2"></div>
          </div>
        </div>
      </div>

      <!-- Sem ofertas -->
      <div v-else-if="filteredProducts.length === 0" class="text-center py-20">
        <p class="text-6xl mb-4">😴</p>
        <p class="text-bc-light font-bold text-xl mb-2">Sem queimas activas neste momento</p>
        <p class="text-bc-muted mb-6">As lojas lançam promoções durante o dia. Activa as notificações para não perderes!</p>
        <RouterLink to="/" class="btn-gold px-6 py-2.5 text-sm">Explorar Lojas</RouterLink>
      </div>

      <!-- Grelha de produtos em queima -->
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <RouterLink
          v-for="p in filteredProducts"
          :key="p.id"
          :to="`/lojas/${p.store.slug}/produtos/${p.slug}`"
          class="card-african overflow-hidden hover:border-red-500/60 transition group relative"
        >
          <!-- Badge desconto -->
          <div class="absolute top-2 left-2 z-10">
            <span class="bg-red-600 text-white font-black text-sm px-2 py-1 rounded-lg shadow-lg">
              −{{ discountPct(p) }}%
            </span>
          </div>

          <!-- Countdown -->
          <div class="absolute top-2 right-2 z-10">
            <span class="bg-black/70 text-red-300 text-[10px] font-mono px-2 py-1 rounded-lg">
              ⏱ {{ countdown(p.flash_until) }}
            </span>
          </div>

          <!-- Imagem -->
          <div class="h-44 bg-bc-surface-2 relative overflow-hidden">
            <AppImg
              :src="p.images?.[0] ? (p.images[0].startsWith('http') ? p.images[0] : `/storage/${p.images[0]}`) : ''"
              type="product"
              class="w-full h-full object-cover group-hover:scale-105 transition"
              :alt="p.name"
            />
          </div>

          <!-- Info -->
          <div class="p-3">
            <p class="text-bc-light font-medium text-sm line-clamp-2 mb-2">{{ p.name }}</p>
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <span class="text-bc-muted line-through text-xs">{{ formatMZN(p.price) }}</span>
              <span class="text-red-400 font-black text-base">{{ formatMZN(p.flash_price) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-bc-gold text-xs">{{ p.store.name }}</span>
              <span class="text-bc-muted text-xs">Stock: {{ p.stock?.quantity ?? 0 }}</span>
            </div>
            <div class="mt-2 text-center">
              <span class="bg-red-600/20 text-red-400 text-xs border border-red-500/30 rounded px-2 py-0.5">
                Ver oferta →
              </span>
            </div>
          </div>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const products    = ref([])
const loading     = ref(true)
const filterStore = ref(null)
const now         = ref(new Date())
let tickInterval  = null
let refreshInterval = null

const filteredProducts = computed(() =>
  filterStore.value
    ? products.value.filter(p => p.store?.id === filterStore.value)
    : products.value
)

const uniqueStores = computed(() => new Set(products.value.map(p => p.store?.id)).size)
const bestDiscount = computed(() => Math.max(0, ...products.value.map(p => discountPct(p))))

const storeList = computed(() => {
  const map = {}
  products.value.forEach(p => {
    if (!p.store) return
    if (!map[p.store.id]) map[p.store.id] = { id: p.store.id, name: p.store.name, count: 0 }
    map[p.store.id].count++
  })
  return Object.values(map)
})

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

function discountPct(p) {
  if (!p.price || !p.flash_price) return 0
  return Math.round(((p.price - p.flash_price) / p.price) * 100)
}

function countdown(until) {
  const diff = Math.max(0, new Date(until) - now.value)
  if (diff === 0) return 'Expirou'
  const h = Math.floor(diff / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  if (h > 0) return `${h}h ${String(m).padStart(2,'0')}m`
  return `${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`
}

async function load() {
  try {
    const { data } = await axios.get('/products/flash')
    products.value = Array.isArray(data) ? data : (data.data ?? [])
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
  tickInterval    = setInterval(() => { now.value = new Date() }, 1000)
  refreshInterval = setInterval(load, 60000) // actualizar a cada minuto
})

onUnmounted(() => {
  clearInterval(tickInterval)
  clearInterval(refreshInterval)
})
</script>
