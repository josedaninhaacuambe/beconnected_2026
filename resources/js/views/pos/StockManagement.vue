<template>
  <div class="flex flex-col h-full overflow-hidden">
    <!-- Tabs -->
    <div class="flex border-b border-gray-200 bg-white px-4">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        class="px-4 py-3 text-sm font-semibold border-b-2 transition"
        :class="activeTab === t.key ? 'border-bc-gold text-bc-gold' : 'border-transparent text-gray-500 hover:text-gray-700'">
        {{ t.icon }} {{ t.label }}
      </button>
    </div>

    <div class="flex-1 overflow-y-auto p-4">

      <!-- ── Tab: Produtos e Stock ─────────────────────────────────────── -->
      <div v-if="activeTab === 'stock'">
        <div class="flex items-center gap-3 mb-4">
          <input v-model="search" type="text" placeholder="🔍 Pesquisar produto..."
            class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-bc-gold" />
          <select v-model="stockFilter" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
            <option value="all">Todos</option>
            <option value="low">Stock baixo</option>
            <option value="out">Sem stock</option>
          </select>
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
                <p class="font-semibold text-sm text-gray-800 truncate">{{ p.name }}</p>
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
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-white bg-red-500 hover:bg-red-600 transition">
                − Saída
              </button>
              <button @click="openMovement(p, 'adjustment')"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">
                ± Ajuste
              </button>
            </div>
          </div>
          <p v-if="!filteredProducts.length" class="text-center py-12 text-gray-400">Nenhum produto encontrado.</p>
        </div>
      </div>

      <!-- ── Tab: Histórico ───────────────────────────────────────────── -->
      <div v-if="activeTab === 'history'">
        <div v-if="loadingHistory" class="space-y-2">
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
          <p v-if="!movements.length" class="text-center py-12 text-gray-400">Sem movimentos registados.</p>
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
          <p class="text-sm text-gray-500 mb-4">{{ movModal.product?.name }}</p>

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
              :disabled="movModal.loading"
              class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm transition"
              style="background:#F07820;">
              {{ movModal.loading ? 'A guardar...' : 'Confirmar' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const activeTab = ref('stock')
const tabs = [
  { key: 'stock',   icon: '📦', label: 'Produtos' },
  { key: 'history', icon: '📋', label: 'Histórico' },
]

const products  = ref([])
const movements = ref([])
const loading   = ref(true)
const loadingHistory = ref(false)
const search    = ref('')
const stockFilter = ref('all')

const movModal = ref({ open: false, product: null, type: 'in', quantity: 1, reason: '', loading: false, error: '' })

const filteredProducts = computed(() => {
  let list = products.value
  if (search.value) list = list.filter(p => p.name.toLowerCase().includes(search.value.toLowerCase()))
  if (stockFilter.value === 'low')  list = list.filter(p => (p.stock?.quantity ?? 0) > 0 && (p.stock?.quantity ?? 0) <= (p.stock?.minimum_stock ?? 5))
  if (stockFilter.value === 'out')  list = list.filter(p => (p.stock?.quantity ?? 0) <= 0)
  return list
})

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
  movModal.value.loading = true
  movModal.value.error = ''
  try {
    await axios.post('/pos/stock/movement', {
      product_id: movModal.value.product.id,
      type:       movModal.value.type,
      quantity:   movModal.value.quantity,
      reason:     movModal.value.reason,
    })
    // Actualizar stock localmente
    const p = products.value.find(x => x.id === movModal.value.product.id)
    if (p && p.stock) {
      if (movModal.value.type === 'in')         p.stock.quantity += movModal.value.quantity
      else if (movModal.value.type === 'out')   p.stock.quantity -= movModal.value.quantity
      else                                      p.stock.quantity  = movModal.value.quantity
    }
    movModal.value.open = false
    loadHistory()
  } catch (e) {
    movModal.value.error = e.response?.data?.message ?? 'Erro ao registar.'
  } finally {
    movModal.value.loading = false
  }
}

async function loadHistory() {
  loadingHistory.value = true
  try {
    const { data } = await axios.get('/pos/stock/history')
    movements.value = data.data
  } finally {
    loadingHistory.value = false
  }
}

onMounted(async () => {
  const { data } = await axios.get('/pos/stock')
  products.value = data
  loading.value = false
  loadHistory()
})
</script>
