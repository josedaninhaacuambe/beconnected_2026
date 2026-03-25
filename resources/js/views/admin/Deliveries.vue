<template>
  <div class="p-6 pb-mobile">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-bc-gold">Monitorização de Entregas</h1>
        <p class="text-bc-muted text-sm">Controlo em tempo real de todas as entregas activas</p>
      </div>
      <button @click="load" class="btn-ghost text-sm flex items-center gap-2">
        <span :class="{ 'animate-spin': loading }">🔄</span> Actualizar
      </button>
    </div>

    <!-- Resumo por estado -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
      <button
        v-for="s in statusFilters"
        :key="s.value"
        @click="activeStatus = s.value; load()"
        :class="['card-african p-3 text-center transition', activeStatus === s.value ? 'border-bc-gold' : '']"
      >
        <p class="text-xl">{{ s.icon }}</p>
        <p class="text-bc-light text-xs font-bold mt-1">{{ statusCount[s.value] ?? 0 }}</p>
        <p class="text-bc-muted text-xs">{{ s.label }}</p>
      </button>
    </div>

    <!-- Tabela de entregas -->
    <div class="card-african overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-bc-muted">A carregar...</div>
      <div v-else-if="deliveries.length === 0" class="p-8 text-center text-bc-muted">
        Sem entregas {{ activeStatus ? 'com este estado' : '' }}.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm min-w-[700px]">
          <thead>
            <tr class="border-b border-bc-gold/20 text-bc-muted text-xs uppercase tracking-wide">
              <th class="text-left py-3 px-4">Pedido</th>
              <th class="text-left py-3 px-4">Cliente</th>
              <th class="text-left py-3 px-4">Estafeta</th>
              <th class="text-left py-3 px-4">Estado</th>
              <th class="text-left py-3 px-4">GPS</th>
              <th class="text-left py-3 px-4">Criado</th>
              <th class="text-right py-3 px-4">Acções</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="d in deliveries"
              :key="d.id"
              class="border-b border-bc-surface-2/50 hover:bg-bc-surface-2/20 transition cursor-pointer"
              @click="openDetail(d)"
            >
              <td class="py-3 px-4">
                <p class="text-bc-light font-mono text-xs">#{{ d.order?.order_number ?? d.order_id }}</p>
                <p class="text-bc-muted text-xs">{{ formatMZN(d.order?.total) }}</p>
              </td>
              <td class="py-3 px-4">
                <p class="text-bc-light text-sm">{{ d.order?.user?.name ?? '—' }}</p>
                <p class="text-bc-muted text-xs">{{ d.order?.user?.phone ?? d.order?.user?.email ?? '' }}</p>
              </td>
              <td class="py-3 px-4">
                <p v-if="d.driver" class="text-bc-light text-sm">{{ d.driver.user?.name }}</p>
                <button
                  v-else
                  @click.stop="openAssign(d)"
                  class="text-bc-gold text-xs border border-bc-gold/30 rounded px-2 py-0.5 hover:bg-bc-gold/10"
                >Atribuir</button>
              </td>
              <td class="py-3 px-4">
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusClass(d.status)]">
                  {{ statusLabel(d.status) }}
                </span>
              </td>
              <td class="py-3 px-4">
                <span v-if="d.driver?.current_latitude" class="text-green-400 text-xs">📡 Activo</span>
                <span v-else class="text-bc-muted text-xs">—</span>
              </td>
              <td class="py-3 px-4 text-bc-muted text-xs">{{ formatDate(d.created_at) }}</td>
              <td class="py-3 px-4 text-right">
                <button @click.stop="openDetail(d)" class="text-bc-gold text-xs hover:underline">Detalhe</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginação -->
      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 p-4">
        <button
          v-for="p in meta.last_page" :key="p"
          @click="page = p; load()"
          :class="['px-3 py-1 rounded text-xs', p === meta.current_page ? 'bg-bc-gold text-bc-dark font-bold' : 'text-bc-muted hover:text-bc-gold border border-bc-gold/20']"
        >{{ p }}</button>
      </div>
    </div>

    <!-- Painel de detalhes -->
    <div v-if="selected" class="fixed inset-0 bg-black/70 z-50 flex items-end sm:items-center justify-center p-4">
      <div class="card-african p-5 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="text-bc-gold font-bold">Entrega #{{ selected.id }}</h3>
            <p class="text-bc-muted text-xs">Pedido #{{ selected.order?.order_number }}</p>
          </div>
          <button @click="selected = null" class="text-bc-muted hover:text-bc-light text-xl">✕</button>
        </div>

        <!-- Estado -->
        <div class="flex items-center gap-2 mb-4">
          <span :class="['text-xs px-3 py-1 rounded-full font-bold', statusClass(selected.status)]">
            {{ statusLabel(selected.status) }}
          </span>
          <span v-if="selected.driver?.current_latitude" class="text-green-400 text-xs">📡 GPS activo</span>
        </div>

        <!-- Cliente -->
        <div class="bg-bc-surface-2 rounded-lg p-3 mb-3">
          <p class="text-bc-muted text-xs uppercase tracking-wide mb-1">Cliente</p>
          <p class="text-bc-light text-sm font-medium">{{ selected.order?.user?.name }}</p>
          <p class="text-bc-muted text-xs">{{ selected.order?.user?.phone ?? selected.order?.user?.email }}</p>
          <p class="text-bc-muted text-xs mt-1">📍 {{ selected.dropoff_address }}</p>
          <p v-if="selected.dropoff_latitude" class="text-bc-muted text-xs font-mono">
            GPS: {{ selected.dropoff_latitude?.toFixed(5) }}, {{ selected.dropoff_longitude?.toFixed(5) }}
          </p>
        </div>

        <!-- Estafeta -->
        <div class="bg-bc-surface-2 rounded-lg p-3 mb-3">
          <p class="text-bc-muted text-xs uppercase tracking-wide mb-1">Estafeta</p>
          <div v-if="selected.driver">
            <p class="text-bc-light text-sm font-medium">{{ selected.driver.user?.name }}</p>
            <p class="text-bc-muted text-xs">{{ selected.driver.vehicle_type }} · {{ selected.driver.user?.phone }}</p>
            <div v-if="selected.driver.current_latitude" class="mt-2">
              <p class="text-green-400 text-xs">📡 Localização actual:</p>
              <p class="text-bc-muted text-xs font-mono">{{ selected.driver.current_latitude?.toFixed(5) }}, {{ selected.driver.current_longitude?.toFixed(5) }}</p>
              <a
                :href="`https://www.google.com/maps?q=${selected.driver.current_latitude},${selected.driver.current_longitude}`"
                target="_blank"
                class="text-bc-gold text-xs hover:underline mt-1 inline-block"
              >🗺 Ver no Google Maps</a>
            </div>
          </div>
          <div v-else>
            <p class="text-bc-muted text-sm">Sem estafeta atribuído</p>
            <button @click="openAssign(selected)" class="btn-gold text-xs mt-2 px-3 py-1">Atribuir estafeta</button>
          </div>
        </div>

        <!-- Trajecto -->
        <div class="bg-bc-surface-2 rounded-lg p-3 mb-3">
          <p class="text-bc-muted text-xs uppercase tracking-wide mb-2">Trajecto</p>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs">
              <span :class="selected.assigned_at ? 'text-green-400' : 'text-bc-muted'">{{ selected.assigned_at ? '✓' : '○' }}</span>
              <span :class="selected.assigned_at ? 'text-bc-light' : 'text-bc-muted'">Atribuído: {{ selected.assigned_at ? formatDate(selected.assigned_at) : '—' }}</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
              <span :class="selected.picked_up_at ? 'text-green-400' : 'text-bc-muted'">{{ selected.picked_up_at ? '✓' : '○' }}</span>
              <span :class="selected.picked_up_at ? 'text-bc-light' : 'text-bc-muted'">Recolhido: {{ selected.picked_up_at ? formatDate(selected.picked_up_at) : '—' }}</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
              <span :class="selected.delivered_at ? 'text-green-400' : 'text-bc-muted'">{{ selected.delivered_at ? '✓' : '○' }}</span>
              <span :class="selected.delivered_at ? 'text-bc-light' : 'text-bc-muted'">Entregue: {{ selected.delivered_at ? formatDate(selected.delivered_at) : '—' }}</span>
            </div>
            <div v-if="selected.client_confirmed_at" class="flex items-center gap-2 text-xs">
              <span class="text-green-400">✓</span>
              <span class="text-bc-light">Confirmado pelo cliente: {{ formatDate(selected.client_confirmed_at) }}</span>
              <span v-if="selected.driver_rating" class="text-yellow-400">★ {{ selected.driver_rating }}/5</span>
            </div>
          </div>
        </div>

        <!-- Avaliação do estafeta -->
        <div v-if="selected.driver_rating" class="bg-bc-surface-2 rounded-lg p-3">
          <p class="text-bc-muted text-xs uppercase tracking-wide mb-1">Avaliação do serviço</p>
          <div class="flex items-center gap-1">
            <span v-for="i in 5" :key="i" :class="i <= selected.driver_rating ? 'text-yellow-400' : 'text-bc-muted'">★</span>
            <span class="text-bc-muted text-xs ml-1">({{ selected.driver_rating }}/5)</span>
          </div>
          <p v-if="selected.driver_rating_comment" class="text-bc-muted text-xs mt-1">{{ selected.driver_rating_comment }}</p>
        </div>
      </div>
    </div>

    <!-- Modal de atribuição de estafeta -->
    <div v-if="assignDelivery" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
      <div class="card-african p-5 w-full max-w-md">
        <h3 class="text-bc-gold font-bold mb-4">Atribuir Estafeta</h3>
        <div v-if="availableDrivers.length === 0" class="text-bc-muted text-sm text-center py-4">
          Nenhum estafeta disponível neste momento.
        </div>
        <div v-else class="space-y-2 mb-4 max-h-60 overflow-y-auto">
          <label
            v-for="d in availableDrivers" :key="d.id"
            class="flex items-center gap-3 p-3 rounded-lg border border-bc-surface-2 hover:border-bc-gold/40 cursor-pointer"
          >
            <input type="radio" v-model="selectedDriverId" :value="d.id" />
            <div>
              <p class="text-bc-light text-sm">{{ d.user?.name }}</p>
              <p class="text-bc-muted text-xs">{{ d.vehicle_type }} · {{ d.user?.phone }}</p>
            </div>
          </label>
        </div>
        <div class="flex gap-3">
          <button @click="assignDelivery = null; selectedDriverId = null" class="btn-ghost flex-1">Cancelar</button>
          <button @click="doReassign" :disabled="!selectedDriverId || assignLoading" class="btn-gold flex-1">
            {{ assignLoading ? 'A atribuir...' : 'Confirmar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const deliveries = ref([])
const loading = ref(true)
const activeStatus = ref('')
const page = ref(1)
const meta = ref({ current_page: 1, last_page: 1 })
const selected = ref(null)
const assignDelivery = ref(null)
const selectedDriverId = ref(null)
const assignLoading = ref(false)
const availableDrivers = ref([])
const statusCount = ref({})
let refreshTimer = null

const statusFilters = [
  { value: '',           icon: '📋', label: 'Todas' },
  { value: 'pending',    icon: '⏳', label: 'Pendentes' },
  { value: 'assigned',   icon: '👤', label: 'Atribuídas' },
  { value: 'picking_up', icon: '📦', label: 'A recolher' },
  { value: 'in_transit', icon: '🚗', label: 'Em trânsito' },
  { value: 'delivered',  icon: '✅', label: 'Entregues' },
]

function statusLabel(s) {
  return { pending: 'Pendente', assigned: 'Atribuída', picking_up: 'A recolher', in_transit: 'Em trânsito', delivered: 'Entregue', failed: 'Falhou' }[s] ?? s
}

function statusClass(s) {
  return {
    pending:    'bg-yellow-900/40 text-yellow-400',
    assigned:   'bg-blue-900/40 text-blue-400',
    picking_up: 'bg-orange-900/40 text-orange-400',
    in_transit: 'bg-purple-900/40 text-purple-400',
    delivered:  'bg-green-900/40 text-green-400',
    failed:     'bg-red-900/40 text-red-400',
  }[s] ?? 'bg-bc-surface-2 text-bc-muted'
}

function formatMZN(v) {
  return v != null ? new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 0 }).format(v) : '—'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('pt-MZ', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (activeStatus.value) params.status = activeStatus.value
    const { data } = await axios.get('/admin/deliveries', { params })
    deliveries.value = data.data
    meta.value = data.meta ?? { current_page: data.current_page, last_page: data.last_page }
  } finally {
    loading.value = false
  }
}

