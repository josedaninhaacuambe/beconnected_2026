<template>
  <section v-if="drops.length > 0" class="daily-drop-section py-10 px-4">
    <div class="container mx-auto">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
          <span class="text-4xl">🌅</span>
          <div>
            <h2 class="text-2xl font-black text-white tracking-tight">DESCOBERTA DO DIA</h2>
            <p class="text-amber-300 text-sm">Renova a Meia-Noite</p>
          </div>
        </div>
        <div class="flex items-center gap-2 midnight-badge px-4 py-2 rounded-xl">
          <span class="text-xs text-amber-300 font-semibold">🕛 Novo às 00:00 em</span>
          <span class="text-white font-black text-lg tabular-nums">{{ midnightCountdown }}</span>
        </div>
      </div>

      <!-- Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div
          v-for="(product, index) in drops"
          :key="product.id"
          class="drop-card rounded-2xl overflow-hidden relative flex flex-col"
          :style="{ animationDelay: `${index * 100}ms` }"
        >
          <!-- "Novo" badge -->
          <div class="absolute top-2 left-2 z-10">
            <span class="new-badge text-xs font-bold px-2 py-0.5 rounded-full">Novo às 00:00</span>
          </div>

          <!-- Image -->
          <div class="h-32 bg-bc-surface relative overflow-hidden">
            <img
              v-if="product.images && product.images.length"
              :src="`/storage/${product.images[0]}`"
              :alt="product.name"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-4xl">🌅</div>
          </div>

          <!-- Content -->
          <div class="p-3 flex flex-col flex-1">
            <p class="text-bc-muted text-xs truncate mb-0.5">{{ product.store?.name }}</p>
            <h3 class="text-bc-light font-semibold text-xs mb-2 line-clamp-2 leading-snug">{{ product.name }}</h3>
            <p class="text-bc-gold font-bold text-sm mt-auto">{{ fmt(product.price) }} MT</p>

            <RouterLink
              :to="`/lojas/${product.store?.slug}`"
              class="mt-2 block text-center border border-amber-500/40 text-amber-300 font-semibold text-xs py-1.5 rounded-xl hover:bg-amber-500/20 transition"
            >
              Descobrir
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

const drops = ref([])
const midnightCountdown = ref('--:--:--')
let intervalId = null

// Deterministic seeded shuffle using today's date string as entropy
function seededShuffle(arr, seed) {
  // Simple LCG hash of the seed string
  let hash = 0
  for (let i = 0; i < seed.length; i++) {
    hash = ((hash << 5) - hash + seed.charCodeAt(i)) | 0
  }
  const copy = [...arr]
  for (let i = copy.length - 1; i > 0; i--) {
    hash = ((hash * 1664525 + 1013904223) | 0) >>> 0
    const j = hash % (i + 1)
    ;[copy[i], copy[j]] = [copy[j], copy[i]]
  }
  return copy
}

async function fetchDrops() {
  try {
    const { data } = await axios.get('/products/trending')
    const today = new Date().toDateString()
    const shuffled = seededShuffle(data, today)
    drops.value = shuffled.slice(0, 6)
  } catch (e) {
    drops.value = []
  }
}

function pad(n) { return String(n).padStart(2, '0') }

function buildMidnightCountdown() {
  const now = new Date()
  const midnight = new Date(now)
  midnight.setHours(24, 0, 0, 0)
  const diff = Math.max(0, midnight - now)
  const h = Math.floor(diff / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  midnightCountdown.value = `${pad(h)}:${pad(m)}:${pad(s)}`
}

onMounted(async () => {
  await fetchDrops()
  buildMidnightCountdown()
  intervalId = setInterval(buildMidnightCountdown, 1000)
})

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId)
})

function fmt(val) {
  if (val == null) return '0'
  return Number(val).toLocaleString('pt-MZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
</script>

<style scoped>
.daily-drop-section {
  background: linear-gradient(135deg, #050d1a 0%, #111111 100%);
}

.midnight-badge {
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.new-badge {
  background: rgba(245, 158, 11, 0.85);
  color: #000;
}

.drop-card {
  background: linear-gradient(160deg, #0d1320 0%, #161616 100%);
  border: 1px solid rgba(245, 158, 11, 0.15);
  animation: slideInUp 0.4s ease both;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.drop-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(245, 158, 11, 0.15);
  border-color: rgba(245, 158, 11, 0.4);
}

@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
