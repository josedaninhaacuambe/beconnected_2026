<template>
  <div class="p-6 max-w-6xl mx-auto">
    <h1 class="text-xl font-bold text-bc-light mb-6">Pedidos</h1>

    <!-- Filters bar -->
    <div class="flex flex-wrap gap-3 mb-5">
      <input
        v-model="filters.search"
        type="text"
        placeholder="Nº pedido ou cliente..."
        class="input-african flex-1 min-w-48"
      />
      <select v-model="filters.status" class="select-african">
        <option value="">Todos os estados</option>
        <option value="pending">Pendente</option>
        <option value="confirmed">Confirmado</option>
        <option value="processing">A preparar</option>
        <option value="shipped">Enviado</option>
        <option value="delivered">Entregue</option>
        <option value="cancelled">Cancelado</option>
      </select>
      <input
        v-model="filters.store"
        type="text"
        placeholder="Loja..."
        class="input-african w-40"
      />
      <input v-model="filters.date_from" type="date" class="input-african w-40" />
      <input v-model="filters.date_to" type="date" class="input-african w-40" />
      <button @click="applyFilters" class="btn-gold px-4 py-2 text-sm">Filtrar</button>
    </div>

    <!-- Stats bar -->
    <div class="flex gap-4 mb-5 text-sm text-bc-muted">
      <span>Total: <strong class="text-bc-light">{{ meta?.total ?? 0 }}</strong></span>
      <span>Reembolsos pendentes: <strong class="text-red-400">{{ pendingRefunds }}</strong></span>
    </div>

    <!-- Feedback -->
    <p v-if="successMsg" class="text-green-400 text-sm mb-4">{{ successMsg }}</p>
    <p v-if="errorMsg" class="text-red-400 text-sm mb-4">{{ errorMsg }}</p>

    <!-- Table -->
    <div class="card-african overflow-hidden">
      <div v-if="loading" class="p-4 space-y-2">
        <div v-for="i in 6" :key="i" class="skeleton h-12 rounded-xl"></div>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-bc-gold/20">
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Nº Pedido</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Cliente</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Loja(s)</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Total</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Estado Pedido</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Estado Pag.</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Reembolso</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Resolvido</th>
            <th class="text-right py-3 px-4 text-bc-muted text-xs uppercase">Acções</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="orders.length === 0">
            <td colspan="9" class="py-8 text-center text-bc-muted text-sm">Nenhum pedido encontrado.</td>
          </tr>
          <tr
            v-for="order in orders"
            :key="order.id"
            class="border-b border-bc-gold/10 hover:bg-bc-gold/5 transition"
          >
            <td class="py-3 px-4 font-mono text-xs text-bc-light">{{ order.order_number }}</td>
            <td class="py-3 px-4 text-bc-muted text-xs">{{ order.user?.name ?? '—' }}</td>
            <td class="py-3 px-4 text-bc-muted text-xs">{{ storeNames(order) }}</td>
            <td class="py-3 px-4 text-bc-gold font-semibold text-xs">{{ formatMZN(order.total_amount) }}</td>
            <td class="py-3 px-4">
              <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', orderStatusBadge(order.status)]">
                {{ orderStatusLabel(order.status) }}
              </span>
            </td>
            <td class="py-3 px-4 text-bc-muted text-xs">{{ order.payment_status ?? '—' }}</td>
            <td class="py-3 px-4 text-center">
              <span v-if="order.refund_flag && !order.resolved_at" class="text-red-400 text-base" title="Reembolso pendente">🚨</span>
              <span v-else-if="order.refund_flag" class="text-bc-muted text-xs">Sim</span>
              <span v-else class="text-bc-muted text-xs">—</span>
            </td>
            <td class="py-3 px-4">
              <span v-if="order.resolved_at" class="text-green-400 text-xs">✓ {{ formatDate(order.resolved_at) }}</span>
              <span v-else class="text-bc-muted text-xs">Não</span>
            </td>
            <td class="py-3 px-4 text-right">
              <button
                @click="openManage(order)"
                class="text-xs px-3 py-1 bg-bc-gold/20 text-bc-gold rounded-lg hover:bg-bc-gold/30"
              >Gerir</button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="p-4 flex items-center justify-between text-xs text-bc-muted border-t border-bc-gold/10">
        <span>{{ meta.total }} pedidos</span>
        <div class="flex gap-2">
          <button
            v-for="p in meta.last_page"
            :key="p"
            @click="page = p; loadOrders()"
            :class="['px-2 py-1 rounded', p === meta.current_page ? 'bg-bc-gold text-bc-dark font-bold' : 'hover:bg-bc-gold/10']"
          >{{ p }}</button>
        </div>
      </div>
    </div>

    <!-- Manage Modal -->
    <div v-if="manageModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="manageModal = false">
      <div class="bg-bc-surface rounded-2xl w-full max-w-lg p-6 shadow-2xl overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-bc-light font-bold text-lg">Gerir Pedido #{{ selectedOrder?.order_number }}</h2>
          <button @click="manageModal = false" class="text-bc-muted hover:text-bc-light text-xl">✕</button>
        </div>

        <!-- Order summary -->
        <div class="bg-bc-surface-2 rounded-xl p-4 mb-4 space-y-1 text-sm">
          <p class="text-bc-muted text-xs uppercase mb-2">Resumo</p>
          <p class="text-bc-light">Cliente: <span class="text-bc-muted">{{ selectedOrder?.user?.name ?? '—' }}</span></p>
          <p class="text-bc-light">Loja(s): <span class="text-bc-muted">{{ storeNames(selectedOrder) }}</span></p>
          <p class="text-bc-light">Total: <span class="text-bc-gold font-semibold">{{ formatMZN(selectedOrder?.total_amount) }}</span></p>
          <p v-if="selectedOrder?.items_summary" class="text-bc-muted text-xs">{{ selectedOrder.items_summary }}</p>
        </div>

        <form @submit.prevent="saveManage" class="space-y-4">
          <div>
            <label class="text-bc-muted text-xs block mb-1">Nota Admin</label>
            <textarea
              v-model="manageForm.admin_note"
              rows="3"
              class="input-african w-full resize-none"
              placeholder="Observações internas..."
            ></textarea>
          </div>

          <div>
            <label class="text-bc-muted text-xs block mb-1">Estado do Pedido</label>
            <select v-model="manageForm.status" class="select-african w-full">
              <option value="">Manter estado actual</option>
              <option value="pending">Pendente</option>
              <option value="confirmed">Confirmado</option>
              <option value="processing">A preparar</option>
              <option value="shipped">Enviado</option>
              <option value="delivered">Entregue</option>
              <option value="cancelled">Cancelado</option>
            </select>
          </div>

          <div class="flex items-center gap-3">
            <input
              id="refund_flag"
              v-model="manageForm.refund_flag"
              type="checkbox"
              class="w-4 h-4 accent-bc-gold"
            />
            <label for="refund_flag" class="text-bc-light text-sm cursor-pointer">
              Marcar como Reembolso Pendente
            </label>
          </div>

          <p v-if="modalError" class="text-red-400 text-sm">{{ modalError }}</p>

          <div class="flex gap-3 pt-2">
            <button type="submit" :disabled="saving" class="btn-gold flex-1 py-2 text-sm">
              {{ saving ? 'A guardar…' : 'Guardar' }}
            </button>
            <button type="button" @click="manageModal = false" class="flex-1 py-2 text-sm border border-bc-gold/30 rounded-xl text-bc-muted hover:text-bc-light">
              Fechar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const orders = ref([])