async function loadStatusCounts() {
  const statuses = ['pending', 'assigned', 'picking_up', 'in_transit', 'delivered']
  const results = await Promise.all(statuses.map(s =>
    axios.get('/admin/deliveries', { params: { status: s, page: 1 } }).then(r => [s, r.data.total ?? 0])
  ))
  statusCount.value = Object.fromEntries(results)
}

async function openDetail(d) {
  try {
    const { data } = await axios.get(`/admin/deliveries/${d.id}`)
    selected.value = data
  } catch {
    selected.value = d
  }
}

async function openAssign(d) {
  assignDelivery.value = d
  selectedDriverId.value = null
  try {
    const { data } = await axios.get('/admin/drivers', { params: { status: 'approved', is_available: true } })
    availableDrivers.value = (data.data ?? data).filter(dr => dr.status === 'approved')
  } catch {
    availableDrivers.value = []
  }
}

async function doReassign() {
  if (!selectedDriverId.value || !assignDelivery.value) return
  assignLoading.value = true
  try {
    await axios.post(`/admin/deliveries/${assignDelivery.value.id}/reassign`, { driver_id: selectedDriverId.value })
    assignDelivery.value = null
    selectedDriverId.value = null
    await load()
  } finally {
    assignLoading.value = false
  }
}

onMounted(() => {
  load()
  loadStatusCounts()
  refreshTimer = setInterval(() => { load(); loadStatusCounts() }, 30000) // auto-refresh 30s
})

onUnmounted(() => clearInterval(refreshTimer))
</script>
