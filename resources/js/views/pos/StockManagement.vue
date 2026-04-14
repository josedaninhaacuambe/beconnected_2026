<template>
  <div class="flex flex-col h-full overflow-hidden">

    <!-- Aviso offline -->
    <div v-if="!isOnline" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border-b border-amber-200">
      <span>📵</span>
      <span>Modo offline — os movimentos serão sincronizados quando houver ligação</span>
      <span v-if="pendingMovementsCount > 0" class="ml-auto bg-amber-200 text-amber-800 rounded-full px-2 py-0.5">
        {{ pendingMovementsCount }} pendente{{ pendingMovementsCount > 1 ? 's' : '' }}
      </span>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-gray-200 bg-white px-4">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        class="px-4 py-3 text-sm font-semibold border-b-2 transition"
        :class="activeTab === t.key ? 'border-bc-gold text-bc-gold' : 'border-transparent text-gray-500 hover:text-gray-700'">
        {{ t.icon }} {{ t.label }}
        <span v-if="t.key === 'history' && pendingMovementsCount > 0"
          class="ml-1 bg-amber-400 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
          {{ pendingMovementsCount }}
        </span>
      </button>
    </div>

    <div class="flex-1 overflow-y-auto p-4">

      <!-- ── Tab: Produtos e Stock ─────────────────────────────────────── -->
      <div v-if="activeTab === 'stock'">
        <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex-1 min-w-0 flex items-center gap-3">
            <input v-model="search" type="text" placeholder="🔍 Pesquisar produto..."
              class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-bc-gold" />
            <select v-model="stockFilter" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
              <option value="all">Todos</option>
              <option value="low">Stock baixo</option>
              <option value="out">Sem stock</option>
            </select>
          </div>
          <div class="flex flex-col gap-2 w-full sm:w-auto sm:flex-row sm:items-center">
            <button v-if="canPrintStock" @click="printStockList"
              class="w-full sm:w-auto px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
              🖨️ Imprimir / Guardar PDF
            </button>
            <button v-if="canPrintStock" @click="exportStockCsv"
              class="w-full sm:w-auto px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
              📄 Exportar CSV
            </button>
          </div>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 6" :key="i" class="skeleton h-16 rounded-xl"></div>
        </div>

        <div v-else class="space-y-2">
          <div v-for="p in filteredProducts" :key="p.id"
            class="bg-white rounded-xl border border-gray-100 px-4 py-3 flex flex-col gap-2">
            <!-- Linha superior: info + stock -->
            <div class="flex items-center gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p class="font-semibold text-sm text-gray-800 truncate">{{ p.name }}</p>
                  <span v-if="p._offline" class="text-[9px] bg-amber-100 text-amber-700 font-bold px-1.5 py-0.5 rounded-full flex-shrink-0">offline</span>
                </div>
                <p class="text-xs text-gray-400">{{ p.sku || 'Sem SKU' }}</p>
              </div>
              <div class="text-center flex-shrink-0">
                <p class="text-lg font-black" :class="stockColor(p.stock?.quantity)">{{ p.stock?.quantity ?? 0 }}</p>
                <p class="text-[10px] text-gray-400">em stock</p>
              </div>
            </div>

            <!-- Linha inferior: botões de movimento -->
            <div class="flex gap-2">
              <button @click="openMovement(p, 'in')"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-white transition hover:opacity-90"
                style="background:#22C55E;">
                + Entrada
              </button>
              <button @click="openMovement(p, 'out')"
                :disabled="(p.stock?.quantity ?? 0) <= 0"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-white bg-red-500 hover:bg-red-600 transition disabled:opacity-40">
                − Saída
              </button>
              <button @click="openMovement(p, 'adjustment')"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">
                ± Ajuste
              </button>
            </div>
          </div>
          <p v-if="!filteredProducts.length && !loading" class="text-center py-12 text-gray-400">Nenhum produto encontrado.</p>
        </div>
      </div>

      <!-- ── Tab: Histórico ───────────────────────────────────────────── -->
      <div v-if="activeTab === 'history'">

        <!-- Movimentos pendentes offline -->
        <div v-if="pendingMovements.length" class="mb-4">
          <p class="text-xs font-bold text-amber-700 mb-2">📵 Pendentes (offline)</p>
          <div class="space-y-1.5">
            <div v-for="m in pendingMovements" :key="m.local_id"
              class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-center gap-3">
              <span class="text-lg">{{ m.type === 'in' ? '📥' : m.type === 'out' ? '📤' : '⚖️' }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ m.product_name }}</p>
                <p class="text-xs text-amber-600">{{ m.reason || 'Sem motivo' }} · pendente sync</p>
              </div>
              <div class="text-right">
                <p class="font-bold text-sm" :class="m.type === 'in' ? 'text-green-600' : m.type === 'out' ? 'text-red-500' : 'text-blue-500'">
                  {{ m.type === 'in' ? '+' : m.type === 'out' ? '-' : '' }}{{ m.quantity }}
                </p>
                <p class="text-[10px] text-gray-400">{{ formatDate(m.created_at) }}</p>
              </div>
            </div>
          </div>
          <div class="border-t border-gray-200 my-3"></div>
        </div>

        <!-- Aviso offline com cache -->
        <div v-if="!isOnline && !loadingHistory && !movements.length" class="text-center py-8 text-gray-400">
          <span class="text-3xl block mb-2">📵</span>
          <p class="text-sm">Sem histórico em cache. Ligue-se para carregar.</p>
        </div>
        <div v-else-if="!isOnline && movements.length" class="flex items-center gap-2 px-3 py-2 mb-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl">
          <span>📵</span><span>Histórico offline — última sincronização guardada localmente</span>
        </div>

        <div v-else-if="loadingHistory" class="space-y-2">
          <div v-for="i in 6" :key="i" class="skeleton h-12 rounded-xl"></div>
        </div>

        <div v-else class="space-y-1.5">
          <div v-for="m in movements" :key="m.id"
            class="bg-white rounded-xl border border-gray-100 px-4 py-3 flex items-center gap-3">
            <span class="text-lg">{{ m.type === 'in' ? '📥' : m.type === 'out' ? '📤' : '⚖️' }}</span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 truncate">{{ m.product?.name }}</p>
              <p class="text-xs text-gray-400">{{ m.reason }} · {{ m.user?.name }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-sm" :class="m.type === 'in' ? 'text-green-600' : m.type === 'out' ? 'text-red-500' : 'text-blue-500'">
                {{ m.type === 'in' ? '+' : m.type === 'out' ? '-' : '' }}{{ m.quantity }}
              </p>
              <p class="text-[10px] text-gray-400">{{ m.quantity_before }} → {{ m.quantity_after }}</p>
            </div>
            <p class="text-[10px] text-gray-400 w-20 text-right">{{ formatDate(m.created_at) }}</p>
          </div>
          <p v-if="!movements.length && !loadingHistory" class="text-center py-12 text-gray-400">Sem movimentos registados.</p>
        </div>
      </div>

    </div>

    <!-- ── Modal de movimento ──────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="movModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
          <h3 class="font-bold text-lg mb-1">
            {{ movModal.type === 'in' ? '📥 Entrada de Stock' : movModal.type === 'out' ? '📤 Saída de Stock' : '⚖️ Ajuste de Stock' }}
          </h3>
          <p class="text-sm text-gray-500 mb-1">{{ movModal.product?.name }}</p>
          <p v-if="!isOnline" class="text-xs text-amber-600 font-semibold mb-4">
            📵 Offline — será sincronizado quando houver ligação
          </p>
          <p v-else class="mb-4"></p>

          <div class="space-y-3">
            <div>
              <label class="text-xs font-semibold text-gray-600">
                {{ movModal.type === 'adjustment' ? 'Novo total em stock' : 'Quantidade' }}
              </label>
              <input v-model.number="movModal.quantity" type="number" min="1"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mt-1 focus:outline-none focus:border-bc-gold text-lg font-bold text-center" />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-600">Motivo (opcional)</label>
              <input v-model="movModal.reason" type="text" placeholder="ex: Compra ao fornecedor"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
            </div>
          </div>

          <div v-if="movModal.error" class="mt-3 text-red-500 text-sm text-center">{{ movModal.error }}</div>

          <div class="flex gap-3 mt-5">
            <button @click="movModal.open = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600">Cancelar</button>
            <button @click="confirmMovement"
              :disabled="movModal.loading || !movModal.quantity || movModal.quantity <= 0"
              class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm transition disabled:opacity-40"
              style="background:#F07820;">
              {{ movModal.loading ? 'A guardar...' : isOnline ? 'Confirmar' : '💾 Guardar Offline' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth.js'
import {
  useOfflinePos,
  getCachedProducts, updateCachedProduct,
  savePendingMovement, getPendingMovements,
  cacheStockHistory, getCachedStockHistory, fmtCacheAge,
} from '@/composables/useOfflinePos'

const { isOnline, trySyncNow, refreshPendingCount } = useOfflinePos()

const activeTab = ref('stock')
const tabs = [
  { key: 'stock',   icon: '📦', label: 'Produtos' },
  { key: 'history', icon: '📋', label: 'Histórico' },
]

const auth = useAuthStore()
const products         = ref([])
const movements        = ref([])
const pendingMovements = ref([])
const loading          = ref(true)
const loadingHistory   = ref(false)
const search           = ref('')
const stockFilter      = ref('all')

const pendingMovementsCount = computed(() => pendingMovements.value.length)

const movModal = ref({
  open: false, product: null, type: 'in',
  quantity: 1, reason: '', loading: false, error: '',
})

const filteredProducts = computed(() => {
  let list = products.value
  if (search.value) list = list.filter(p => p.name.toLowerCase().includes(search.value.toLowerCase()) || (p.sku && p.sku.toLowerCase().includes(search.value.toLowerCase())))
  if (stockFilter.value === 'low')  list = list.filter(p => (p.stock?.quantity ?? 0) > 0 && (p.stock?.quantity ?? 0) <= (p.stock?.minimum_stock ?? 5))
  if (stockFilter.value === 'out')  list = list.filter(p => (p.stock?.quantity ?? 0) <= 0)
  return list
})

const canPrintStock = computed(() => auth.hasPosPermission('gerir_stock'))

function printStockList() {
  const rows = filteredProducts.value.map((p, index) => {
    const qty = p.stock?.quantity ?? 0
    const min = p.stock?.minimum_stock ?? 0
    return `
      <tr>
        <td>${index + 1}</td>
        <td>${p.name}</td>
        <td>${p.sku || '-'}</td>
        <td>${p.barcode || '-'}</td>
        <td class="text-right">${qty}</td>
        <td class="text-right">${min}</td>
      </tr>
    `
  }).join('')

  const html = `<!doctype html>
    <html lang="pt">
      <head>
        <meta charset="utf-8" />
        <title>Lista de Stock - POS</title>
        <style>
          @page { size: A4 portrait; margin: 16mm; }
          body { font-family: Inter, sans-serif; color: #111827; margin: 0; padding: 18px; background: #fff; }
          h1 { font-size: 18px; margin-bottom: 10px; }
          p { margin: 0; }
          table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 11px; }
          th, td { border: 1px solid #D1D5DB; padding: 8px 10px; vertical-align: top; }
          th { background: #F9FAFB; text-align: left; }
          td.text-right { text-align: right; }
          tbody tr { page-break-inside: avoid; }
          .small { font-size: 10px; color: #6B7280; }
        </style>
      </head>
      <body>
        <h1>Lista de Stock - POS</h1>
        <p class="small">Gerado em ${new Date().toLocaleString('pt-MZ')}</p>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Produto</th>
              <th>SKU</th>
              <th>Barcode</th>
              <th>Stock</th>
              <th>Stock mínimo</th>
            </tr>
          </thead>
          <tbody>
            ${rows || '<tr><td colspan="6" class="small">Nenhum produto encontrado.</td></tr>'}
          </tbody>
        </table>
      </body>
    </html>`

  const win = window.open('', '_blank')
  if (!win) return
  win.document.write(html)
  win.document.close()
  win.focus()
  setTimeout(() => {
    win.print()
    win.close()
  }, 300)
}

function exportStockCsv() {
  const header = ['#', 'Produto', 'SKU', 'Barcode', 'Stock', 'Stock mínimo']
  const rows = filteredProducts.value.map((p, index) => [
    index + 1,
    p.name,
    p.sku || '-',
    p.barcode || '-',
    p.stock?.quantity ?? 0,
    p.stock?.minimum_stock ?? 0,
  ])
  const csv = [header, ...rows].map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `stock-pos-${new Date().toISOString().slice(0,10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

function stockColor(qty) {
  if (!qty || qty <= 0) return 'text-red-500'
  if (qty <= 5) return 'text-yellow-500'
  return 'text-green-600'
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('pt-MZ', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function openMovement(product, type) {
  movModal.value = { open: true, product, type, quantity: 1, reason: '', loading: false, error: '' }
}

async function confirmMovement() {
  if (!movModal.value.quantity || movModal.value.quantity <= 0) return
  movModal.value.loading = true
  movModal.value.error   = ''

  const { product, type, quantity, reason } = movModal.value

  try {
    if (isOnline.value) {
      // ── Online: enviar para API ──────────────────────────────────────
      await axios.post('/pos/stock/movement', {
        product_id: product.id,
        type, quantity, reason,
      })
    } else {
      // ── Offline: guardar na fila do IndexedDB ────────────────────────
      const mov = {
        local_id:     `mov_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`,
        product_id:   product.id,
        product_name: product.name,
        type, quantity,
        reason:       reason || '',
        created_at:   new Date().toISOString(),
      }
      await savePendingMovement(mov)
      pendingMovements.value.unshift(mov)
      await refreshPendingCount()
    }

    // Actualizar stock localmente + cache
    const p = products.value.find(x => x.id === product.id)
    if (p && p.stock) {
      if (type === 'in')         p.stock.quantity += quantity
      else if (type === 'out')   p.stock.quantity  = Math.max(0, p.stock.quantity - quantity)
      else                       p.stock.quantity  = quantity
      await updateCachedProduct(p)
    }

    movModal.value.open = false

    // Histórico só carrega se online
    if (isOnline.value) loadHistory()

  } catch (e) {
    movModal.value.error = e.response?.data?.message ?? 'Erro ao registar.'
  } finally {
    movModal.value.loading = false
  }
}

async function loadHistory() {
  loadingHistory.value = true

  // Mostrar cache offline imediatamente
  if (!isOnline.value) {
    const cached = await getCachedStockHistory()
    if (cached) movements.value = cached.value
    loadingHistory.value = false
    return
  }

  // Mostrar cache enquanto carrega da rede
  const cached = await getCachedStockHistory()
  if (cached) movements.value = cached.value

  try {
    const { data } = await axios.get('/pos/stock/history')
    movements.value = data.data
    await cacheStockHistory(data.data)
  } finally {
    loadingHistory.value = false
  }
}

async function loadProducts() {
  loading.value = true

  // 1. Mostrar cache imediatamente
  const cached = await getCachedProducts()
  if (cached.length) {
    products.value = cached
    loading.value  = false
  }

  // 2. Actualizar do servidor em background se online
  if (isOnline.value) {
    try {
      const { data } = await axios.get('/pos/stock')
      products.value = data
      loading.value  = false
    } catch {
      // mantém cache
    }
  }

  if (!products.value.length) loading.value = false
}

async function loadPendingMovements() {
  pendingMovements.value = await getPendingMovements()
}

// Quando volta a ficar online: sincronizar e recarregar
watch(isOnline, async (online) => {
  if (online) {
    await trySyncNow()
    await loadProducts()
    await loadHistory()
    await loadPendingMovements()
  }
})

onMounted(async () => {
  await loadProducts()
  await loadPendingMovements()
  if (isOnline.value) loadHistory()
})
</script>
