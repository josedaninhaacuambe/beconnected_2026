<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-xl font-bold text-bc-light mb-6">Dashboard de Comissões</h1>

    <!-- Cards de resumo -->
    <div v-if="statsLoading" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div v-for="i in 4" :key="i" class="skeleton h-24 rounded-2xl"></div>
    </div>

    <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="card-african p-4 text-center">
        <p class="text-bc-muted text-xs mb-1">Total Ganho</p>
        <p class="text-bc-gold font-bold text-xl">{{ formatMZN(stats.total_earned) }}</p>
        <p class="text-bc-muted text-xs">acumulado</p>
      </div>
      <div class="card-african p-4 text-center">
        <p class="text-bc-muted text-xs mb-1">Pendente</p>
        <p class="text-orange-400 font-bold text-xl">{{ formatMZN(stats.pending_amount) }}</p>
        <p class="text-bc-muted text-xs">por pagar</p>
      </div>
      <div class="card-african p-4 text-center">
        <p class="text-bc-muted text-xs mb-1">Já Pago</p>
        <p class="text-green-400 font-bold text-xl">{{ formatMZN(stats.total_earned) }}</p>
        <p class="text-bc-muted text-xs">recebido</p>
      </div>
      <div class="card-african p-4 text-center">
        <p class="text-bc-muted text-xs mb-1">Produtos Vendidos</p>
        <p class="text-bc-light font-bold text-xl">{{ stats.total_products_sold?.toLocaleString() ?? 0 }}</p>
        <p class="text-bc-muted text-xs">unidades</p>
      </div>
    </div>

    <!-- Pagar agora -->
    <div class="card-african p-5 mb-6">
      <h2 class="text-bc-gold font-semibold mb-3">Efectuar Pagamento</h2>
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="payoutMethod" class="select-african">
          <option value="mpesa">M-Pesa (840 442 932)</option>
          <option value="emola">eMola (973 157 227)</option>
        </select>
        <button
          @click="processPayout"
          :disabled="paying || !stats?.pending_amount"
          class="btn-gold px-6 py-2 text-sm"
        >
          {{ paying ? 'A processar...' : `Pagar ${formatMZN(stats?.pending_amount)} agora` }}
        </button>
        <p v-if="payoutError" class="text-red-400 text-sm w-full">{{ payoutError }}</p>
        <p v-if="payoutSuccess" class="text-green-400 text-sm w-full">{{ payoutSuccess }}</p>
      </div>
      <p class="text-bc-muted text-xs mt-2">
        Mínimo para pagamento automático: 100 MZN. Taxa: 0,50 MZN por unidade vendida.
      </p>
    </div>

    <!-- Gráfico mensal -->
    <div class="card-african p-5 mb-6">
      <h2 class="text-bc-gold font-semibold mb-4">Evolução Mensal</h2>
      <div v-if="statsLoading" class="skeleton h-32 rounded-xl"></div>
      <div v-else class="flex items-end gap-2 h-32">
        <div
          v-for="m in stats.monthly_breakdown ?? []"
          :key="m.month + '-' + m.year"
          class="flex-1 flex flex-col items-center gap-1"
        >
          <p class="text-bc-gold text-xs font-bold">{{ formatMZN(m.total) }}</p>
          <div
            class="w-full bg-bc-gold/60 rounded-t"
            :style="{ height: barHeight(m.total, stats.monthly_breakdown) + 'px' }"
          ></div>
          <p class="text-bc-muted text-xs">{{ m.month }}/{{ m.year }}</p>
        </div>
      </div>
    </div>

    <!-- Histórico de pagamentos -->
    <div class="card-african p-5">
      <h2 class="text-bc-gold font-semibold mb-4">Histórico de Pagamentos</h2>

      <div v-if="payoutsLoading" class="space-y-2">
        <div v-for="i in 4" :key="i" class="skeleton h-12 rounded-xl"></div>
      </div>

      <div v-else-if="payouts.length === 0" class="text-center py-6 text-bc-muted text-sm">
        Nenhum pagamento registado ainda.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-bc-muted text-xs uppercase border-b border-bc-gold/20">
              <th class="text-left pb-2">Data</th>
              <th class="text-left pb-2">Valor</th>
              <th class="text-left pb-2">Método</th>
              <th class="text-left pb-2">Referência</th>
              <th class="text-left pb-2">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in payouts" :key="p.id" class="border-b border-bc-gold/10">
              <td class="py-2 text-bc-muted text-xs">{{ formatDate(p.created_at) }}</td>
              <td class="py-2 text-bc-gold font-semibold">{{ formatMZN(p.amount) }}</td>
              <td class="py-2 text-bc-light uppercase text-xs">{{ p.method }}</td>
              <td class="py-2 text-bc-muted text-xs font-mono">{{ p.reference ?? '—' }}</td>
              <td class="py-2">
                <span :class="statusBadge(p.status)">{{ p.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const stats = ref({})
const payouts = ref([])
const statsLoading = ref(true)
const payoutsLoading = ref(true)
const paying = ref(false)
const payoutMethod = ref('mpesa')
const payoutError = ref('')
const payoutSuccess = ref('')

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

function formatDate(d) {
  return new Date(d).toLocaleString('pt-MZ')
}

function statusBadge(s) {
  return 'text-xs px-2 py-0.5 rounded-full ' + ({
    completed: 'bg-green-500/20 text-green-300',
    pending: 'bg-orange-500/20 text-orange-300',
    failed: 'bg-red-500/20 text-red-300',
  }[s] ?? 'bg-bc-surface text-bc-muted')
}

function barHeight(amount, breakdown) {
  const max = Math.max(...(breakdown ?? []).map(m => m.total ?? 0), 1)
  return Math.max(4, Math.round((amount / max) * 96))
}

async function loadStats() {
  statsLoading.value = true
  try {
    const { data } = await axios.get('/admin/commissions/dashboard')
    stats.value = data
  } finally {
    statsLoading.value = false
  }
}

async function loadPayouts() {
  payoutsLoading.value = true
  try {
    const { data } = await axios.get('/admin/commissions/payouts')
    payouts.value = data.data ?? data
  } finally {
    payoutsLoading.value = false
  }
}

async function processPayout() {
  paying.value = true
  payoutError.value = ''
  payoutSuccess.value = ''
  try {
    const { data } = await axios.post('/admin/commissions/payout', { payment_method: payoutMethod.value })
    payoutSuccess.value = data.message
    await loadStats()
    await loadPayouts()
  } catch (e) {
    payoutError.value = e.response?.data?.message || 'Erro ao processar pagamento.'
  } finally {
    paying.value = false
  }
}

onMounted(() => {
  loadStats()
  loadPayouts()
})
</script>
