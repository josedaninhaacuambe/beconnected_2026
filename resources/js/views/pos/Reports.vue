<template>
  <div class="overflow-y-auto h-full p-4 space-y-4">
    <!-- Filtros de data + toggle canal -->
    <div class="bg-white rounded-xl border border-gray-100 p-4 flex flex-wrap items-end gap-3">
      <div>
        <label class="text-xs font-semibold text-gray-500">De</label>
        <input v-model="from" type="date" class="block border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
      </div>
      <div>
        <label class="text-xs font-semibold text-gray-500">Até</label>
        <input v-model="to" type="date" class="block border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
      </div>
      <button @click="load" class="px-5 py-2 rounded-xl text-white font-bold text-sm transition hover:opacity-90" style="background:#F07820;">
        📊 Ver Relatório
      </button>
      <div class="flex gap-2">
        <button @click="quickRange('today')" class="px-3 py-2 rounded-xl border text-xs font-semibold text-gray-600 hover:border-bc-gold hover:text-bc-gold transition">Hoje</button>
        <button @click="quickRange('week')"  class="px-3 py-2 rounded-xl border text-xs font-semibold text-gray-600 hover:border-bc-gold hover:text-bc-gold transition">Esta semana</button>
        <button @click="quickRange('month')" class="px-3 py-2 rounded-xl border text-xs font-semibold text-gray-600 hover:border-bc-gold hover:text-bc-gold transition">Este mês</button>
      </div>
      <!-- Toggle canal -->
      <div class="ml-auto flex rounded-xl border border-gray-200 overflow-hidden text-xs font-bold">
        <button @click="canal = 'all'"    :class="canal === 'all'    ? 'bg-bc-gold text-white' : 'text-gray-500 hover:bg-gray-50'" class="px-3 py-2 transition">Tudo</button>
        <button @click="canal = 'pos'"    :class="canal === 'pos'    ? 'bg-bc-gold text-white' : 'text-gray-500 hover:bg-gray-50'" class="px-3 py-2 border-l border-r border-gray-200 transition">🖥️ POS</button>
        <button @click="canal = 'online'" :class="canal === 'online' ? 'bg-bc-gold text-white' : 'text-gray-500 hover:bg-gray-50'" class="px-3 py-2 transition">🌐 Online</button>
      </div>
    </div>

    <div v-if="loading" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div v-for="i in 4" :key="i" class="skeleton h-24 rounded-xl"></div>
    </div>

    <template v-else-if="data">
      <!-- Cards de resumo -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-1">Receita Total</p>
          <p class="text-xl font-black" style="color:#F07820;">{{ fmt(summary.totalRevenue) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-1">Nº de Vendas</p>
          <p class="text-xl font-black text-gray-800">{{ summary.totalSales }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-1">Ticket Médio</p>
          <p class="text-xl font-black text-gray-800">{{ fmt(summary.avgTicket) }}</p>
        </div>
        <!-- Comparação POS vs Online -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-2">POS vs Online</p>
          <div class="flex gap-2 text-xs">
            <div class="flex-1 text-center">
              <p class="font-black text-gray-800">{{ fmt(data.summary.posRevenue) }}</p>
              <p class="text-gray-400">🖥️ POS</p>
            </div>
            <div class="w-px bg-gray-100"></div>
            <div class="flex-1 text-center">
              <p class="font-black text-gray-800">{{ fmt(data.summary.onlineRevenue) }}</p>
              <p class="text-gray-400">🌐 Online</p>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Métodos de pagamento -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <h3 class="font-bold text-sm text-gray-700 mb-3">💳 Métodos de Pagamento</h3>
          <div class="space-y-2">
            <div v-for="m in data.by_payment" :key="m.method" class="flex items-center gap-3">
              <span class="text-lg">{{ methodIcon(m.method) }}</span>
              <div class="flex-1">
                <div class="flex justify-between text-sm mb-0.5">
                  <span class="font-semibold capitalize">{{ m.method }}</span>
                  <span class="font-bold" style="color:#F07820;">{{ fmt(m.total) }}</span>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full transition-all" style="background:#F07820;"
                    :style="{ width: (data.summary.totalRevenue > 0 ? m.total / data.summary.totalRevenue * 100 : 0).toFixed(0) + '%' }"></div>
                </div>
              </div>
              <span class="text-xs text-gray-400">{{ m.count }} vendas</span>
            </div>
          </div>
        </div>

        <!-- Top produtos -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <h3 class="font-bold text-sm text-gray-700 mb-3">🏆 Top Produtos</h3>
          <div class="space-y-2">
            <div v-for="(p, i) in data.top_products" :key="i" class="flex items-center gap-2">
              <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black text-white flex-shrink-0"
                :style="{ background: i === 0 ? '#F07820' : i === 1 ? '#94A3B8' : '#CBD5E1' }">{{ i + 1 }}</span>
              <p class="flex-1 text-sm text-gray-700 truncate">{{ p.product_name }}</p>
              <span class="text-xs text-gray-400">{{ p.qty }} un.</span>
              <span class="text-xs font-bold" style="color:#F07820;">{{ fmt(p.revenue) }}</span>
            </div>
            <p v-if="!data.top_products.length" class="text-center text-gray-400 text-sm py-4">Sem vendas no período.</p>
          </div>
        </div>

        <!-- Por vendedor -->
        <div v-if="canal !== 'online'" class="bg-white rounded-xl border border-gray-100 p-4">
          <h3 class="font-bold text-sm text-gray-700 mb-3">👤 Por Vendedor (POS)</h3>
          <div class="space-y-2">
            <div v-for="s in data.by_seller" :key="s.name" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background:#1C2B3C;">{{ s.name[0] }}</div>
                <span class="text-sm text-gray-700">{{ s.name }}</span>
              </div>
              <div class="text-right">
                <p class="text-sm font-bold" style="color:#F07820;">{{ fmt(s.total) }}</p>
                <p class="text-[10px] text-gray-400">{{ s.count }} vendas</p>
              </div>
            </div>
            <p v-if="!data.by_seller.length" class="text-center text-gray-400 text-sm py-2">Sem vendas POS no período.</p>
          </div>
        </div>

        <!-- Vendas por dia -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <h3 class="font-bold text-sm text-gray-700 mb-3">📅 Vendas por Dia</h3>
          <div class="space-y-2">
            <div v-for="d in filteredByDay.slice(-10)" :key="d.date" class="flex items-center gap-2">
              <span class="text-xs text-gray-400 w-12">{{ d.date }}</span>
              <div class="flex-1 h-4 bg-gray-100 rounded-full overflow-hidden flex">
                <div v-if="d.pos > 0" class="h-full transition-all" style="background:#F07820;"
                  :style="{ width: Math.max(2, d.pos / maxDayTotal * 100).toFixed(0) + '%' }"
                  :title="'POS: ' + fmt(d.pos)"></div>
                <div v-if="d.online > 0" class="h-full transition-all" style="background:#1C2B3C;"
                  :style="{ width: Math.max(2, d.online / maxDayTotal * 100).toFixed(0) + '%' }"
                  :title="'Online: ' + fmt(d.online)"></div>
              </div>
              <span class="text-xs font-bold text-gray-700 w-24 text-right">{{ fmt(d.total) }}</span>
            </div>
            <div class="flex gap-3 text-[10px] text-gray-400 mt-1">
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm inline-block" style="background:#F07820;"></span>POS</span>
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm inline-block" style="background:#1C2B3C;"></span>Online</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabela unificada de vendas -->
      <div class="bg-white rounded-xl border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-bold text-sm text-gray-700">🧾 Histórico de Vendas</h3>
          <div class="flex gap-1 text-xs">
            <span class="px-2 py-0.5 rounded-full" style="background:#F07820;color:white;">🖥️ POS</span>
            <span class="px-2 py-0.5 rounded-full" style="background:#1C2B3C;color:white;">🌐 Online</span>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                <th class="pb-2">Canal</th>
                <th class="pb-2">Data/Hora</th>
                <th class="pb-2">Cliente</th>
                <th class="pb-2">Itens</th>
                <th class="pb-2">Pagamento</th>
                <th class="pb-2 text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="row in unifiedSales" :key="row.id + row.canal" class="hover:bg-gray-50">
                <td class="py-2">
                  <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full text-white"
                    :style="row.canal === 'POS' ? 'background:#F07820' : 'background:#1C2B3C'">
                    {{ row.canal }}
                  </span>
                </td>
                <td class="py-2 text-xs text-gray-500">{{ formatTime(row.date) }}</td>
                <td class="py-2 text-xs text-gray-700">{{ row.customer ?? '—' }}</td>
                <td class="py-2 text-xs text-gray-500">{{ row.items }} item(s)</td>
                <td class="py-2 text-xs">{{ methodIcon(row.payment) }} {{ row.payment }}</td>
                <td class="py-2 text-xs font-bold text-right" style="color:#F07820;">{{ fmt(row.total) }}</td>
              </tr>
              <tr v-if="!unifiedSales.length">
                <td colspan="6" class="text-center py-8 text-gray-400 text-sm">Sem vendas no período.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <div v-else class="flex flex-col items-center justify-center py-24 text-gray-400">
      <span class="text-4xl mb-3">📊</span>
      <p>Selecciona um período e clica em "Ver Relatório"</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const from   = ref(new Date(new Date().setDate(1)).toISOString().slice(0, 10))
const to     = ref(new Date().toISOString().slice(0, 10))
const data   = ref(null)
const loading = ref(false)
const canal   = ref('all') // 'all' | 'pos' | 'online'

// Resumo filtrado por canal
const summary = computed(() => {
  if (!data.value) return {}
  if (canal.value === 'pos') {
    const sales = data.value.pos_sales ?? []
    const total = sales.reduce((s, x) => s + parseFloat(x.total), 0)
    return { totalRevenue: total, totalSales: sales.length, avgTicket: sales.length ? total / sales.length : 0 }
  }
  if (canal.value === 'online') {
    const orders = data.value.online_orders ?? []
    const total  = orders.reduce((s, x) => s + parseFloat(x.subtotal), 0)
    return { totalRevenue: total, totalSales: orders.length, avgTicket: orders.length ? total / orders.length : 0 }
  }
  return data.value.summary
})

const filteredByDay = computed(() => {
  if (!data.value) return []
  return data.value.by_day.map(d => ({
    ...d,
    total:  canal.value === 'pos' ? d.pos : canal.value === 'online' ? d.online : d.total,
  }))
})

const maxDayTotal = computed(() => Math.max(1, ...(data.value?.by_day?.map(d => d.total) ?? [1])))

const unifiedSales = computed(() => {
  if (!data.value) return []
  const rows = []
  if (canal.value !== 'online') {
    for (const s of data.value.pos_sales ?? []) {
      rows.push({ id: 'pos_' + s.id, canal: 'POS', date: s.sale_at, customer: s.customer_name, items: s.items?.length ?? 0, payment: s.payment_method, total: s.total })
    }
  }
  if (canal.value !== 'pos') {
    for (const o of data.value.online_orders ?? []) {
      rows.push({ id: 'online_' + o.id, canal: 'Online', date: o.created_at, customer: null, items: o.items?.length ?? 0, payment: o.order?.payment_method ?? 'online', total: o.subtotal })
    }
  }
  return rows.sort((a, b) => new Date(b.date) - new Date(a.date)).slice(0, 50)
})

function fmt(v) {
  return new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' }).format(v ?? 0)
}
function methodIcon(m) {
  return { cash: '💵', mpesa: '📱', emola: '📲', credit: '🏦', online: '🌐' }[m] ?? '💳'
}
function formatTime(d) {
  return new Date(d).toLocaleString('pt-MZ', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}
function quickRange(range) {
  const now = new Date()
  if (range === 'today') {
    from.value = to.value = now.toISOString().slice(0, 10)
  } else if (range === 'week') {
    const d = new Date(now); d.setDate(d.getDate() - 6)
    from.value = d.toISOString().slice(0, 10)
    to.value   = now.toISOString().slice(0, 10)
  } else {
    from.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
    to.value   = now.toISOString().slice(0, 10)
  }
  load()
}

async function load() {
  loading.value = true
  try {
    const { data: d } = await axios.get('/api/pos/reports', { params: { from: from.value, to: to.value } })
    data.value = d
  } finally {
    loading.value = false
  }
}
</script>
