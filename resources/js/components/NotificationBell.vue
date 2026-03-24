<template>
  <div class="relative" ref="bellRef">
    <!-- Botão sino -->
    <button @click="toggle" class="relative p-2 text-bc-muted hover:text-bc-gold transition">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
      </svg>
      <!-- Badge contador -->
      <span v-if="unreadCount > 0"
        class="absolute top-0.5 right-0.5 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Painel de notificações -->
    <Teleport to="body">
      <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>
      <div
        v-if="open"
        class="fixed z-50 w-80 max-h-[480px] flex flex-col bg-white border border-gray-200 rounded-2xl shadow-2xl overflow-hidden"
        :style="panelStyle"
      >
        <!-- Cabeçalho -->
        <div class="flex items-center justify-between px-4 py-3 bg-bc-navy border-b border-white/10 flex-shrink-0">
          <h3 class="text-white font-semibold text-sm">Notificações</h3>
          <button v-if="unreadCount > 0" @click="markAllRead" class="text-blue-100 text-xs hover:underline">
            Marcar tudo como lido
          </button>
        </div>

        <!-- Lista -->
        <div class="overflow-y-auto flex-1">
          <div v-if="loading" class="p-4 space-y-3">
            <div v-for="i in 4" :key="i" class="skeleton h-14 rounded-xl"></div>
          </div>

          <div v-else-if="notifications.length === 0" class="p-8 text-center text-bc-muted text-sm">
            <span class="text-3xl block mb-2">🔔</span>
            Sem notificações ainda.
          </div>

          <div v-else class="divide-y divide-bc-gold/5">
            <button
              v-for="n in notifications"
              :key="n.id"
              @click="openNotification(n)"
              class="w-full text-left px-4 py-3 hover:bg-bc-gold/5 transition"
              :class="!n.read_at ? 'bg-bc-gold/5' : ''"
            >
              <div class="flex items-start gap-3">
                <span class="text-xl flex-shrink-0 mt-0.5">
                  {{ n.type === 'flash_sale' ? '⚡' : '🔔' }}
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-bc-light text-xs font-semibold line-clamp-1">{{ n.title }}</p>
                  <p class="text-bc-muted text-xs line-clamp-2 mt-0.5">{{ n.body }}</p>
                  <p class="text-bc-muted/60 text-[10px] mt-1">{{ timeAgo(n.created_at) }}</p>
                </div>
                <span v-if="!n.read_at" class="w-2 h-2 bg-bc-gold rounded-full flex-shrink-0 mt-1.5"></span>
              </div>
            </button>
          </div>
        </div>

        <!-- Rodapé -->
        <div class="flex-shrink-0 px-4 py-2 border-t border-bc-gold/10 text-center">
          <RouterLink to="/queima-de-stock" @click="open = false" class="text-bc-gold text-xs hover:underline">
            Ver todas as queimas de stock →
          </RouterLink>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import axios from 'axios'

const authStore = useAuthStore()
const router    = useRouter()

const open          = ref(false)
const loading       = ref(false)
const notifications = ref([])
const unreadCount   = ref(0)
const bellRef       = ref(null)
const panelStyle    = ref({})

let pollInterval = null

function toggle() {
  open.value = !open.value
  if (open.value) {
    positionPanel()
    loadNotifications()
  }
}

function positionPanel() {
  const el = bellRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const fromRight = window.innerWidth - rect.right
  panelStyle.value = {
    top:   `${rect.bottom + 8}px`,
    right: `${fromRight}px`,
  }
}

async function loadNotifications() {
  if (!authStore.isAuthenticated) return
  loading.value = true
  try {
    const { data } = await axios.get('/notifications')
    notifications.value = data
  } finally {
    loading.value = false
  }
}

async function loadUnreadCount() {
  if (!authStore.isAuthenticated) return
  try {
    const { data } = await axios.get('/notifications/unread-count')
    unreadCount.value = data.count
  } catch {}
}

async function markAllRead() {
  await axios.post('/notifications/read-all')
  notifications.value.forEach(n => { if (!n.read_at) n.read_at = new Date().toISOString() })
  unreadCount.value = 0
}

function openNotification(n) {
  if (!n.read_at) {
    axios.post(`/notifications/${n.id}/read`)
    n.read_at = new Date().toISOString()
    if (unreadCount.value > 0) unreadCount.value--
  }
  open.value = false
  if (n.type === 'flash_sale' && n.data?.store_slug && n.data?.product_slug) {
    router.push(`/lojas/${n.data.store_slug}/produtos/${n.data.product_slug}`)
  }
}

function timeAgo(dateStr) {
  const diff = Date.now() - new Date(dateStr)
  const m = Math.floor(diff / 60000)
  if (m < 1) return 'Agora mesmo'
  if (m < 60) return `há ${m} min`
  const h = Math.floor(m / 60)
  if (h < 24) return `há ${h}h`
  return `há ${Math.floor(h / 24)}d`
}

onMounted(() => {
  loadUnreadCount()
  // Verificar novas notificações a cada 30 segundos
  pollInterval = setInterval(loadUnreadCount, 30000)
})

onUnmounted(() => clearInterval(pollInterval))
</script>
