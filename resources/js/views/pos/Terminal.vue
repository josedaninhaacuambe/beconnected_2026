<template>
  <div class="flex h-full" style="height: calc(100vh - 88px);">

    <!-- ── ESQUERDA: Produtos ─────────────────────────────────────────────── -->
    <div class="flex-1 flex flex-col overflow-hidden border-r border-gray-200">
      <!-- Pesquisa -->
      <div class="p-3 border-b border-gray-200 bg-white">
        <input
          ref="searchInput"
          v-model="search"
          @input="filterProducts"
          type="text"
          placeholder="🔍 Pesquisar produto, SKU ou código de barras..."
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-bc-gold"
        />
      </div>

      <!-- Grid de produtos -->
      <div class="flex-1 overflow-y-auto p-3">
        <div v-if="loadingProducts" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
          <div v-for="i in 8" :key="i" class="skeleton h-28 rounded-xl"></div>
        </div>

        <div v-else-if="!filtered.length" class="flex flex-col items-center justify-center h-full text-gray-400">
          <span class="text-4xl mb-2">📦</span>
          <p class="text-sm">Nenhum produto encontrado</p>
        </div>

        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
          <button
            v-for="p in filtered" :key="p.id"
            @click="addToCart(p)"
            class="relative flex flex-col items-center text-center p-3 bg-white rounded-xl border-2 border-transparent hover:border-bc-gold hover:shadow-md transition active:scale-95"
            :class="(p.stock?.quantity ?? 0) <= 0 ? 'opacity-40 cursor-not-allowed' : ''"
            :disabled="(p.stock?.quantity ?? 0) <= 0"
          >
            <!-- Imagem ou placeholder -->
            <div class="w-full h-16 rounded-lg overflow-hidden bg-gray-100 mb-2 flex items-center justify-center">
              <AppImg v-if="p.image" :src="p.image.startsWith('http') ? p.image : '/storage/' + p.image" class="w-full h-full object-cover" />
              <span v-else class="text-2xl">🛍️</span>
            </div>
            <p class="text-xs font-semibold text-gray-800 line-clamp-2 leading-tight mb-1">{{ p.name }}</p>
            <p class="text-sm font-black" style="color:#F07820;">{{ fmt(p.price) }}</p>
            <!-- Stock baixo -->
            <span v-if="p.stock && p.stock.quantity <= 5 && p.stock.quantity > 0"
              class="absolute top-1 right-1 text-[9px] bg-yellow-100 text-yellow-700 font-bold px-1 py-0.5 rounded">
              {{ p.stock.quantity }} restam
            </span>
            <span v-if="(p.stock?.quantity ?? 0) <= 0"
              class="absolute inset-0 flex items-center justify-center bg-white/70 rounded-xl text-xs font-bold text-red-500">
              Sem stock
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- ── DIREITA: Carrinho ──────────────────────────────────────────────── -->
    <div class="w-72 lg:w-80 flex flex-col bg-white">
      <!-- Cabeçalho carrinho -->
      <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <p class="font-bold text-gray-800">🛒 Carrinho</p>
        <button v-if="cart.length" @click="clearCart" class="text-xs text-red-400 hover:text-red-600">Limpar</button>
      </div>

      <!-- Items -->
      <div class="flex-1 overflow-y-auto px-3 py-2 space-y-2">
        <div v-if="!cart.length" class="flex flex-col items-center justify-center h-full text-gray-400">
          <span class="text-3xl mb-1">🛒</span>
          <p class="text-xs">Carrinho vazio</p>
        </div>

        <div v-for="item in cart" :key="item.product_id"
          class="flex items-center gap-2 p-2 bg-gray-50 rounded-xl">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-gray-800 truncate">{{ item.product_name }}</p>
            <p class="text-xs" style="color:#F07820;">{{ fmt(item.unit_price) }}</p>
          </div>
          <!-- Qty -->
          <div class="flex items-center gap-1">
            <button @click="changeQty(item, -1)" class="w-6 h-6 rounded-lg bg-gray-200 text-gray-600 text-xs font-bold hover:bg-gray-300 transition">−</button>
            <span class="w-6 text-center text-xs font-bold">{{ item.quantity }}</span>
            <button @click="changeQty(item, 1)" class="w-6 h-6 rounded-lg text-white text-xs font-bold transition" style="background:#F07820;">+</button>
          </div>
          <p class="text-xs font-bold text-gray-800 w-16 text-right">{{ fmt(item.subtotal) }}</p>
          <button @click="removeItem(item)" class="text-red-400 hover:text-red-600 ml-1">✕</button>
        </div>
      </div>

      <!-- Totais -->
      <div class="px-4 py-3 border-t border-gray-100 space-y-2">
        <div class="flex justify-between text-sm text-gray-600">
          <span>Subtotal</span><span>{{ fmt(subtotal) }}</span>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-gray-600">Desconto</span>
          <input v-model.number="discount" type="number" min="0"
            class="w-20 text-right border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:border-bc-gold" />
        </div>
        <div class="flex justify-between font-black text-base">
          <span>TOTAL</span>
          <span style="color:#F07820;">{{ fmt(total) }}</span>
        </div>

        <!-- Método de pagamento -->
        <div class="grid grid-cols-3 gap-1.5 pt-1">
          <button v-for="m in payMethods" :key="m.value" @click="payMethod = m.value"
            class="py-1.5 rounded-xl text-xs font-bold border-2 transition"
            :class="payMethod === m.value ? 'border-bc-gold text-bc-gold bg-bc-gold/10' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
            {{ m.icon }} {{ m.label }}
          </button>
        </div>

        <!-- Cliente (opcional) -->
        <input v-model="customerName" type="text" placeholder="Nome do cliente (opcional)"
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-bc-gold" />

        <!-- Botão vender -->
        <button
          @click="finalizeSale"
          :disabled="!cart.length || processing"
          class="w-full py-3 rounded-xl font-black text-white text-sm transition hover:opacity-90 active:scale-95 disabled:opacity-40"
          style="background:#F07820;"
        >
          {{ processing ? 'A registar...' : isOnline ? '✅ Confirmar Venda' : '📥 Guardar Offline' }}
        </button>
      </div>
    </div>

    <!-- ── Recibo / Modal de sucesso ──────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="receipt" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
          <div class="text-5xl mb-3">✅</div>
          <h3 class="font-black text-xl text-gray-800 mb-1">Venda Registada!</h3>
          <p class="text-sm text-gray-500 mb-4">
            {{ isOnline ? 'Sincronizada com o servidor.' : '⚠️ Guardada localmente. Será sincronizada quando online.' }}
          </p>
          <div class="bg-gray-50 rounded-xl p-3 text-left text-sm space-y-1 mb-4">
            <div class="flex justify-between"><span class="text-gray-500">Total</span><span class="font-bold" style="color:#F07820;">{{ fmt(receipt.total) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Pagamento</span><span class="font-semibold">{{ receipt.payment_method }}</span></div>
            <div v-if="receipt.customer_name" class="flex justify-between"><span class="text-gray-500">Cliente</span><span>{{ receipt.customer_name }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Hora</span><span>{{ formatTime(receipt.sale_at) }}</span></div>
          </div>
          <div class="flex gap-3">
            <button @click="printReceipt" class="flex-1 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">🖨️ Imprimir</button>
            <button @click="newSale" class="flex-1 py-2 rounded-xl text-white font-bold text-sm transition" style="background:#F07820;">Nova Venda</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useOfflinePos, cacheProducts, getCachedProducts, savePendingSale } from '@/composables/useOfflinePos'

const { isOnline, pendingCount, trySyncNow } = useOfflinePos()

const allProducts = ref([])
const filtered    = ref([])
const search      = ref('')
const cart        = ref([])
const discount    = ref(0)
const payMethod   = ref('cash')
const customerName = ref('')
const processing  = ref(false)
const receipt     = ref(null)
const loadingProducts = ref(true)

const payMethods = [
  { value: 'cash',  icon: '💵', label: 'Dinheiro' },
  { value: 'mpesa', icon: '📱', label: 'M-Pesa' },
  { value: 'emola', icon: '📲', label: 'eMola' },
]

const subtotal = computed(() => cart.value.reduce((s, i) => s + i.subtotal, 0))
const total    = computed(() => Math.max(0, subtotal.value - (discount.value || 0)))

function fmt(v) {
  return new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' }).format(v ?? 0)
}

function filterProducts() {
  const q = search.value.toLowerCase().trim()
  if (!q) { filtered.value = allProducts.value; return }
  filtered.value = allProducts.value.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.sku && p.sku.toLowerCase().includes(q)) ||
    (p.barcode && p.barcode.toLowerCase().includes(q))
  )
}

