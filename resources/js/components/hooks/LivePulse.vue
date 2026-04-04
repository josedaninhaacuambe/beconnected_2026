<template>
  <div class="live-pulse-bar overflow-hidden">
    <div class="pulse-inner flex items-center gap-4 px-3 py-2">
      <template v-if="isLoading">
        <span class="text-sm text-white/70">Carregando atualizações ao vivo...</span>
      </template>
      <template v-else-if="error">
        <div class="w-full text-center text-red-200 text-sm flex items-center justify-between">
          <span>{{ error }}</span>
          <button @click="retryLivePulse" class="ml-3 text-white bg-red-500 px-2 py-1 text-xs rounded">Tentar novamente</button>
        </div>
      </template>
      <template v-else-if="messages.length === 0">
        <span class="text-sm text-white/70">Sem atualizações recentes</span>
      </template>
      <template v-else>
        <!-- Live indicator -->
        <div class="live-dot-wrapper flex items-center gap-1.5 shrink-0">
          <span class="live-dot"></span>
          <span class="text-red-400 font-black text-xs uppercase tracking-widest">AO VIVO</span>
        </div>

        <!-- Separator -->
        <div class="h-4 w-px bg-bc-muted/30 shrink-0"></div>

        <!-- Scrolling ticker -->
        <div class="ticker-overflow overflow-hidden flex-1">
          <div class="ticker-track flex gap-12" :style="tickerStyle">
            <span
              v-for="(msg, i) in duplicatedMessages"
              :key="i"
              class="ticker-item text-bc-light text-xs whitespace-nowrap shrink-0"
              v-html="msg"
            ></span>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { trackEvent } from '@/utils/analytics.js'

const messages = ref([])
const isLoading = ref(true)
const error = ref(null)
let refreshInterval = null

const cities = ['Maputo', 'Matola', 'Beira', 'Nampula', 'Tete', 'Quelimane', 'Inhambane', 'Chimoio']

function randomFrom(arr) {
  return arr[Math.floor(Math.random() * arr.length)]
}

function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min
}

function buildMessages(products) {
  if (!products.length) return []
  const msgs = []
  const pool = [...products]

  // 🛒 Added to cart messages
  for (let i = 0; i < 3; i++) {
    const p = randomFrom(pool)
    const city = randomFrom(cities)
    msgs.push(`🛒 <span class="text-bc-gold font-semibold">Alguém em ${city}</span> acabou de adicionar <span class="text-white font-semibold">${p.name}</span> ao carrinho`)
  }

  // 👀 People watching store
  for (let i = 0; i < 3; i++) {
    const p = randomFrom(pool)
    const n = randomInt(3, 49)
    msgs.push(`👀 <span class="text-orange-300 font-semibold">${n} pessoas</span> estão a ver <span class="text-white font-semibold">${p.store?.name}</span> agora`)
  }

  // ✅ Recently purchased
  for (let i = 0; i < 3; i++) {
    const p = randomFrom(pool)
    const mins = randomInt(1, 29)
    msgs.push(`✅ <span class="text-green-400 font-semibold">${p.name}</span> foi comprado há <span class="text-white font-semibold">${mins} min</span>`)
  }

  // 🔥 Hot today
  for (let i = 0; i < 2; i++) {
    const p = randomFrom(pool)
    msgs.push(`🔥 <span class="text-white font-semibold">${p.name}</span> é <span class="text-red-400 font-semibold">top de vendas</span> hoje em ${randomFrom(cities)}`)
  }

  // Shuffle
  for (let i = msgs.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[msgs[i], msgs[j]] = [msgs[j], msgs[i]]
  }

  return msgs.slice(0, 12)
}

async function fetchAndBuild() {
  isLoading.value = true
  error.value = null

  try {
    const { data } = await axios.get('/products/trending')
    const list = Array.isArray(data) ? data : (data.data ?? [])
    messages.value = buildMessages(list)
  } catch (e) {
    messages.value = []
    error.value = 'Não foi possível carregar o pulso ao vivo. Tenta novamente.'
    trackEvent('hook_load_failed', { hook: 'LivePulse', message: e.message || 'unknown' })
  } finally {
    isLoading.value = false
  }
}

function retryLivePulse() {
  fetchAndBuild()
}

// Duplicate messages for seamless CSS loop
const duplicatedMessages = computed(() => [...messages.value, ...messages.value])

// Ticker animation uses CSS, but we dynamically set duration based on message count
const tickerStyle = computed(() => {
  const count = duplicatedMessages.value.length
  // ~7s per message feels natural
  const duration = Math.max(30, count * 7)
  return {
    animation: `ticker-scroll ${duration}s linear infinite`,
  }
})

onMounted(async () => {
  await fetchAndBuild()
  refreshInterval = setInterval(fetchAndBuild, 30000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})
</script>

<style scoped>
.live-pulse-bar {
  background: #0d0d0d;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.pulse-inner {
  min-height: 36px;
}

.live-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  background: #ef4444;
  border-radius: 50%;
  animation: blink 1.2s ease-in-out infinite;
  box-shadow: 0 0 6px rgba(239, 68, 68, 0.8);
}

@keyframes blink {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.4; transform: scale(0.8); }
}

.ticker-overflow {
  mask-image: linear-gradient(to right, transparent 0%, black 4%, black 96%, transparent 100%);
}

.ticker-track {
  will-change: transform;
}

.ticker-item + .ticker-item::before {
  content: '·';
  margin-right: 12px;
  color: rgba(255, 255, 255, 0.2);
}

@keyframes ticker-scroll {
  0%   { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
</style>
