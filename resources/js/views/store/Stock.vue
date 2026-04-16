<template>
  <div class="p-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-bc-light">Gestão de Stock</h1>
        <p class="text-bc-muted text-sm mt-0.5">{{ products.length }} produto{{ products.length !== 1 ? 's' : '' }} · {{ lowStockCount }} com stock baixo</p>
      </div>
      <RouterLink to="/loja/stock/importar" class="btn-gold px-4 py-2 text-sm">📥 Importar</RouterLink>
    </div>

    <!-- Alertas de stock baixo -->
    <div v-if="lowStockProducts.length" class="card-african border-orange-500/30 p-4 mb-6">
      <p class="text-orange-400 font-semibold text-sm mb-3">⚠ {{ lowStockProducts.length }} produto(s) com stock baixo ou esgotado</p>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="p in lowStockProducts" :key="p.id"
          @click="openAdjust(p)"
          class="text-xs px-3 py-1.5 bg-orange-900/30 border border-orange-500/30 text-orange-300 rounded-lg hover:bg-orange-900/50 transition"
        >
          {{ p.name }} — <span :class="stockQty(p) === 0 ? 'text-red-400' : 'text-orange-400'">{{ stockQty(p) }} un.</span>
        </button>
      </div>
    </div>

    <!-- Tabela de stock -->
    <div class="card-african overflow-hidden mb-6">
      <div class="p-4 border-b border-bc-gold/10 flex gap-3">
        <input v-model="search" type="text" placeholder="Pesquisar produto..." class="input-african flex-1 py-2 text-sm" />
        <select v-model="filterStock" class="select-african py-2 text-sm w-44">
          <option value="">Todos</option>
          <option value="ok">Stock OK</option>
          <option value="low">Stock baixo</option>
          <option value="out">Esgotado</option>
        </select>
      </div>

      <div v-if="loading" class="p-6 space-y-3">
        <div v-for="i in 5" :key="i" class="skeleton h-14 rounded-xl"></div>
      </div>

      <div v-else-if="filtered.length === 0" class="p-10 text-center text-bc-muted text-sm">
        Nenhum produto encontrado.
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-bc-gold/10 text-bc-muted text-xs uppercase">
            <th class="text-left px-4 py-3">Produto</th>
            <th class="text-center px-4 py-3">Stock Actual</th>
            <th class="text-center px-4 py-3">Mínimo</th>
            <th class="text-center px-4 py-3">Vendidos</th>
            <th class="text-center px-4 py-3">Estado</th>
            <th class="text-right px-4 py-3">Acções</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-bc-gold/5">
          <tr v-for="p in filtered" :key="p.id" class="hover:bg-bc-gold/5 transition">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg overflow-hidden bg-bc-surface-2 flex-shrink-0 flex items-center justify-center">
                  <AppImg
                    :src="p.images?.[0] ? (p.images[0].startsWith('http') ? p.images[0] : `/storage/${p.images[0]}`) : ''"
                    type="product"
                    class="w-full h-full object-cover"
                  />
                </div>
                <div>
                  <p class="text-bc-light font-medium line-clamp-1">{{ p.name }}</p>
                  <p class="text-bc-muted text-xs font-mono">{{ p.sku || '—' }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="text-lg font-bold" :class="stockColor(p)">{{ stockQty(p) }}</span>
            </td>
            <td class="px-4 py-3 text-center text-bc-muted text-xs">{{ p.stock?.minimum_stock ?? 5 }}</td>
            <td class="px-4 py-3 text-center text-bc-muted text-xs">{{ p.total_sold ?? 0 }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="stockBadge(p)">{{ stockLabel(p) }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button @click="openMovements(p)" class="text-xs px-2 py-1 border border-bc-gold/20 text-bc-muted hover:text-bc-gold hover:border-bc-gold/40 rounded-lg transition">
                  📋 Histórico
                </button>
                <button @click="openAdjust(p)" class="text-xs px-2 py-1 bg-bc-gold/20 text-bc-gold hover:bg-bc-gold/30 rounded-lg transition">
                  ± Ajustar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ══════════ MODAL: AJUSTAR STOCK ══════════ -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="adjustModal.open" class="fixed inset-0 z-50 flex items-center justify-center px-4" @click.self="adjustModal.open = false">
          <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
          <div class="relative bg-bc-dark border border-bc-gold/30 rounded-2xl p-6 w-full max-w-sm z-10">
            <button @click="adjustModal.open = false" class="absolute top-4 right-4 text-bc-muted hover:text-bc-gold">✕</button>

            <h3 class="text-bc-gold font-bold text-lg mb-1">Ajustar Stock</h3>
            <p class="text-bc-muted text-sm mb-4 line-clamp-1">{{ adjustModal.product?.name }}</p>

            <!-- Stock actual -->
            <div class="bg-bc-surface-2 rounded-xl p-4 text-center mb-5">
              <p class="text-bc-muted text-xs mb-1">Stock actual</p>
              <p class="text-4xl font-bold" :class="stockColor(adjustModal.product)">{{ stockQty(adjustModal.product) }}</p>
              <p class="text-bc-muted text-xs mt-1">unidades</p>
            </div>

            <!-- Tipo de operação -->
            <div class="flex gap-2 mb-4">
              <button v-for="t in adjustTypes" :key="t.value"
                @click="adjustModal.type = t.value"
                :class="['flex-1 py-2 rounded-xl text-xs font-medium transition border', adjustModal.type === t.value ? `${t.active}` : 'border-bc-gold/20 text-bc-muted hover:border-bc-gold/30']"
              >{{ t.icon }} {{ t.label }}</button>
            </div>

            <!-- Quantidade -->
            <div class="mb-4">
              <label class="text-bc-muted text-xs mb-1 block">Quantidade</label>
              <input
                v-model.number="adjustModal.quantity"
                type="number"
                min="1"
                class="input-african text-center text-xl font-bold"
                placeholder="0"
              />
              <p v-if="adjustModal.type !== 'adjustment'" class="text-bc-muted text-xs mt-1 text-center">
                Resultado: <span class="font-bold" :class="previewColor">{{ previewQty }}</span> unidades
              </p>
            </div>

            <!-- Motivo -->
            <input
              v-model="adjustModal.reason"
              type="text"
              :placeholder="adjustModal.type === 'in' ? 'Ex: Reposição de fornecedor' : adjustModal.type === 'out' ? 'Ex: Devolução, avaria' : 'Ex: Contagem física'"
              class="input-african text-sm mb-4"
            />

            <p v-if="adjustModal.error" class="text-red-400 text-xs mb-3 text-center">{{ adjustModal.error }}</p>

            <button
              @click="applyAdjust"
              :disabled="!adjustModal.quantity || adjustModal.saving"
              class="btn-green w-full py-3 text-sm disabled:opacity-50"
            >
              {{ adjustModal.saving ? 'A guardar...' : 'Confirmar Ajuste' }}
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ══════════ MODAL: HISTÓRICO ══════════ -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="historyModal.open" class="fixed inset-0 z-50 flex items-center justify-center px-4" @click.self="historyModal.open = false">
          <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
          <div class="relative bg-bc-dark border border-bc-gold/30 rounded-2xl p-6 w-full max-w-lg z-10 max-h-[90vh] flex flex-col">
            <button @click="historyModal.open = false" class="absolute top-4 right-4 text-bc-muted hover:text-bc-gold">✕</button>

            <h3 class="text-bc-gold font-bold text-lg mb-1">Histórico de Stock</h3>
            <p class="text-bc-muted text-sm mb-4">{{ historyModal.product?.name }}</p>

            <!-- Resumo -->
            <div class="grid grid-cols-3 gap-3 mb-4">
              <div class="bg-bc-surface-2 rounded-xl p-3 text-center">
                <p class="text-bc-muted text-xs">Actual</p>
                <p class="text-bc-light font-bold text-xl">{{ historyModal.product?.quantity ?? 0 }}</p>
              </div>
              <div class="bg-bc-surface-2 rounded-xl p-3 text-center">
                <p class="text-bc-muted text-xs">Mínimo</p>
                <p class="text-bc-light font-bold text-xl">{{ historyModal.product?.minimum_stock ?? 5 }}</p>
              </div>
              <div class="bg-bc-surface-2 rounded-xl p-3 text-center">
                <p class="text-bc-muted text-xs">Movimentos</p>
                <p class="text-bc-light font-bold text-xl">{{ historyModal.movements?.length ?? 0 }}</p>
              </div>
            </div>

            <!-- Lista de movimentos -->
            <div class="overflow-y-auto flex-1">
              <div v-if="historyModal.loading" class="space-y-2">
                <div v-for="i in 4" :key="i" class="skeleton h-12 rounded-xl"></div>
              </div>
              <div v-else-if="!historyModal.movements?.length" class="text-center py-8 text-bc-muted text-sm">
                Nenhum movimento registado.
              </div>
              <div v-else class="space-y-2">
                <div v-for="m in historyModal.movements" :key="m.id"
                  class="flex items-center gap-3 bg-bc-surface-2 rounded-xl p-3">
                  <div :class="['w-8 h-8 rounded-lg flex items-center justify-center text-sm flex-shrink-0', movementColor(m.type)]">
                    {{ movementIcon(m.type) }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-bc-light text-xs font-medium">{{ movementTypeLabel(m.type) }}
                      <span v-if="m.type === 'out' && m.reason?.startsWith('Venda')" class="text-orange-400 ml-1">🛒</span>
                    </p>
                    <p class="text-bc-muted text-xs line-clamp-1">{{ m.reason || 'Sem motivo' }}</p>
                    <p class="text-bc-muted text-xs">{{ formatDate(m.created_at) }} · {{ m.user?.name ?? 'Sistema' }}</p>
                  </div>
                  <div class="text-right flex-shrink-0">
                    <p :class="['font-bold text-sm', m.type === 'in' ? 'text-green-400' : m.type === 'out' ? 'text-red-400' : 'text-blue-400']">
                      {{ m.type === 'in' ? '+' : m.type === 'out' ? '-' : '=' }}{{ m.quantity }}
                    </p>
                    <p class="text-bc-muted text-xs">{{ m.quantity_before }} → {{ m.quantity_after }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'

const products    = ref([])
const loading     = ref(true)
const search      = ref('')
const filterStock = ref('')

// ─── Computed ─────────────────────────────────────────────
const stockQty = (p) => p?.stock?.quantity ?? 0
const minStock = (p) => p?.stock?.minimum_stock ?? 5

const stockColor = (p) => {
  if (!p) return ''
  const q = stockQty(p)
  if (q === 0) return 'text-red-400'
  if (q <= minStock(p)) return 'text-orange-400'
  return 'text-green-400'
}

const stockLabel = (p) => {
  const q = stockQty(p)
  if (q === 0) return 'Esgotado'
  if (q <= minStock(p)) return 'Baixo'
  return 'OK'
}

const stockBadge = (p) => {
  const q = stockQty(p)
  const base = 'text-xs px-2 py-0.5 rounded-full font-medium '
  if (q === 0) return base + 'bg-red-500/20 text-red-400'
  if (q <= minStock(p)) return base + 'bg-orange-500/20 text-orange-400'
  return base + 'bg-green-500/20 text-green-400'
}

const lowStockProducts = computed(() =>
  products.value.filter(p => stockQty(p) <= minStock(p))
)
const lowStockCount = computed(() => lowStockProducts.value.length)

const filtered = computed(() => {
  let list = products.value
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(p => p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q))
  }
  if (filterStock.value === 'ok')  list = list.filter(p => stockQty(p) > minStock(p))
  if (filterStock.value === 'low') list = list.filter(p => stockQty(p) > 0 && stockQty(p) <= minStock(p))
  if (filterStock.value === 'out') list = list.filter(p => stockQty(p) === 0)
  return list
})

// ─── Adjust Modal ─────────────────────────────────────────
const adjustModal = reactive({
  open: false, product: null,
  type: 'in', quantity: null, reason: '', saving: false, error: '',
})

const adjustTypes = [
  { value: 'in',         label: 'Entrada',   icon: '+', active: 'border-green-500/60 bg-green-500/10 text-green-400' },
  { value: 'out',        label: 'Saída',     icon: '−', active: 'border-red-500/60 bg-red-500/10 text-red-400' },
  { value: 'adjustment', label: 'Ajuste',    icon: '=', active: 'border-blue-500/60 bg-blue-500/10 text-blue-400' },
]

const previewQty = computed(() => {
  const cur = stockQty(adjustModal.product)
  const qty = adjustModal.quantity || 0
  if (adjustModal.type === 'in')  return cur + qty
  if (adjustModal.type === 'out') return Math.max(0, cur - qty)
  return qty
})

const previewColor = computed(() => {
  const v = previewQty.value
  if (v === 0) return 'text-red-400'
  if (v <= minStock(adjustModal.product)) return 'text-orange-400'
  return 'text-green-400'
})

function openAdjust(p) {
  adjustModal.product  = p
  adjustModal.type     = 'in'
  adjustModal.quantity = null
  adjustModal.reason   = ''
  adjustModal.error    = ''
  adjustModal.open     = true
}

async function applyAdjust() {
  if (!adjustModal.quantity || adjustModal.quantity < 1) return
  adjustModal.saving = true
  adjustModal.error  = ''
  try {
    const { data } = await axios.post(`/store/products/${adjustModal.product.id}/stock`, {
      type:     adjustModal.type,
      quantity: adjustModal.quantity,
      reason:   adjustModal.reason || undefined,
    })
    // Update local stock
    const p = products.value.find(x => x.id === adjustModal.product.id)
    if (p && data.stock) {
      p.stock = { ...p.stock, quantity: data.stock.quantity }
    }
    adjustModal.open = false
  } catch (e) {
    adjustModal.error = e.response?.data?.message || 'Erro ao ajustar stock.'
  } finally {
    adjustModal.saving = false
  }
}

// ─── History Modal ────────────────────────────────────────
const historyModal = reactive({
  open: false, product: null, movements: [], loading: false,
})

async function openMovements(p) {
  historyModal.product   = { ...p, quantity: stockQty(p), minimum_stock: minStock(p) }
  historyModal.movements = []
  historyModal.loading   = true
  historyModal.open      = true
  try {
    const { data } = await axios.get(`/store/products/${p.id}/stock/movements`)
    historyModal.movements = data.movements
    historyModal.product   = { ...data.product }
  } finally {
    historyModal.loading = false
  }
}

// ─── Movement helpers ─────────────────────────────────────
function movementTypeLabel(type) {
  return { in: 'Entrada de stock', out: 'Saída de stock', adjustment: 'Ajuste' }[type] ?? type
}
function movementIcon(type) {
  return { in: '↑', out: '↓', adjustment: '↺' }[type] ?? '•'
}
function movementColor(type) {
  return {
    in:         'bg-green-500/20 text-green-400',
    out:        'bg-red-500/20 text-red-400',
    adjustment: 'bg-blue-500/20 text-blue-400',
  }[type] ?? 'bg-bc-surface text-bc-muted'
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('pt-MZ', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

// ─── Load ─────────────────────────────────────────────────
onMounted(async () => {
  try {
    const { data } = await axios.get('/store/products', { params: { per_page: 100 } })
    products.value = data.data ?? data
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