const loading = ref(true)
const meta = ref(null)
const page = ref(1)
const pendingRefunds = ref(0)
const successMsg = ref('')
const errorMsg = ref('')

const filters = ref({
  search: '',
  status: '',
  store: '',
  date_from: '',
  date_to: '',
})

const manageModal = ref(false)
const selectedOrder = ref(null)
const saving = ref(false)
const modalError = ref('')

const manageForm = ref({
  admin_note: '',
  status: '',
  refund_flag: false,
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('pt-MZ')
}

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

function storeNames(order) {
  if (!order) return '—'
  if (order.stores?.length) return order.stores.map(s => s.name).join(', ')
  if (order.store?.name) return order.store.name
  return '—'
}

function orderStatusLabel(s) {
  return {
    pending: 'Pendente',
    confirmed: 'Confirmado',
    processing: 'A preparar',
    shipped: 'Enviado',
    delivered: 'Entregue',
    cancelled: 'Cancelado',
  }[s] ?? s
}

function orderStatusBadge(s) {
  const base = 'px-2 py-0.5 rounded-full text-xs font-medium '
  return base + ({
    pending: 'bg-yellow-500/20 text-yellow-300',
    confirmed: 'bg-blue-500/20 text-blue-300',
    processing: 'bg-purple-500/20 text-purple-300',
    shipped: 'bg-indigo-500/20 text-indigo-300',
    delivered: 'bg-green-500/20 text-green-300',
    cancelled: 'bg-red-500/20 text-red-300',
  }[s] ?? 'bg-bc-surface text-bc-muted')
}

function flashSuccess(msg) {
  successMsg.value = msg
  errorMsg.value = ''
  setTimeout(() => { successMsg.value = '' }, 3000)
}

function flashError(msg) {
  errorMsg.value = msg
  successMsg.value = ''
  setTimeout(() => { errorMsg.value = '' }, 4000)
}

async function loadOrders() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin/orders', {
      params: { ...filters.value, page: page.value },
    })
    orders.value = data.data
    meta.value = data.meta ?? {
      total: data.total,
      current_page: data.current_page,
      last_page: data.last_page,
    }
    pendingRefunds.value = data.pending_refunds ?? orders.value.filter(o => o.refund_flag && !o.resolved_at).length
  } catch {
    flashError('Erro ao carregar pedidos.')
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  loadOrders()
}

function openManage(order) {
  selectedOrder.value = order
  manageForm.value = {
    admin_note: order.admin_note ?? '',
    status: '',
    refund_flag: order.refund_flag ?? false,
  }
  modalError.value = ''
  manageModal.value = true
}

async function saveManage() {
  saving.value = true
  modalError.value = ''
  try {
    const payload = {
      admin_note: manageForm.value.admin_note,
      refund_flag: manageForm.value.refund_flag,
    }
    if (manageForm.value.status) payload.status = manageForm.value.status

    const { data } = await axios.put(`/admin/orders/${selectedOrder.value.id}/resolve`, payload)

    const idx = orders.value.findIndex(o => o.id === selectedOrder.value.id)
    if (idx !== -1) {
      orders.value[idx] = { ...orders.value[idx], ...data.order ?? data }
    }

    manageModal.value = false
    flashSuccess('Pedido actualizado com sucesso.')
  } catch (err) {
    modalError.value = err.response?.data?.message ?? 'Erro ao guardar alterações.'
  } finally {
    saving.value = false
  }
}

onMounted(loadOrders)
</script>
