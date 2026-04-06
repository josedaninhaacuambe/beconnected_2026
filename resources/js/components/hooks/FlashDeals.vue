<template>
  <section class="flash-deals-section py-10 px-4">
    <!-- Header -->
    <div class="container mx-auto">
      <div class="flash-header rounded-2xl p-5 mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <span class="text-4xl animate-pulse">⚡</span>
          <div>
            <h2 class="text-2xl font-black text-black tracking-tight">RELÂMPAGO</h2>
            <p class="text-orange-600 text-sm font-medium">Ofertas que acabam em breve — não percas!</p>
          </div>
        </div>
        <div class="flex items-center gap-2 bg-gray-100 rounded-xl px-4 py-2">
          <span class="text-orange-600 text-sm font-semibold">Acabam Em:</span>
          <span class="text-black font-black text-xl tabular-nums">{{ globalCountdown }}</span>
        </div>
      </div>

      <!-- Product grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
        <template v-if="isLoading">
          <div v-for="n in 8" :key="`flash-skel-${n}`" class="flash-card rounded-2xl overflow-hidden relative flex flex-col animate-pulse bg-slate-800">
            <div class="h-44 bg-slate-700"></div>
            <div class="p-4 flex-1 space-y-2">
              <div class="h-3 bg-slate-700 rounded"></div>
              <div class="h-3 bg-slate-700 rounded w-3/4"></div>
              <div class="h-4 bg-slate-700 rounded"></div>
              <div class="h-7 bg-slate-700 rounded"></div>
            </div>
          </div>
        </template>

        <template v-else-if="error">
          <div class="col-span-full bg-red-500/15 text-red-200 rounded-xl p-4 text-center">
            <p>{{ error }}</p>
            <button @click="retryFetchDeals" class="mt-3 px-4 py-2 bg-red-500 text-white rounded">Tentar novamente</button>
          </div>
        </template>

        <template v-else-if="deals.length === 0">
          <div class="col-span-full bg-bc-surface rounded-xl p-6 text-center text-bc-muted">Nenhuma oferta relâmpago disponível no momento.</div>
        </template>

        <template v-else>
          <div
            v-for="deal in deals"
            :key="deal.id"
            class="flash-card rounded-2xl overflow-hidden relative flex flex-col"
            :class="{ 'flash-expired': isExpired(deal) }"
          >
          <!-- % off badge -->
          <div class="absolute top-3 left-3 z-10">
            <span class="bg-red-600 text-white font-black text-sm px-2 py-1 rounded-lg shadow-lg">
              −{{ discountPct(deal) }}%
            </span>
          </div>

          <!-- Expired overlay -->
          <div v-if="isExpired(deal)" class="expired-overlay absolute inset-0 z-20 flex items-center justify-center rounded-2xl">
            <span class="text-white font-black text-xl bg-black/70 px-4 py-2 rounded-xl">EXPIRADO</span>
          </div>

          <!-- Product image -->
          <div class="h-44 bg-bc-surface relative overflow-hidden">
            <AppImg
              :src="deal.images?.length ? `/storage/${deal.images[0]}` : ''"
              :alt="deal.name"
              class="w-full h-full object-cover"
            />
          </div>

          <!-- Info -->
          <div class="p-4 flex flex-col flex-1">
            <p class="text-xs text-bc-muted mb-1 truncate">{{ deal.store?.name }}</p>
            <h3 class="text-bc-light font-semibold text-sm mb-3 line-clamp-2 leading-snug">{{ deal.name }}</h3>

            <!-- Prices -->
            <div class="mb-3">
              <span class="text-bc-gold font-black text-2xl">{{ fmt(deal.flash_price) }} MT</span>
              <span class="text-bc-muted text-sm line-through ml-2">{{ fmt(deal.price) }} MT</span>
            </div>

            <!-- Countdown per card -->
            <div class="flex items-center gap-1 mb-4">
              <span class="text-xs text-orange-400 font-semibold">⏱</span>
              <span class="text-orange-300 font-mono font-bold text-sm tabular-nums">{{ countdowns[deal.id] || '00:00:00' }}</span>
            </div>

            <!-- CTA -->
            <RouterLink
              :to="`/lojas/${deal.store?.slug}`"
              class="mt-auto block text-center bg-bc-gold text-bc-dark font-bold text-sm py-2 rounded-xl hover:brightness-110 transition"
            >
              Ver na Loja →
            </RouterLink>
          </div>
        </div>
        </template>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { trackEvent } from '@/utils/analytics.js'

const deals = ref([])
const countdowns = ref({})
const isLoading = ref(true)
const error = ref(null)
let intervalId = null

async function fetchDeals() {
  isLoading.value = true
  error.value = null

  try {
    const { data } = await axios.get('/products/flash')
    deals.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    deals.value = []
    error.value = 'Não foi possível carregar ofertas relâmpago. Tenta novamente.'
    trackEvent('hook_load_failed', { hook: 'FlashDeals', message: e.message || 'unknown' })
  } finally {
    isLoading.value = false
  }
}

function retryFetchDeals() {
  fetchDeals()
}

function isExpired(deal) {
  return new Date(deal.flash_until) <= new Date()
}

function discountPct(deal) {
  if (!deal.price || !deal.flash_price) return 0
  return Math.round(((deal.price - deal.flash_price) / deal.price) * 100)
}

function fmt(val) {
  if (val == null) return '0'
  return Number(val).toLocaleString('pt-MZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function pad(n) {
  return String(n).padStart(2, '0')
}

function buildCountdown(untilStr) {
  const diff = Math.max(0, new Date(untilStr) - new Date())
  const h = Math.floor(diff / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  return `${pad(h)}:${pad(m)}:${pad(s)}`
}

// Global countdown uses the earliest expiry
const globalCountdown = ref('--:--:--')
function updateGlobalCountdown() {
  if (!deals.value.length) { globalCountdown.value = '--:--:--'; return }
  const earliest = deals.value.reduce((a, b) =>
    new Date(a.flash_until) < new Date(b.flash_until) ? a : b
  )
  globalCountdown.value = buildCountdown(earliest.flash_until)
}

function tick() {
  deals.value.forEach(d => {
    countdowns.value[d.id] = buildCountdown(d.flash_until)
  })
  updateGlobalCountdown()
}

onMounted(async () => {
  await fetchDeals()
  tick()
  intervalId = setInterval(tick, 1000)
})

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId)
})
</script>

<style scoped>
.flash-deals-section {
  background: white;
}

.flash-header {
  background: white;
  border: 1px solid #e0e0e0;
  box-shadow: none;
}

.flash-card {
  background: white;
  border: 1px solid #e0e0e0;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.flash-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.flash-card.flash-expired {
  opacity: 0.45;
  filter: grayscale(0.6);
}

.expired-overlay {
  background: rgba(0, 0, 0, 0.55);
}
</style>
