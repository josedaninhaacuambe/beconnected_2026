<template>
  <div class="p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-bc-light flex items-center gap-2">⚡ Queima de Stock</h1>
        <p class="text-bc-muted text-sm mt-1">Lança promoções relâmpago — todos os clientes são notificados instantaneamente.</p>
      </div>
      <button @click="showLaunchModal = true" class="btn-gold px-4 py-2 text-sm">+ Lançar Queima</button>
    </div>

    <!-- Queimas activas -->
    <div class="card-african overflow-hidden mb-6">
      <div class="px-5 py-3 bg-bc-surface-2 border-b border-bc-gold/10">
        <h2 class="text-bc-gold font-semibold text-sm">Queimas Activas</h2>
      </div>

      <div v-if="loadingActive" class="p-5 space-y-3">
        <div v-for="i in 3" :key="i" class="skeleton h-16 rounded-xl"></div>
      </div>

      <div v-else-if="activeFlashSales.length === 0" class="p-8 text-center text-bc-muted text-sm">
        Nenhuma queima activa. Lança a primeira agora!
      </div>

      <div v-else class="divide-y divide-bc-gold/10">
        <div v-for="p in activeFlashSales" :key="p.id" class="flex items-center gap-4 px-5 py-4">
          <div class="w-12 h-12 rounded-xl overflow-hidden bg-bc-surface-2 flex-shrink-0">
            <AppImg v-if="p.images?.[0]"
              :src="p.images[0].startsWith('http') ? p.images[0] : `/storage/${p.images[0]}`"
              class="w-full h-full object-cover" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-bc-light font-medium text-sm">{{ p.name }}</p>
            <div class="flex items-center gap-3 mt-0.5">
              <span class="text-bc-muted line-through text-xs">{{ formatMZN(p.price) }}</span>
              <span class="text-red-400 font-bold text-sm">{{ formatMZN(p.flash_price) }}</span>
              <span class="bg-red-500/20 text-red-400 text-xs px-2 py-0.5 rounded-full font-bold">
                −{{ discountPct(p) }}%
              </span>
            </div>
            <p class="text-bc-muted text-xs mt-0.5">⏱ Acaba em: <span class="text-bc-gold">{{ countdown(p.flash_until) }}</span></p>
          </div>
          <div class="text-right flex-shrink-0">
            <p class="text-bc-muted text-xs mb-1">Stock: {{ p.stock?.quantity ?? 0 }}</p>
            <button @click="cancelFlash(p)" class="text-red-400 hover:text-red-300 text-xs border border-red-400/30 px-3 py-1 rounded-lg">
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Historial / dicas -->
    <div class="card-african p-5">
      <h2 class="text-bc-gold font-semibold mb-3 text-sm">💡 Dicas para Queimas de Sucesso</h2>
      <ul class="text-bc-muted text-xs space-y-2">
        <li>🕐 <strong class="text-bc-light">Duração ideal:</strong> 2 a 6 horas cria urgência sem pressionar demasiado.</li>
        <li>💰 <strong class="text-bc-light">Desconto mínimo:</strong> Pelo menos 20% para chamar atenção dos clientes.</li>
        <li>📦 <strong class="text-bc-light">Stock suficiente:</strong> Garante que tens unidades suficientes para a procura.</li>
        <li>⏰ <strong class="text-bc-light">Melhor hora:</strong> 12h–14h (almoço) ou 19h–21h (após trabalho).</li>
        <li>🔔 <strong class="text-bc-light">Notificação imediata:</strong> Todos os clientes recebem alerta quando lanças.</li>
      </ul>
    </div>

    <!-- ─── Modal: Lançar Queima ─────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showLaunchModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70" @click.self="showLaunchModal = false">
        <div class="bg-bc-surface border border-bc-gold/30 rounded-2xl w-full max-w-lg p-6">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-bc-gold font-bold text-lg">⚡ Lançar Queima de Stock</h2>
            <button @click="showLaunchModal = false" class="text-bc-muted hover:text-bc-light">✕</button>
          </div>

          <div class="space-y-4">
            <!-- Escolher produto -->
            <div>
              <label class="text-bc-muted text-xs mb-1 block">Produto *</label>
              <select v-model="launchForm.product_id" @change="onProductSelect" class="select-african">
                <option value="">Seleccionar produto...</option>
                <option v-for="p in eligibleProducts" :key="p.id" :value="p.id">
                  {{ p.name }} — {{ formatMZN(p.price) }} (stock: {{ p.stock }})
                </option>
              </select>
            </div>

            <!-- Info do produto seleccionado -->
            <div v-if="selectedProduct" class="bg-bc-surface-2 rounded-xl p-3 text-xs">
              <div class="flex items-center gap-3">
                <AppImg v-if="selectedProduct.images?.[0]"
                  :src="selectedProduct.images[0].startsWith('http') ? selectedProduct.images[0] : `/storage/${selectedProduct.images[0]}`"
                  class="w-10 h-10 rounded-lg object-cover flex-shrink-0" />
                <div>
                  <p class="text-bc-light font-medium">{{ selectedProduct.name }}</p>
                  <p class="text-bc-muted">Preço normal: <strong class="text-white">{{ formatMZN(selectedProduct.price) }}</strong></p>
                </div>
              </div>
            </div>

            <!-- Preço de queima -->
            <div>
              <label class="text-bc-muted text-xs mb-1 block">Preço de Queima (MZN) *</label>
              <input v-model.number="launchForm.flash_price" type="number" step="0.01" min="1"
                placeholder="Ex: 150.00" class="input-african" />
              <p v-if="selectedProduct && launchForm.flash_price" class="text-xs mt-1"
                :class="launchForm.flash_price < selectedProduct.price ? 'text-green-400' : 'text-red-400'">
                {{ launchForm.flash_price < selectedProduct.price
                  ? `Desconto de ${discountPctValue}% — os clientes vão adorar!`
                  : 'O preço de queima deve ser inferior ao preço normal.' }}
              </p>
            </div>

            <!-- Duração -->
            <div>
              <label class="text-bc-muted text-xs mb-2 block">Duração da Queima *</label>
              <div class="grid grid-cols-4 gap-2 mb-2">
                <button v-for="h in [1,2,4,6]" :key="h"
                  type="button"
                  @click="setDuration(h)"
                  :class="['py-2 rounded-xl text-xs border transition',
                    selectedHours === h ? 'bg-bc-gold text-bc-dark border-bc-gold font-bold' : 'border-bc-gold/30 text-bc-muted hover:border-bc-gold']">
                  {{ h }}h
                </button>
              </div>
              <input v-model="launchForm.flash_until" type="datetime-local" class="input-african text-xs" />
            </div>

            <!-- Mensagem extra (opcional) -->
            <div>
              <label class="text-bc-muted text-xs mb-1 block">Mensagem extra (opcional)</label>
              <input v-model="launchForm.message" type="text" placeholder="Ex: Só enquanto durar o stock!" class="input-african" maxlength="200" />
            </div>

            <p v-if="launchError" class="text-red-400 text-sm bg-red-900/20 rounded-lg p-2">{{ launchError }}</p>

            <!-- Preview da notificação -->
            <div v-if="selectedProduct && launchForm.flash_price && launchForm.flash_price < selectedProduct.price"
              class="bg-bc-dark border border-bc-gold/20 rounded-xl p-3 text-xs">
              <p class="text-bc-gold font-semibold mb-1">🔔 Preview da notificação enviada:</p>
              <p class="text-bc-light font-medium">⚡ Queima de Stock — {{ storeName }}</p>
              <p class="text-bc-muted mt-0.5">{{ selectedProduct.name }} com {{ discountPctValue }}% de desconto! {{ formatMZN(selectedProduct.price) }} → {{ formatMZN(launchForm.flash_price) }} MZN.</p>
            </div>

            <div class="flex gap-3">
              <button @click="showLaunchModal = false" class="btn-ghost flex-1 py-3 text-sm">Cancelar</button>
              <button @click="launchFlashSale" :disabled="launching"
                class="btn-gold flex-1 py-3 text-sm">
                {{ launching ? 'A lançar...' : '⚡ Lançar Agora!' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const loadingActive   = ref(true)
const showLaunchModal = ref(false)
const launching       = ref(false)
const launchError     = ref('')
const storeName       = ref('')

const activeFlashSales  = ref([])
const eligibleProducts  = ref([])
const selectedProduct   = ref(null)
const selectedHours     = ref(null)

const launchForm = reactive({
  product_id:  '',
  flash_price: '',
  flash_until: '',
  message:     '',
})

// Countdown timer
let tickInterval = null
const now = ref(new Date())

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

function discountPct(p) {
  if (!p.price || !p.flash_price) return 0
  return Math.round(((p.price - p.flash_price) / p.price) * 100)
}

const discountPctValue = computed(() => {
  if (!selectedProduct.value || !launchForm.flash_price) return 0
  return Math.round(((selectedProduct.value.price - launchForm.flash_price) / selectedProduct.value.price) * 100)
})

function countdown(until) {
  const diff = Math.max(0, new Date(until) - now.value)
  if (diff === 0) return 'Expirado'
  const h = Math.floor(diff / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  return `${h}h ${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`
}

function setDuration(hours) {
  selectedHours.value = hours
  const until = new Date(Date.now() + hours * 3600000)
  launchForm.flash_until = until.toISOString().slice(0, 16)
}

function onProductSelect() {
  selectedProduct.value = eligibleProducts.value.find(p => p.id == launchForm.product_id) || null
  launchForm.flash_price = ''
}

async function loadActive() {
  loadingActive.value = true
  try {
    const { data } = await axios.get('/store/flash-sales')
    activeFlashSales.value = data
  } finally {
    loadingActive.value = false
  }
}

async function loadEligible() {
  const { data } = await axios.get('/store/flash-sales/eligible')
  eligibleProducts.value = data
}

async function loadStoreName() {
  try {
    const { data } = await axios.get('/store')
    storeName.value = data.name
  } catch {}
}

async function launchFlashSale() {
  launchError.value = ''
  if (!launchForm.product_id || !launchForm.flash_price || !launchForm.flash_until) {
    launchError.value = 'Preenche todos os campos obrigatórios.'
    return
  }
  launching.value = true
  try {
    const { data } = await axios.post('/store/flash-sales/launch', launchForm)
    showLaunchModal.value = false
    launchForm.product_id  = ''
    launchForm.flash_price = ''
    launchForm.flash_until = ''
    launchForm.message     = ''
    selectedProduct.value  = null
    selectedHours.value    = null
    await loadActive()
    await loadEligible()
    alert(`✅ ${data.message}`)
  } catch (e) {
    const errs = e.response?.data?.errors
    launchError.value = errs
      ? Object.values(errs).flat().join(' ')
      : (e.response?.data?.message || 'Erro ao lançar queima.')
  } finally {
    launching.value = false
  }
}

async function cancelFlash(product) {
  if (!confirm(`Cancelar a queima de "${product.name}"?`)) return
  await axios.delete(`/store/flash-sales/${product.id}`)
  await loadActive()
  await loadEligible()
}

onMounted(async () => {
  await Promise.all([loadActive(), loadEligible(), loadStoreName()])
  tickInterval = setInterval(() => { now.value = new Date() }, 1000)
})

onUnmounted(() => clearInterval(tickInterval))
</script>
