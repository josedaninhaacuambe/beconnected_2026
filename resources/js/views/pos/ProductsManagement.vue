<template>
  <div class="flex h-full flex-col bg-gray-50 overflow-hidden">

    <!-- Banner offline -->
    <div v-if="!isOnline" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border-b border-amber-200">
      <span>📵</span>
      <span>Modo offline — edições e novos produtos serão sincronizados quando houver ligação</span>
      <span v-if="pendingProductCount > 0" class="ml-auto bg-amber-200 text-amber-800 rounded-full px-2 py-0.5">
        {{ pendingProductCount }} pendente{{ pendingProductCount > 1 ? 's' : '' }}
      </span>
    </div>

    <!-- ── Header ─────────────────────────────────────────────────────────────── -->
    <div class="flex-shrink-0 px-4 py-3 bg-white border-b border-gray-200 flex flex-wrap items-center gap-3">
      <h2 class="font-bold text-gray-800 text-sm">📦 Gestão de Produtos</h2>

      <!-- Filtros -->
      <div class="flex items-center gap-2 flex-1 min-w-0">
        <input v-model="searchQ" type="text" placeholder="🔍 Pesquisar..."
          class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-bc-gold w-40 sm:w-56" />

        <select v-model="filterCat"
          class="px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-bc-gold">
          <option value="">📦 Todas</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>

        <select v-model="filterStatus"
          class="px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-bc-gold">
          <option value="active">Activos</option>
          <option value="inactive">Removidos</option>
          <option value="all">Todos</option>
        </select>
      </div>

      <button v-if="canManage" @click="openCreate"
        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-bold text-white shrink-0"
        style="background:#F07820;">
        ➕ Novo
      </button>
    </div>

    <!-- ── Lista de produtos ───────────────────────────────────────────────────── -->
    <div class="flex-1 overflow-y-auto p-4">
      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div v-for="i in 6" :key="i" class="bg-white rounded-xl h-48 animate-pulse"></div>
      </div>

      <div v-else-if="!paged.length" class="flex flex-col items-center justify-center h-64 text-gray-400">
        <span class="text-5xl mb-3">📦</span>
        <p class="text-sm font-semibold">Nenhum produto encontrado</p>
        <button v-if="canManage" @click="openCreate"
          class="mt-3 px-4 py-2 rounded-lg text-sm font-bold text-white" style="background:#F07820;">
          ➕ Adicionar primeiro produto
        </button>
      </div>

      <div v-else>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="p in paged" :key="p.id"
            class="bg-white rounded-xl shadow-sm border flex flex-col overflow-hidden transition"
            :class="!p.is_active ? 'opacity-60 border-red-200' : 'border-transparent hover:shadow-md'">

            <!-- Imagem -->
            <div class="w-full h-28 bg-gray-100 relative overflow-hidden">
              <AppImg
                :src="p.images?.[0] && !p.images[0].startsWith('http') ? '/storage/' + p.images[0] : ''"
                type="product" class="w-full h-full object-cover" />
              <span v-if="!p.is_active"
                class="absolute inset-0 flex items-center justify-center bg-red-50/80 text-red-600 text-xs font-bold">
                🚫 Removido
              </span>
              <span v-else-if="p.availability === 'pos'"
                class="absolute top-1 right-1 bg-blue-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">
                POS only
              </span>
            </div>

            <!-- Info -->
            <div class="p-3 flex-1 flex flex-col gap-1.5">
              <p class="font-bold text-gray-800 text-sm line-clamp-1">{{ p.name }}</p>
              <p class="text-[10px] text-gray-400">SKU: {{ p.sku || 'N/A' }}
                <span v-if="p.barcode"> · CB: {{ p.barcode }}</span>
              </p>

              <!-- Preço + Stock -->
              <div class="flex justify-between items-end text-sm">
                <div>
                  <p class="text-[10px] text-gray-400">Preço venda</p>
                  <p class="font-black" style="color:#F07820;">{{ fmt(p.price) }}</p>
                  <p v-if="p.cost_price > 0" class="text-[10px] text-gray-400">
                    Custo: {{ fmt(p.cost_price) }}
                  </p>
                </div>
                <div class="text-right">
                  <p class="text-[10px] text-gray-400">Stock</p>
                  <!-- Edição inline de stock -->
                  <div v-if="stockEdit[p.id]" class="flex items-center gap-1">
                    <input v-model.number="stockEdit[p.id].qty" type="number" min="0"
                      class="w-14 px-1 py-0.5 text-xs border border-bc-gold rounded text-center"
                      @keyup.enter="saveStock(p)" />
                    <button @click="saveStock(p)" class="text-green-600 text-xs">✓</button>
                    <button @click="delete stockEdit[p.id]" class="text-gray-400 text-xs">✕</button>
                  </div>
                  <div v-else class="flex items-center gap-1 justify-end">
                    <p class="font-bold text-sm"
                      :class="(p.stock?.quantity ?? 0) > 0 ? 'text-green-600' : 'text-red-500'">
                      {{ p.stock?.quantity ?? 0 }} {{ p.weight_unit || 'un' }}
                    </p>
                    <button v-if="canManage && p.is_active" @click="startStockEdit(p)"
                      class="text-blue-500 text-xs opacity-60 hover:opacity-100" title="Ajustar stock">
                      ✏️
                    </button>
                  </div>
                </div>
              </div>

              <!-- Acções -->
              <div v-if="canManage" class="flex gap-1.5 pt-2 border-t border-gray-100 mt-auto">
                <!-- Histórico -->
                <button @click="toggleHistory(p)"
                  class="flex-1 py-1 rounded-lg text-[10px] font-bold border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
                  📋 Histórico
                </button>
                <template v-if="p.is_active">
                  <button @click="openEdit(p)"
                    class="flex-1 py-1 rounded-lg text-[10px] font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
                    ✏️ Editar
                  </button>
                  <button @click="confirmRemove(p)"
                    class="flex-1 py-1 rounded-lg text-[10px] font-bold bg-red-50 text-red-600 hover:bg-red-100 transition">
                    🗑️ Remover
                  </button>
                </template>
                <button v-else @click="restoreProduct(p)"
                  class="flex-1 py-1 rounded-lg text-[10px] font-bold bg-green-50 text-green-700 hover:bg-green-100 transition">
                  ♻️ Restaurar
                </button>
              </div>
            </div>

            <!-- Histórico expandido -->
            <div v-if="historyOpen[p.id]" class="border-t border-gray-100 bg-gray-50 px-3 py-2">
              <p class="text-[10px] font-bold text-gray-500 mb-1.5">Histórico de alterações</p>
              <div v-if="loadingHistory[p.id]" class="text-[10px] text-gray-400">A carregar...</div>
              <div v-else-if="!histories[p.id]?.length" class="text-[10px] text-gray-400">Sem registos.</div>
              <div v-else class="space-y-1 max-h-40 overflow-y-auto">
                <div v-for="h in histories[p.id]" :key="h.id"
                  class="text-[10px] text-gray-600 flex gap-2 items-start">
                  <span class="text-gray-400 shrink-0">{{ fmtDate(h.created_at) }}</span>
                  <span class="shrink-0 font-semibold">{{ fieldLabel(h.field) }}</span>
                  <span class="text-red-400 line-through shrink-0">{{ formatVal(h.field, h.old_value) }}</span>
                  <span class="text-gray-400">→</span>
                  <span class="text-green-700 font-semibold shrink-0">{{ formatVal(h.field, h.new_value) }}</span>
                  <span v-if="h.reason" class="text-gray-400 italic truncate">· {{ h.reason }}</span>
                  <span class="text-gray-400 shrink-0">por {{ h.changed_by?.name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Paginação -->
        <div v-if="totalPages > 1" class="mt-4 flex items-center justify-center gap-2">
          <button @click="page = Math.max(1, page - 1)" :disabled="page === 1"
            class="px-3 py-1 rounded border border-gray-200 text-xs font-semibold disabled:opacity-40 hover:bg-gray-50">
            ← Anterior
          </button>
          <span class="text-xs text-gray-600 font-semibold">{{ page }} / {{ totalPages }}</span>
          <button @click="page = Math.min(totalPages, page + 1)" :disabled="page === totalPages"
            class="px-3 py-1 rounded border border-gray-200 text-xs font-semibold disabled:opacity-40 hover:bg-gray-50">
            Próxima →
          </button>
        </div>
      </div>
    </div>

    <!-- ── Modal remover ───────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="removeTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-5">
          <p class="font-bold text-gray-800 mb-1">🗑️ Remover produto?</p>
          <p class="text-sm text-gray-500 mb-3">
            <strong>{{ removeTarget.name }}</strong> ficará inactivo e não aparecerá no terminal de vendas.
            O registo será mantido no histórico.
          </p>
          <div>
            <label class="text-xs font-semibold text-gray-500">Motivo (opcional)</label>
            <input v-model="removeReason" type="text" placeholder="Ex: produto descontinuado"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
          </div>
          <div class="flex gap-2 mt-4">
            <button @click="removeTarget = null; removeReason = ''"
              class="flex-1 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600">
              Cancelar
            </button>
            <button @click="doRemove" :disabled="removing"
              class="flex-1 py-2 rounded-xl text-sm font-bold text-white disabled:opacity-50"
              style="background:#dc2626;">
              {{ removing ? '⏳...' : '🗑️ Remover' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Modal editar / criar ────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-white px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-bold text-gray-800">{{ formId ? '✏️ Editar Produto' : '➕ Novo Produto' }}</h3>
            <button @click="showForm = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
          </div>

          <div class="p-5 space-y-4">
            <!-- Foto -->
            <div>
              <label class="text-xs font-bold text-gray-600">Foto</label>
              <div class="mt-1 border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-bc-gold transition"
                @click="$refs.imgInput.click()">
                <input ref="imgInput" type="file" accept="image/*" class="hidden" @change="onImage" />
                <img v-if="imgPreview" :src="imgPreview" class="w-24 h-24 object-cover rounded-lg mx-auto" />
                <span v-else class="text-sm text-gray-400">📷 Clique para adicionar imagem</span>
              </div>
            </div>

            <!-- Nome -->
            <div>
              <label class="text-xs font-bold text-gray-600">Nome *</label>
              <input v-model="form.name" type="text" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
            </div>

            <!-- SKU / Barcode -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-bold text-gray-600">SKU</label>
                <input v-model="form.sku" type="text" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
              </div>
              <div>
                <label class="text-xs font-bold text-gray-600">Cód. barras</label>
                <input v-model="form.barcode" type="text" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
              </div>
            </div>

            <!-- Categoria -->
            <div>
              <label class="text-xs font-bold text-gray-600">Categoria</label>
              <select v-model.number="form.product_category_id" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold">
                <option :value="null">-- Sem categoria --</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <!-- Preços -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-bold text-gray-600">Preço venda (MZN) *</label>
                <input v-model.number="form.price" type="number" min="0" step="0.01"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
              </div>
              <div>
                <label class="text-xs font-bold text-gray-600">Preço custo (MZN)</label>
                <input v-model.number="form.cost_price" type="number" min="0" step="0.01"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
              </div>
            </div>

            <!-- Stock (só ao criar) -->
            <div v-if="!formId" class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-bold text-gray-600">Stock inicial</label>
                <input v-model.number="form.stock_quantity" type="number" min="0"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
              </div>
              <div>
                <label class="text-xs font-bold text-gray-600">Stock mínimo</label>
                <input v-model.number="form.stock_min" type="number" min="0"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
              </div>
            </div>

            <!-- Pesável -->
            <div class="border border-gray-100 rounded-xl p-3">
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input v-model="form.is_weighable" type="checkbox" class="rounded" />
                <span class="font-semibold text-gray-700">⚖️ Vendido por peso / volume</span>
              </label>
              <select v-if="form.is_weighable" v-model="form.weight_unit"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-2 focus:outline-none focus:border-bc-gold">
                <option value="kg">kg</option>
                <option value="g">g</option>
                <option value="l">l</option>
                <option value="ml">ml</option>
              </select>
            </div>

            <!-- Disponibilidade -->
            <div>
              <label class="text-xs font-bold text-gray-600">Disponibilidade</label>
              <select v-model="form.availability"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold">
                <option value="both">Loja virtual + POS</option>
                <option value="virtual_store">Apenas loja virtual</option>
                <option value="pos">Apenas POS</option>
              </select>
            </div>

            <!-- Motivo (edição) -->
            <div v-if="formId">
              <label class="text-xs font-bold text-gray-600">Motivo da alteração</label>
              <input v-model="form.reason" type="text" placeholder="Ex: actualização de preço"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
            </div>

            <div v-if="formError" class="text-red-500 text-sm">{{ formError }}</div>
          </div>

          <div class="sticky bottom-0 bg-white px-5 py-3 border-t border-gray-100 flex gap-2">
            <button @click="showForm = false"
              class="flex-1 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600">
              Cancelar
            </button>
            <button @click="saveProduct" :disabled="saving || !form.name || !form.price"
              class="flex-1 py-2 rounded-xl text-sm font-bold text-white disabled:opacity-50"
              style="background:#F07820;">
              {{ saving ? '⏳ A guardar...' : (formId ? '✅ Guardar' : '✅ Criar') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import AppImg from '@/components/AppImg.vue'
import {
  useOfflinePos,
  savePendingProduct, getPendingProducts,
  cacheCategories, getCachedCategories,
} from '@/composables/useOfflinePos'

const auth = useAuthStore()
const { isOnline, pendingProductCount, refreshPendingCount } = useOfflinePos()
const offlineProducts = ref([]) // produtos criados offline ainda não sincronizados

// ── State ──────────────────────────────────────────────────────────────────────
const products      = ref([])
const categories    = ref([])
const loading       = ref(true)
const page          = ref(1)
const perPage       = 9
const searchQ       = ref('')
const filterCat     = ref('')
const filterStatus  = ref('active')

// Histórico por produto
const historyOpen    = reactive({})
const loadingHistory = reactive({})
const histories      = reactive({})

// Edição inline de stock
const stockEdit = reactive({})

// Remover produto
const removeTarget = ref(null)
const removeReason = ref('')
const removing     = ref(false)

// Formulário
const showForm  = ref(false)
const formId    = ref(null)
const saving    = ref(false)
const formError = ref('')
const imgPreview = ref(null)
const imgFile    = ref(null)
const form = ref(blankForm())

function blankForm() {
  return {
    name: '', sku: '', barcode: '', description: '',
    price: 0, cost_price: 0, product_category_id: null,
    stock_quantity: 0, stock_min: 5,
    is_weighable: false, weight_unit: 'kg',
    availability: 'both', selling_modes: ['unit'], reason: '',
  }
}

// ── Permissões ─────────────────────────────────────────────────────────────────
const canManage = computed(() => {
  const role = auth.posRole
  return role === 'owner' || role === 'manager'
})

// ── Filtro + paginação ─────────────────────────────────────────────────────────
const filtered = computed(() => {
  // Produtos do servidor + produtos criados offline ainda pendentes
  let list = [...products.value, ...offlineProducts.value]

  if (filterStatus.value === 'active')   list = list.filter(p => p.is_active)
  if (filterStatus.value === 'inactive') list = list.filter(p => !p.is_active)

  if (filterCat.value) {
    list = list.filter(p => (p.product_category_id ?? p.category_id) == filterCat.value)
  }

  const q = searchQ.value.toLowerCase().trim()
  if (q) {
    list = list.filter(p =>
      p.name.toLowerCase().includes(q) ||
      (p.sku  && p.sku.toLowerCase().includes(q)) ||
      (p.barcode && p.barcode.toLowerCase().includes(q))
    )
  }

  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paged = computed(() => {
  const start = (page.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

// ── Formatos ───────────────────────────────────────────────────────────────────
const _fmt = new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' })
function fmt(v) { return _fmt.format(v ?? 0) }

function fmtDate(dt) {
  return new Date(dt).toLocaleDateString('pt-MZ', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const fieldLabels = {
  price: 'Preço venda', cost_price: 'Preço custo', compare_price: 'Preço riscado',
  name: 'Nome', availability: 'Disponibilidade', is_active: 'Estado',
}
function fieldLabel(f) { return fieldLabels[f] ?? f }

function formatVal(field, val) {
  if (field === 'is_active') return val === '1' || val === 'true' ? 'Activo' : 'Inactivo'
  if (['price', 'cost_price', 'compare_price'].includes(field)) return fmt(parseFloat(val))
  if (field === 'availability') return { both: 'Ambos', pos: 'POS', virtual_store: 'Virtual' }[val] ?? val
  return val ?? '—'
}

// ── Carregar dados ─────────────────────────────────────────────────────────────
async function load() {
  loading.value = true

  // Mostrar cache imediatamente (categorias + produtos offline pendentes)
  const [cachedCats, pendingProds] = await Promise.all([
    getCachedCategories(),
    getPendingProducts(),
  ])
  if (cachedCats)      categories.value = cachedCats.value
  offlineProducts.value = pendingProds.map(p => ({
    ...p, id: p.local_id, is_active: true, _offline: true,
    stock: { quantity: p.stock_quantity ?? 0 },
  }))

  if (isOnline.value) {
    try {
      const [pRes, cRes] = await Promise.all([
        axios.get('/pos/products/manage'),
        axios.get('/pos/categories'),
      ])
      products.value   = pRes.data || []
      categories.value = cRes.data || []
      await cacheCategories(cRes.data || [])
    } catch (e) {
      console.error('Erro ao carregar produtos:', e.response?.data ?? e.message)
    }
  } else if (!products.value.length) {
    // Offline sem dados do servidor: mostrar só os pendentes
    products.value = []
  }

  loading.value = false
}

// ── Histórico ──────────────────────────────────────────────────────────────────
async function toggleHistory(p) {
  if (historyOpen[p.id]) {
    historyOpen[p.id] = false
    return
  }
  historyOpen[p.id] = true
  if (histories[p.id]) return // já carregado

  loadingHistory[p.id] = true
  try {
    const { data } = await axios.get(`/pos/products/${p.id}/history`)
    histories[p.id] = data
  } catch {
    histories[p.id] = []
  } finally {
    loadingHistory[p.id] = false
  }
}

// ── Stock inline ───────────────────────────────────────────────────────────────
function startStockEdit(p) {
  stockEdit[p.id] = { qty: p.stock?.quantity ?? 0 }
}

async function saveStock(p) {
  const newQty = stockEdit[p.id]?.qty
  if (newQty === undefined || newQty < 0) return
  try {
    await axios.post('/pos/stock/movement', {
      product_id: p.id,
      type: 'adjustment',
      quantity: newQty,
      reason: 'Ajuste via gestão de produtos',
    })
    if (!p.stock) p.stock = {}
    p.stock.quantity = newQty
    delete stockEdit[p.id]
  } catch (e) {
    alert('Erro ao ajustar stock: ' + (e.response?.data?.message ?? e.message))
  }
}

// ── Remover produto ────────────────────────────────────────────────────────────
function confirmRemove(p) {
  removeTarget.value = p
  removeReason.value = ''
}

async function doRemove() {
  removing.value = true
  try {
    await axios.delete(`/pos/products/${removeTarget.value.id}`, {
      data: { reason: removeReason.value || 'Removido via POS' }
    })
    // Marca como inactivo localmente (sem recarregar tudo)
    const idx = products.value.findIndex(p => p.id === removeTarget.value.id)
    if (idx !== -1) products.value[idx].is_active = false
    // Limpa histórico em cache para este produto
    delete histories[removeTarget.value.id]
    removeTarget.value = null
  } catch (e) {
    alert('Erro: ' + (e.response?.data?.message ?? e.message))
  } finally {
    removing.value = false
  }
}

async function restoreProduct(p) {
  try {
    await axios.put(`/pos/products/${p.id}`, { is_active: true, reason: 'Restaurado via POS' })
    p.is_active = true
    delete histories[p.id]
  } catch (e) {
    alert('Erro: ' + (e.response?.data?.message ?? e.message))
  }
}

// ── Criar / editar produto ─────────────────────────────────────────────────────
function openCreate() {
  formId.value   = null
  imgPreview.value = null
  imgFile.value  = null
  formError.value = ''
  form.value = blankForm()
  showForm.value = true
}

function openEdit(p) {
  formId.value = p.id
  imgFile.value = null
  imgPreview.value = p.images?.[0] && !p.images[0].startsWith('http')
    ? '/storage/' + p.images[0]
    : null
  formError.value = ''
  form.value = {
    name:               p.name,
    sku:                p.sku || '',
    barcode:            p.barcode || '',
    description:        p.description || '',
    price:              p.price,
    cost_price:         p.cost_price || 0,
    product_category_id: p.product_category_id ?? p.category_id ?? null,
    stock_quantity:     p.stock?.quantity ?? 0,
    stock_min:          p.stock_min ?? 5,
    is_weighable:       p.is_weighable || false,
    weight_unit:        p.weight_unit ?? 'kg',
    availability:       p.availability || 'both',
    selling_modes:      p.selling_modes || ['unit'],
    reason:             '',
  }
  showForm.value = true
}

function onImage(e) {
  const f = e.target.files?.[0]
  if (!f) return
  if (f.size > 2 * 1024 * 1024) { alert('Imagem máx. 2 MB'); return }
  imgFile.value = f
  const r = new FileReader()
  r.onload = ev => { imgPreview.value = ev.target.result }
  r.readAsDataURL(f)
}

async function saveProduct() {
  if (!form.value.name?.trim() || !form.value.price) return
  saving.value  = true
  formError.value = ''

  try {
    if (formId.value) {
      // Actualizar via POS endpoint (regista histórico automaticamente)
      const payload = { ...form.value }
      delete payload.stock_quantity
      delete payload.stock_min
      await axios.put(`/pos/products/${formId.value}`, payload)
      // Actualiza produto na lista local
      const idx = products.value.findIndex(p => p.id === formId.value)
      if (idx !== -1) Object.assign(products.value[idx], form.value)
      // Limpa histórico em cache para forçar reload
      delete histories[formId.value]
    } else if (!isOnline.value) {
      // Offline: guardar na fila (sem imagem — sincronizar depois)
      const localId = `prod_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`
      await savePendingProduct({ local_id: localId, ...form.value, created_at: new Date().toISOString() })
      await refreshPendingCount()
      await load() // actualiza a lista com offlineProducts
    } else {
      // Online: criar via store endpoint (owner/admin)
      const fd = new FormData()
      Object.entries(form.value).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') {
          fd.append(k, Array.isArray(v) ? JSON.stringify(v) : v)
        }
      })
      if (imgFile.value) fd.append('image', imgFile.value)
      await axios.post('/store/products', fd)
      await load()
    }
    showForm.value = false
  } catch (e) {
    formError.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Erro ao guardar produto.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
