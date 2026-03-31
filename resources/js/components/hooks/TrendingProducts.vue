<template>
  <section v-if="isLoading || products.length > 0 || error" class="trending-section py-10 px-4">
    <div class="container mx-auto">
      <!-- Header -->
      <div class="flex items-center gap-3 mb-6">
        <span class="text-4xl">🔥</span>
        <div>
          <h2 class="text-2xl font-black text-white tracking-tight">EM CHAMAS</h2>
          <p class="text-orange-300 text-sm">O Que Moçambique Está a Comprar</p>
        </div>
      </div>

      <!-- Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 gap-4">
        <template v-if="isLoading">
          <div v-for="n in 8" :key="`trending-skel-${n}`" class="trending-card rounded-2xl overflow-hidden relative flex flex-col animate-pulse bg-slate-800">
            <div class="h-36 bg-slate-700"></div>
            <div class="p-3 flex flex-col flex-1 space-y-2">
              <div class="h-3 bg-slate-700 rounded"></div>
              <div class="h-3 bg-slate-700 rounded w-5/6"></div>
              <div class="h-3 bg-slate-700 rounded w-2/3"></div>
              <div class="mt-auto h-8 bg-slate-700 rounded"></div>
            </div>
          </div>
        </template>

        <template v-else-if="error">
          <div class="col-span-full bg-red-500/15 text-red-200 rounded-xl p-4 text-center">
            <p>{{ error }}</p>
            <button @click="retryTrending" class="mt-3 px-4 py-2 bg-red-500 text-white rounded">Tentar novamente</button>
          </div>
        </template>

        <template v-else-if="products.length === 0">
          <div class="col-span-full bg-bc-surface rounded-xl p-6 text-center text-bc-muted">Nenhum produto em destaque no momento.</div>
        </template>

        <template v-else>
          <div
            v-for="(product, index) in products"
            :key="product.id"
            class="trending-card rounded-2xl overflow-hidden relative flex flex-col"
          >
          <!-- Rank badge -->
          <div class="absolute top-2 left-2 z-10">
            <span class="rank-badge text-xs font-black px-2 py-0.5 rounded-full" :class="rankClass(index)">
              #{{ index + 1 }}
            </span>
          </div>

          <!-- Heat meter -->
          <div class="absolute top-2 right-2 z-10">
            <span class="text-base">{{ heatMeter(index) }}</span>
          </div>

          <!-- Image -->
          <div class="h-36 bg-bc-surface relative overflow-hidden">
            <AppImg
              :src="product.images?.length ? `/storage/${product.images[0]}` : ''"
              :alt="product.name"
              class="w-full h-full object-cover"
            />
          </div>

          <!-- Content -->
          <div class="p-3 flex flex-col flex-1">
            <p class="text-bc-muted text-xs truncate mb-0.5">{{ product.store?.name }}</p>
            <h3 class="text-bc-light font-semibold text-xs mb-2 line-clamp-2 leading-snug">{{ product.name }}</h3>

            <!-- Viewers -->
            <div class="flex items-center gap-1 mb-2">
              <span class="text-xs">👁</span>
              <span class="text-orange-300 text-xs font-medium">{{ viewerCount(product) }} a ver agora</span>
            </div>

            <!-- Price + sold -->
            <div class="mt-auto">
              <p class="text-bc-gold font-bold text-base">{{ fmt(product.price) }} MT</p>
              <div class="sold-badge mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full">
                <span class="text-xs">🛒</span>
                <span class="text-xs font-semibold text-orange-200">{{ product.total_sold }} vendidos</span>
              </div>
            </div>

            <RouterLink
              :to="`/lojas/${product.store?.slug}`"
              class="mt-3 block text-center border border-bc-gold/40 text-bc-gold font-semibold text-xs py-1.5 rounded-xl hover:bg-bc-gold hover:text-bc-dark transition"
            >
              Ver na Loja
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { trackEvent } from '@/utils/analytics.js'

const products = ref([])
const isLoading = ref(true)
const error = ref(null)
let minuteInterval = null
const currentHour = ref(new Date().getHours())

async function fetchTrending() {
  isLoading.value = true
  error.value = null

  try {
    const { data } = await axios.get('/products/trending')
    products.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    products.value = []
    error.value = 'Falha ao carregar produtos em destaque. Tenta novamente.'
    trackEvent('hook_load_failed', { hook: 'TrendingProducts', message: e.message || 'unknown' })
  } finally {
    isLoading.value = false
  }
}

function retryTrending() {
  fetchTrending()
}

// Deterministic viewer count: (product.id * hour_of_day) % 47 + 3
function viewerCount(product) {
  return (product.id * currentHour.value) % 47 + 3
}

// Heat meter: top 2 = 3 fires, mid 3 = 2 fires, rest = 1 fire
function heatMeter(index) {
  if (index < 2) return '🔥🔥🔥'
  if (index < 5) return '🔥🔥'
  return '🔥'
}

function rankClass(index) {
  if (index === 0) return 'bg-yellow-400 text-yellow-900'
  if (index === 1) return 'bg-gray-300 text-gray-800'
  if (index === 2) return 'bg-amber-600 text-white'
  return 'bg-bc-surface-2 text-bc-muted'
}

function fmt(val) {
  if (val == null) return '0'
  return Number(val).toLocaleString('pt-MZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

onMounted(async () => {
  await fetchTrending()
  minuteInterval = setInterval(() => {
    currentHour.value = new Date().getHours()
  }, 60000)
})

onUnmounted(() => {
  if (minuteInterval) clearInterval(minuteInterval)
})
</script>

<style scoped>
.trending-section {
  background: linear-gradient(180deg, #0f0a00 0%, #111111 100%);
}

.trending-card {
  background: linear-gradient(160deg, #1e1200 0%, #161616 100%);
  border: 1px solid rgba(230, 126, 34, 0.15);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.trending-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(230, 126, 34, 0.2);
  border-color: rgba(230, 126, 34, 0.4);
}

.sold-badge {
  background: rgba(255, 80, 0, 0.15);
  border: 1px solid rgba(255, 80, 0, 0.3);
}
</style>