function addToCart(product) {
  const existing = cart.value.find(i => i.product_id === product.id)
  if (existing) {
    existing.quantity++
    existing.subtotal = existing.unit_price * existing.quantity
  } else {
    cart.value.push({
      product_id:   product.id,
      product_name: product.name,
      product_sku:  product.sku,
      unit_price:   parseFloat(product.price),
      quantity:     1,
      subtotal:     parseFloat(product.price),
    })
  }
}

function changeQty(item, delta) {
  item.quantity += delta
  if (item.quantity <= 0) { removeItem(item); return }
  item.subtotal = item.unit_price * item.quantity
}

function removeItem(item) {
  cart.value = cart.value.filter(i => i.product_id !== item.product_id)
}

function clearCart() {
  cart.value = []
  discount.value = 0
  customerName.value = ''
}

function generateLocalId() {
  return `local_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`
}

async function finalizeSale() {
  if (!cart.value.length) return
  processing.value = true

  const sale = {
    local_id:       generateLocalId(),
    total:          total.value,
    subtotal:       subtotal.value,
    discount:       discount.value || 0,
    payment_method: payMethod.value,
    customer_name:  customerName.value || null,
    sale_at:        new Date().toISOString(),
    items:          cart.value.map(i => ({ ...i })),
  }

  try {
    if (isOnline.value) {
      await axios.post('/api/pos/sync', { sales: [sale] })
    } else {
      await savePendingSale(sale)
      pendingCount.value++
    }
    receipt.value = sale
    clearCart()
    search.value = ''
    filterProducts()
  } catch (e) {
    // Se falhar online, guardar offline
    await savePendingSale(sale)
    receipt.value = sale
    clearCart()
  } finally {
    processing.value = false
  }
}

function newSale() {
  receipt.value = null
  if (isOnline.value) trySyncNow()
}

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('pt-MZ', { hour: '2-digit', minute: '2-digit' })
}

function printReceipt() {
  window.print()
}

async function loadProducts() {
  loadingProducts.value = true
  try {
    if (isOnline.value) {
      const { data } = await axios.get('/api/pos/products')
      allProducts.value = data
      await cacheProducts(data)
    } else {
      allProducts.value = await getCachedProducts()
    }
  } catch {
    allProducts.value = await getCachedProducts()
  } finally {
    filtered.value = allProducts.value
    loadingProducts.value = false
  }
}

onMounted(loadProducts)
</script>

<style>
@media print {
  body > *:not(#pos-receipt) { display: none !important; }
}
</style>
