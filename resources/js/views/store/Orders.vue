<template>
  <div class="p-4 max-w-5xl mx-auto pb-mobile">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h1 class="text-xl font-bold text-bc-light">Pedidos da Loja</h1>
        <p class="text-bc-muted text-xs mt-0.5">Actualiza automaticamente a cada 30 segundos</p>
      </div>
      <button @click="load" :disabled="loading" class="btn-ghost text-sm flex items-center gap-2">
        <span :class="{ 'animate-spin': loading }">🔄</span> Actualizar
      </button>
    </div>

    <!-- Filtros por estado -->
    <div class="flex gap-2 overflow-x-auto pb-2 mb-5 scrollbar-hide">
      <button
        v-for="s in statusFilters"
        :key="s.value"
        @click="activeStatus = s.value; load()"
        :class="['flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition border', activeStatus === s.value ? 'bg-bc-gold text-bc-dark border-bc-gold' : 'border-bc-gold/20 text-bc-muted hover:border-bc-gold/40']"
      >
        <span>{{ s.icon }}</span>
        <span>{{ s.label }}</span>
        <span v-if="statusCounts[s.value]" class="bg-bc-gold/20 text-bc-gold rounded-full px-1.5 font-bold">{{ statusCounts[s.value] }}</span>
      </button>
    </div>

    <div v-if="loading && orders.length === 0" class="space-y-2">
      <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl"></div>
    </div>

    <div v-else-if="orders.length === 0" class="text-center py-16 text-bc-muted">
      <p class="text-3xl mb-2">📋</p>
      <p>Nenhum pedido {{ activeStatus ? 'com este estado' : '' }}.</p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="o in orders"
        :key="o.id"
        class="card-african p-4 hover:border-bc-gold/40 transition cursor-pointer"
        @click="selected = selected?.id === o.id ? null : o"
      >
        <!-- Linha principal -->
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <span class="font-mono text-bc-light text-xs font-bold">#{{ o.order?.order_number }}</span>
              <span :class="statusBadge(o.status)" class="text-xs px-2 py-0.5 rounded-full">{{ statusLabel(o.status) }}</span>
              <span v-if="o.status === 'pending'" class="text-xs bg-orange-500/20 text-orange-400 px-2 py-0.5 rounded-full animate-pulse">NOVO</span>
            </div>
            <p class="text-bc-muted text-xs truncate">{{ o.order?.user?.name }} · {{ formatDate(o.created_at) }}</p>
            <p class="text-bc-gold font-semibold text-sm mt-1">{{ formatMZN(o.total_amount) }} · {{ o.items_count }} iten(s)</p>
          </div>
          <div class="flex-shrink-0">
            <select
              :value="o.status"
              @change.stop="updateStatus(o, $event.target.value)"
              class="text-xs bg-bc-surface border border-bc-gold/20 rounded-lg px-2 py-1 text-bc-light"
              @click.stop
            >
              <option value="pending">Pendente</option>
              <option value="confirmed">Confirmado</option>
              <option value="processing">A preparar</option>
              <option value="ready">Pronto</option>
              <option value="shipped">Enviado</option>
              <option value="delivered">Entregue</option>
              <option value="cancelled">Cancelado</option>
            </select>
          </div>
        </div>

        <!-- Detalhes expandidos -->
        <div v-if="selected?.id === o.id" class="mt-3 pt-3 border-t border-bc-gold/10 space-y-3">
          <!-- Itens com stock -->
          <div>
            <p class="text-bc-muted text-xs font-semibold mb-2">Itens do pedido + estado do stock:</p>
            <div class="space-y-1.5">
              <div v-for="item in o.items" :key="item.id" class="flex items-center justify-between bg-bc-surface-2 rounded-lg px-3 py-2">
                <div class="flex-1 min-w-0">
                  <p class="text-bc-light text-sm font-medium truncate">{{ item.product_name ?? item.product?.name }}</p>
                  <p class="text-bc-muted text-xs">Qtd vendida: {{ item.quantity }} × {{ formatMZN(item.unit_price) }}</p>
                </div>
                <div class="flex-shrink-0 text-right ml-3">
                  <p class="text-bc-gold text-sm font-semibold">{{ formatMZN(item.total) }}</p>
                  <p v-if="item.product?.stock != null" class="text-xs" :class="item.product.stock.quantity <= (item.product.stock.minimum_stock ?? 5) ? 'text-red-400' : 'text-green-400'">
                    Stock actual: {{ item.product.stock.quantity }}
                    <span v-if="item.product.stock.quantity <= (item.product.stock.minimum_stock ?? 5)"> ⚠</span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Endereco de entrega -->
          <div class="bg-bc-surface-2 rounded-lg p-3">
            <p class="text-bc-muted text-xs font-semibold mb-1">📍 Entrega para:</p>
            <p class="text-bc-light text-sm">{{ o.order?.delivery_address }}</p>
            <p class="text-bc-muted text-xs mt-0.5">{{ o.order?.user?.name }} · {{ o.order?.user?.phone || o.order?.user?.email }}</p>
          </div>

          <!-- Notas do cliente -->
          <div v-if="o.order?.notes" class="bg-bc-surface-2 rounded-lg p-3">
            <p class="text-bc-muted text-xs font-semibold mb-1">📝 Nota do cliente:</p>
            <p class="text-bc-light text-sm">{{ o.order.notes }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Paginacao -->
    <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-6">
      <button
        v-for="p in meta.last_page" :key="p"
        @click="page = p; load()"
        :class="['px-3 py-1 rounded text-xs', p === meta.current_page ? 'bg-bc-gold text-bc-dark font-bold' : 'text-bc-muted border border-bc-gold/20 hover:text-bc-gold']"
      >{{ p }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const orders = ref([])
const loading = ref(true)
const selected = ref(null)
const activeStatus = ref('')
const page = ref(1)
const meta = ref({ current_page: 1, last_page: 1 })
const statusCounts = ref({})
let refreshTimer = null

const statusFilters = [
  { value: '',           icon: '📋', label: 'Todos' },
  { value: 'pending',    icon: '🔔', label: 'Pendentes' },
  { value: 'confirmed',  icon: '✅', label: 'Confirmados' },
  { value: 'processing', icon: '⚙',  label: 'A preparar' },
  { value: 'ready',      icon: '📦', label: 'Prontos' },
  { value: 'shipped',    icon: '🚚', label: 'Enviados' },
  { value: 'delivered',  icon: '🏠', label: 'Entregues' },
]

function statusLabel(s) {
  return { pending: 'Pendente', confirmed: 'Confirmado', processing: 'A preparar', ready: 'Pronto', shipped: 'Enviado', delivered: 'Entregue', cancelled: 'Cancelado' }[s] ?? s
}

function statusBadge(s) {
  const map = {
    pending:    'bg-orange-500/20 text-orange-400',
    confirmed:  'bg-blue-500/20 text-blue-400',
    processing: 'bg-yellow-500/20 text-yellow-400',
    ready:      'bg-teal-500/20 text-teal-400',
    shipped:    'bg-purple-500/20 text-purple-400',
    delivered:  'bg-green-500/20 text-green-400',
    cancelled:  'bg-red-500/20 text-red-400',
  }
  return map[s] ?? 'bg-bc-surface text-bc-muted'
}

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

function formatDate(d) {
  return new Date(d).toLocaleString('pt-MZ', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, per_page: 20 }
    if (activeStatus.value) params.status = activeStatus.value
    const { data } = await axios.get('/store/orders', { params })
    orders.value = data.data ?? data
    meta.value = { current_page: data.current_page ?? 1, last_page: data.last_page ?? 1 }
  } finally {
    loading.value = false
  }
}

async function loadStatusCounts() {
  try {
    const counts = {}
    // fetch pending count only (most important)
    const { data } = await axios.get('/store/orders', { params: { status: 'pending', per_page: 1 } })
    counts.pending = data.total ?? (data.data ?? data).length
    statusCounts.value = counts
  } catch {}
}

async function updateStatus(order, status) {
  try {
    await axios.put(`/store/orders/${order.id}/status`, { status })
    order.status = status
    loadStatusCounts()
  } catch (e) {
    alert(e.response?.data?.message || 'Erro ao actualizar estado.')
  }
}

onMounted(() => {
  load()
  loadStatusCounts()
  refreshTimer = setInterval(() => {
    load()
    loadStatusCounts()
  }, 30000)
})

onUnmounted(() => clearInterval(refreshTimer))
</script>
