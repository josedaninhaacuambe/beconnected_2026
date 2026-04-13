<template>
  <div class="h-full overflow-y-auto p-4 space-y-4 pb-20 sm:pb-4" style="background:#F4F6F8;">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-lg font-black text-gray-800">💰 Fecho de Caixa</h1>
        <p class="text-xs text-gray-500">Vendas do dia organizadas por factura</p>
      </div>
      <div class="flex items-center gap-2">
        <!-- Seletor de data -->
        <input v-model="selectedDate" type="date" @change="load"
          class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-bc-gold" />
        <button @click="load" :disabled="loading"
          class="px-4 py-2 rounded-xl text-white font-bold text-sm disabled:opacity-40 transition"
          style="background:#F07820;">
          {{ loading ? '...' : '↻' }}
        </button>
      </div>
    </div>

    <!-- Filtro de vendedor (apenas para dono/gerente) -->
    <div v-if="data?.is_owner_or_manager && data?.sellers?.length > 1"
      class="flex items-center gap-2 flex-wrap">
      <span class="text-xs font-semibold text-gray-500">Vendedor:</span>
      <button @click="selectedSeller = null; load()"
        class="px-3 py-1 rounded-lg text-xs font-semibold transition border"
        :class="!selectedSeller ? 'bg-bc-gold/10 border-bc-gold text-bc-gold' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
        Todos
      </button>
      <button v-for="s in data.sellers" :key="s.id"
        @click="selectedSeller = s.id; load()"
        class="px-3 py-1 rounded-lg text-xs font-semibold transition border"
        :class="selectedSeller === s.id ? 'bg-bc-gold/10 border-bc-gold text-bc-gold' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
        {{ s.name }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-16 text-gray-400">A carregar...</div>

    <template v-else-if="data">

      <!-- Resumo do dia -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl p-4 shadow-sm">
          <p class="text-xs text-gray-500">Total de Vendas</p>
          <p class="text-2xl font-black text-gray-800">{{ data.total_sales }}</p>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm">
          <p class="text-xs text-gray-500">Total de Caixa</p>
          <p class="text-xl font-black" style="color:#F07820;">{{ fmt(data.total_revenue) }}</p>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm">
          <p class="text-xs text-gray-500">Descontos</p>
          <p class="text-xl font-black text-red-500">{{ fmt(data.total_discount) }}</p>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm">
          <p class="text-xs text-gray-500">IVA Cobrado</p>
          <p class="text-xl font-black text-green-600">{{ fmt(data.total_vat) }}</p>
        </div>
      </div>

      <!-- Por método de pagamento -->
      <div class="bg-white rounded-2xl p-4 shadow-sm">
        <h3 class="text-sm font-black text-gray-700 mb-3">Por Método de Pagamento</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div v-for="pm in data.by_payment" :key="pm.method"
            class="text-center p-3 rounded-xl border border-gray-100">
            <p class="text-lg">{{ payIcon(pm.method) }}</p>
            <p class="text-xs text-gray-500 capitalize">{{ pm.method }}</p>
            <p class="font-black text-gray-800 text-sm">{{ fmt(pm.total) }}</p>
            <p class="text-xs text-gray-400">{{ pm.count }} venda{{ pm.count !== 1 ? 's' : '' }}</p>
          </div>
        </div>
      </div>

      <!-- Por vendedor (apenas dono/gerente) -->
      <div v-if="data.is_owner_or_manager && data.by_seller?.length > 1"
        class="bg-white rounded-2xl p-4 shadow-sm">
        <h3 class="text-sm font-black text-gray-700 mb-3">Por Vendedor</h3>
        <div class="space-y-2">
          <div v-for="s in data.by_seller" :key="s.user_id"
            class="flex items-center justify-between p-3 rounded-xl bg-gray-50">
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-black"
                style="background:#1C2B3C;">{{ (s.name ?? '?')[0].toUpperCase() }}</div>
              <span class="text-sm font-semibold text-gray-800">{{ s.name }}</span>
            </div>
            <div class="text-right">
              <p class="text-sm font-black" style="color:#F07820;">{{ fmt(s.total) }}</p>
              <p class="text-xs text-gray-400">{{ s.sales }} venda{{ s.sales !== 1 ? 's' : '' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Lista de vendas (facturas) -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-sm font-black text-gray-700">Facturas do Dia</h3>
          <span class="text-xs text-gray-400">{{ data.sales.length }} registo{{ data.sales.length !== 1 ? 's' : '' }}</span>
        </div>

        <div v-if="!data.sales.length" class="text-center py-12 text-gray-400 text-sm">
          Sem vendas registadas neste dia.
        </div>

        <div v-for="(sale, idx) in data.sales" :key="sale.id"
          class="border-b border-gray-50 last:border-0">

          <!-- Cabeçalho da venda -->
          <button class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition"
            @click="toggleSale(sale.id)">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-black text-white flex-shrink-0"
                style="background:#1C2B3C;">{{ idx + 1 }}</div>
              <div class="text-left">
                <p class="text-sm font-bold text-gray-800">
                  {{ formatTime(sale.sale_at) }}
                  <span v-if="sale.customer_name" class="text-gray-500 font-normal"> · {{ sale.customer_name }}</span>
                </p>
                <p class="text-xs text-gray-400">
                  {{ payIcon(sale.payment_method) }} {{ sale.payment_method }}
                  <span v-if="sale.user?.name && data.is_owner_or_manager"> · {{ sale.user.name }}</span>
                </p>
              </div>
            </div>
            <div class="text-right flex-shrink-0">
              <p class="text-sm font-black" style="color:#F07820;">{{ fmt(sale.total) }}</p>
              <p class="text-xs" :class="expandedSale === sale.id ? 'text-bc-gold' : 'text-gray-400'">
                {{ sale.items?.length }} item{{ sale.items?.length !== 1 ? 's' : '' }}
                {{ expandedSale === sale.id ? '▲' : '▼' }}
              </p>
            </div>
          </button>

          <!-- Itens da venda (expansível) -->
          <div v-if="expandedSale === sale.id" class="px-4 pb-3">
            <div class="bg-gray-50 rounded-xl overflow-hidden">
              <table class="w-full text-xs">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold">Produto</th>
                    <th class="text-right px-3 py-2 text-gray-500 font-semibold">Qty</th>
                    <th class="text-right px-3 py-2 text-gray-500 font-semibold">P.Unit</th>
                    <th class="text-right px-3 py-2 text-gray-500 font-semibold">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in sale.items" :key="item.id" class="border-b border-gray-100 last:border-0">
                    <td class="px-3 py-2 text-gray-700">{{ item.product_name }}</td>
                    <td class="text-right px-3 py-2 text-gray-600">
                      <span v-if="item.weight_amount">{{ item.weight_amount }}{{ item.weight_unit }}</span>
                      <span v-else>{{ item.quantity }}</span>
                    </td>
                    <td class="text-right px-3 py-2 text-gray-600">{{ fmt(item.unit_price) }}</td>
                    <td class="text-right px-3 py-2 font-semibold text-gray-800">{{ fmt(item.subtotal) }}</td>
                  </tr>
                </tbody>
              </table>
              <div class="px-3 py-2 border-t border-gray-200 space-y-1">
                <div class="flex justify-between text-xs text-gray-500">
                  <span>Subtotal</span><span>{{ fmt(sale.subtotal) }}</span>
                </div>
                <div v-if="sale.discount > 0" class="flex justify-between text-xs text-red-500">
                  <span>Desconto</span><span>- {{ fmt(sale.discount) }}</span>
                </div>
                <div v-if="sale.vat_amount > 0" class="flex justify-between text-xs text-green-600">
                  <span>IVA ({{ sale.vat_rate }}%)</span><span>+ {{ fmt(sale.vat_amount) }}</span>
                </div>
                <div class="flex justify-between text-sm font-black border-t border-gray-200 pt-1">
                  <span>TOTAL</span>
                  <span style="color:#F07820;">{{ fmt(sale.total) }}</span>
                </div>
                <div v-if="sale.amount_paid > 0 && sale.payment_method === 'cash'"
                  class="flex justify-between text-xs text-gray-500">
                  <span>Entregue</span><span>{{ fmt(sale.amount_paid) }}</span>
                </div>
                <div v-if="sale.change > 0" class="flex justify-between text-xs text-green-600 font-semibold">
                  <span>Troco</span><span>{{ fmt(sale.change) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Resumo de fecho -->
      <div class="bg-white rounded-2xl p-5 shadow-sm border-2" style="border-color:#F07820;">
        <h3 class="text-sm font-black text-gray-700 mb-3">📋 Resumo de Fecho de Caixa</h3>
        <div class="space-y-2">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Total de transacções</span>
            <span class="font-bold">{{ data.total_sales }}</span>
          </div>
          <div v-for="pm in data.by_payment" :key="pm.method" class="flex justify-between text-sm">
            <span class="text-gray-600">{{ payIcon(pm.method) }} {{ pm.method }} ({{ pm.count }})</span>
            <span class="font-bold">{{ fmt(pm.total) }}</span>
          </div>
          <div v-if="data.total_discount > 0" class="flex justify-between text-sm text-red-500">
            <span>Descontos concedidos</span>
            <span class="font-bold">- {{ fmt(data.total_discount) }}</span>
          </div>
          <div v-if="data.total_vat > 0" class="flex justify-between text-sm text-green-600">
            <span>IVA cobrado</span>
            <span class="font-bold">{{ fmt(data.total_vat) }}</span>
          </div>
          <div class="flex justify-between text-base font-black border-t border-gray-200 pt-2 mt-2">
            <span>TOTAL DE CAIXA</span>
            <span style="color:#F07820;">{{ fmt(data.total_revenue) }}</span>
          </div>
        </div>
        <button @click="printCashClose"
          class="mt-4 w-full py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
          🖨️ Imprimir Fecho de Caixa
        </button>
      </div>

    </template>

    <div v-else-if="!loading" class="text-center py-16 text-gray-400 text-sm">
      Sem dados para este dia.
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading       = ref(true)
const data          = ref(null)
const selectedDate  = ref(new Date().toISOString().slice(0, 10))
const selectedSeller = ref(null)
const expandedSale  = ref(null)

const _fmt = new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' })
function fmt(v) { return _fmt.format(v ?? 0) }

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('pt-MZ', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function payIcon(method) {
  return { cash: '💵', mpesa: '📱', emola: '📲', credit: '💳' }[method] ?? '💳'
}

function toggleSale(id) {
  expandedSale.value = expandedSale.value === id ? null : id
}

async function load() {
  loading.value = true
  data.value    = null
  try {
    const params = { date: selectedDate.value }
    if (selectedSeller.value) params.seller_id = selectedSeller.value
    const { data: res } = await axios.get('/pos/daily-cash', { params })
    data.value = res
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function printCashClose() {
  if (!data.value) return
  const d = data.value
  const date = new Date(selectedDate.value).toLocaleDateString('pt-MZ', { day: '2-digit', month: '2-digit', year: 'numeric' })

  const payRows = d.by_payment.map(pm =>
    `<tr><td>${payIcon(pm.method)} ${pm.method} (${pm.count} venda${pm.count !== 1 ? 's' : ''})</td><td style="text-align:right;font-weight:700;">${fmt(pm.total)}</td></tr>`
  ).join('')

  const html = `
    <div style="text-align:center;margin-bottom:12px;">
      <div style="font-size:16px;font-weight:900;letter-spacing:2px;">FECHO DE CAIXA</div>
      <div style="font-size:11px;color:#000;">Data: ${date}</div>
    </div>
    <div style="border-top:1px dashed #000;margin:8px 0;"></div>
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr><td>Total de vendas</td><td style="text-align:right;font-weight:700;">${d.total_sales}</td></tr>
      ${payRows}
      ${d.total_discount > 0 ? `<tr><td>Descontos</td><td style="text-align:right;color:#000;">- ${fmt(d.total_discount)}</td></tr>` : ''}
      ${d.total_vat > 0 ? `<tr><td>IVA cobrado</td><td style="text-align:right;">${fmt(d.total_vat)}</td></tr>` : ''}
    </table>
    <div style="border-top:2px solid #000;margin:8px 0;"></div>
    <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:900;">
      <span>TOTAL DE CAIXA</span><span>${fmt(d.total_revenue)}</span>
    </div>
    <div style="border-top:1px dashed #000;margin:8px 0;"></div>
    <div style="font-size:10px;color:#000;text-align:center;">
      Impresso em ${new Date().toLocaleString('pt-MZ')}
    </div>
  `

  const win = window.open('', '_blank', 'width=420,height=500')
  if (!win) { alert('Popup bloqueado. Permita popups para imprimir.'); return }
  win.document.write(`<!DOCTYPE html><html><head>
    <meta charset="utf-8"><title>Fecho de Caixa</title>
    <style>
      @page { margin:2mm; size:80mm auto; }
      * { color:#000!important; box-sizing:border-box; }
      body { margin:0; padding:4mm; font-family:'Courier New',Courier,monospace; font-size:13px; width:80mm; background:#fff; }
      @media print { body { margin:0; padding:2mm 4mm; } }
    </style>
  </head><body>${html}</body></html>`)
  win.document.close()
  win.focus()
  setTimeout(() => {
    try { win.print() } catch (e) {}
    setTimeout(() => { try { win.close() } catch (e) {} }, 1000)
  }, 500)
}

onMounted(load)
</script>
