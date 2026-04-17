<template>
  <div class="min-h-screen bg-bc-dark text-bc-light">

    <!-- ── Cabeçalho da loja ─────────────────────────────────────────── -->
    <div class="bg-bc-navy border-b-2 border-bc-gold sticky top-0 z-40">
      <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <!-- Logo da loja -->
          <div class="w-10 h-10 rounded-xl overflow-hidden bg-bc-gold flex-shrink-0 flex items-center justify-center">
            <AppImg v-if="store?.logo"
              :src="store.logo.startsWith('http') ? store.logo : `/storage/${store.logo}`"
              class="w-full h-full object-cover" />
            <span v-else class="text-white font-black text-lg">{{ store?.name?.charAt(0) }}</span>
          </div>
          <div class="min-w-0">
            <p class="text-xs text-bc-gold font-semibold uppercase tracking-wide">Scan & Go</p>
            <p class="text-bc-light font-bold truncate">{{ store?.name ?? '...' }}</p>
          </div>
        </div>
        <!-- Contador do carrinho -->
        <button @click="showCart = true"
          class="relative flex items-center gap-2 px-3 py-2 rounded-xl bg-bc-gold text-white text-sm font-semibold">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
          </svg>
          {{ cartCount }}
          <span v-if="cartCount > 0" class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-[10px] flex items-center justify-center">
            {{ cartCount }}
          </span>
        </button>
      </div>
    </div>

    <!-- ── Conteúdo principal ────────────────────────────────────────── -->
    <div class="max-w-2xl mx-auto px-4 py-6 space-y-6">

      <!-- Aviso de boas-vindas -->
      <div v-if="store" class="card-african p-4 border-bc-gold/40">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-full bg-bc-gold/20 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-bc-gold" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
            </svg>
          </div>
          <div>
            <p class="text-bc-light font-semibold text-sm">Bem-vindo à {{ store.name }}!</p>
            <p class="text-bc-muted text-xs mt-0.5">Aponta a câmara para o código de barras de qualquer produto para o adicionar ao teu carrinho digital.</p>
          </div>
        </div>
      </div>

      <!-- Painel do scanner ───────────────────────────────────────────── -->
      <div class="card-african overflow-hidden">
        <div class="px-4 pt-4 pb-2 flex items-center justify-between">
          <h2 class="text-bc-light font-bold text-sm">Leitor de Código de Barras</h2>
          <span v-if="scanning" class="flex items-center gap-1 text-bc-gold text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-bc-gold animate-pulse"></span> A ler...
          </span>
        </div>

        <!-- Câmara -->
        <div class="relative bg-black" style="aspect-ratio: 4/3; max-height: 300px;">
          <video ref="videoEl" playsinline muted class="w-full h-full object-cover"></video>

          <!-- Overlay de mira -->
          <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="w-56 h-32 relative">
              <!-- Cantos animados -->
              <span class="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-bc-gold rounded-tl-md"></span>
              <span class="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-bc-gold rounded-tr-md"></span>
              <span class="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-bc-gold rounded-bl-md"></span>
              <span class="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-bc-gold rounded-br-md"></span>
              <!-- Linha de scan animada -->
              <div v-if="scanning" class="absolute left-2 right-2 top-0 h-0.5 bg-bc-gold/70 scan-line"></div>
            </div>
          </div>

          <!-- Estado sem câmara -->
          <div v-if="!cameraActive && !cameraError" class="absolute inset-0 bg-bc-navy flex flex-col items-center justify-center gap-3">
            <svg class="w-12 h-12 text-bc-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
            </svg>
            <button @click="startCamera" class="btn-gold px-5 py-2 text-sm">Activar Câmara</button>
          </div>

          <!-- Erro de câmara -->
          <div v-if="cameraError" class="absolute inset-0 bg-bc-navy flex flex-col items-center justify-center gap-2 p-4">
            <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <p class="text-red-400 text-sm text-center">{{ cameraError }}</p>
            <button @click="startCamera" class="btn-gold px-4 py-1.5 text-xs">Tentar novamente</button>
          </div>
        </div>

        <!-- Input manual de código de barras -->
        <div class="p-4 border-t border-bc-gold/10">
          <p class="text-bc-muted text-xs mb-2">Ou introduz manualmente o código:</p>
          <div class="flex gap-2">
            <input v-model="manualBarcode" @keyup.enter="lookupBarcode(manualBarcode)"
              type="text" placeholder="Ex: 6001234567890"
              class="input-african flex-1 text-sm font-mono" />
            <button @click="lookupBarcode(manualBarcode)" :disabled="!manualBarcode || looking"
              class="btn-gold px-4 py-2 text-sm disabled:opacity-50">
              {{ looking ? '...' : 'Pesquisar' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Produto encontrado ──────────────────────────────────────────── -->
      <transition name="slide-up">
        <div v-if="foundProduct" class="card-african p-4 border border-bc-gold/40">
          <div class="flex gap-4">
            <!-- Imagem -->
            <div class="w-20 h-20 rounded-xl overflow-hidden bg-bc-surface-2 flex-shrink-0 flex items-center justify-center">
              <AppImg
                :src="foundProduct.images?.[0] ? (foundProduct.images[0].startsWith('http') ? foundProduct.images[0] : `/storage/${foundProduct.images[0]}`) : ''"
                type="product"
                class="w-full h-full object-cover"
              />
            </div>
            <!-- Info -->
            <div class="flex-1 min-w-0">
              <p class="text-bc-light font-bold text-base leading-tight">{{ foundProduct.name }}</p>
              <p v-if="foundProduct.category?.name" class="text-bc-muted text-xs mb-1">{{ foundProduct.category.name }}</p>
              <p class="text-bc-gold text-xl font-black">{{ formatMZN(foundProduct.price) }}</p>
              <p class="text-xs mt-0.5" :class="(foundProduct.stock?.quantity ?? 0) > 0 ? 'text-green-500' : 'text-red-400'">
                {{ (foundProduct.stock?.quantity ?? 0) > 0 ? `${foundProduct.stock.quantity} em stock` : 'Sem stock' }}
              </p>
            </div>
          </div>

          <!-- Quantidade + Adicionar -->
          <div class="flex items-center gap-3 mt-4">
            <div class="flex items-center border border-bc-gold/30 rounded-xl overflow-hidden">
              <button @click="productQty = Math.max(1, productQty - 1)"
                class="w-10 h-10 text-bc-gold hover:bg-bc-gold/10 text-xl font-bold">−</button>
              <span class="w-10 text-center text-bc-light font-bold">{{ productQty }}</span>
              <button @click="productQty++"
                class="w-10 h-10 text-bc-gold hover:bg-bc-gold/10 text-xl font-bold">+</button>
            </div>
            <button @click="addToCart(foundProduct, productQty)"
              :disabled="(foundProduct.stock?.quantity ?? 0) === 0"
              class="btn-gold flex-1 py-2.5 text-sm disabled:opacity-50">
              Adicionar ao Carrinho
            </button>
            <button @click="foundProduct = null" class="w-10 h-10 rounded-xl border border-bc-gold/20 text-bc-muted hover:text-red-400 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      </transition>

      <!-- Feedback: produto adicionado ──────────────────────────────── -->
      <transition name="fade">
        <div v-if="addedFeedback" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50
          bg-green-600 text-white px-5 py-3 rounded-2xl shadow-xl text-sm font-semibold flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
          Adicionado ao carrinho!
        </div>
      </transition>

      <!-- Resumo mini do carrinho (inline) ──────────────────────────── -->
      <div v-if="cart.length > 0" class="card-african overflow-hidden">
        <div class="px-4 py-3 flex items-center justify-between border-b border-bc-gold/10">
          <h2 class="text-bc-light font-bold text-sm">O Teu Carrinho ({{ cartCount }} itens)</h2>
          <button @click="showCart = true" class="text-bc-gold text-xs hover:underline">Ver tudo</button>
        </div>
        <div class="divide-y divide-bc-gold/10">
          <div v-for="item in cart" :key="item.product.id" class="flex items-center gap-3 px-4 py-2.5">
            <div class="w-8 h-8 rounded-lg overflow-hidden bg-bc-surface-2 flex-shrink-0 flex items-center justify-center">
              <AppImg
                :src="item.product.images?.[0] ? (item.product.images[0].startsWith('http') ? item.product.images[0] : `/storage/${item.product.images[0]}`) : ''"
                type="product"
                class="w-full h-full object-cover"
              />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-bc-light text-xs font-medium truncate">{{ item.product.name }}</p>
              <p class="text-bc-muted text-xs">{{ item.qty }} × {{ formatMZN(item.product.price) }}</p>
            </div>
            <p class="text-bc-gold text-sm font-bold flex-shrink-0">{{ formatMZN(item.product.price * item.qty) }}</p>
          </div>
        </div>
        <div class="px-4 py-3 bg-bc-surface border-t border-bc-gold/20 flex items-center justify-between">
          <p class="text-bc-muted text-sm">Total</p>
          <p class="text-bc-gold text-lg font-black">{{ formatMZN(cartTotal) }}</p>
        </div>
        <div class="px-4 py-3">
          <button @click="showCheckout = true" class="btn-gold w-full py-3 text-base font-bold">
            Pagar Agora
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Modal: Carrinho completo                                       -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showCart" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showCart = false"></div>
        <div class="relative w-full max-w-lg bg-bc-dark rounded-t-3xl sm:rounded-2xl shadow-2xl max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between px-5 py-4 border-b border-bc-gold/20">
            <h3 class="text-bc-light font-bold">Carrinho ({{ cartCount }})</h3>
            <button @click="showCart = false" class="text-bc-muted hover:text-bc-light">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="overflow-y-auto flex-1 divide-y divide-bc-gold/10">
            <div v-if="cart.length === 0" class="py-12 text-center text-bc-muted text-sm">
              O carrinho está vazio.
            </div>
            <div v-for="item in cart" :key="item.product.id" class="flex items-center gap-3 px-5 py-3">
              <div class="w-12 h-12 rounded-xl overflow-hidden bg-bc-surface-2 flex-shrink-0 flex items-center justify-center">
                <AppImg
                  :src="item.product.images?.[0] ? (item.product.images[0].startsWith('http') ? item.product.images[0] : `/storage/${item.product.images[0]}`) : ''"
                  type="product"
                  class="w-full h-full object-cover"
                />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-bc-light text-sm font-medium leading-tight">{{ item.product.name }}</p>
                <p class="text-bc-gold text-xs font-bold">{{ formatMZN(item.product.price) }}</p>
              </div>
              <!-- Qty controls -->
              <div class="flex items-center border border-bc-gold/30 rounded-lg overflow-hidden text-sm">
                <button @click="changeQty(item, -1)" class="w-8 h-8 text-bc-gold hover:bg-bc-gold/10 font-bold">−</button>
                <span class="w-8 text-center text-bc-light font-bold">{{ item.qty }}</span>
                <button @click="changeQty(item, 1)" class="w-8 h-8 text-bc-gold hover:bg-bc-gold/10 font-bold">+</button>
              </div>
              <p class="text-bc-light font-bold text-sm w-20 text-right flex-shrink-0">{{ formatMZN(item.product.price * item.qty) }}</p>
              <button @click="removeFromCart(item.product.id)" class="text-bc-muted hover:text-red-400 ml-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </div>
          <div v-if="cart.length > 0" class="px-5 py-4 border-t border-bc-gold/20 space-y-3">
            <div class="flex justify-between text-bc-light font-bold">
              <span>Total</span>
              <span class="text-bc-gold text-xl">{{ formatMZN(cartTotal) }}</span>
            </div>
            <button @click="showCart = false; showCheckout = true" class="btn-gold w-full py-3 text-base font-bold">
              Pagar Agora
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Modal: Pagamento                                               -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showCheckout" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="!paying && (showCheckout = false)"></div>
        <div class="relative w-full max-w-lg bg-bc-dark rounded-t-3xl sm:rounded-2xl shadow-2xl">
          <div class="flex items-center justify-between px-5 py-4 border-b border-bc-gold/20">
            <h3 class="text-bc-light font-bold">Pagamento</h3>
            <button v-if="!paying" @click="showCheckout = false" class="text-bc-muted hover:text-bc-light">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-5 py-5 space-y-5">
            <!-- Resumo do total -->
            <div class="bg-bc-surface rounded-2xl p-4 text-center">
              <p class="text-bc-muted text-sm">Total a pagar</p>
              <p class="text-bc-gold text-3xl font-black mt-1">{{ formatMZN(cartTotal) }}</p>
              <p class="text-bc-muted text-xs mt-1">{{ cartCount }} {{ cartCount === 1 ? 'item' : 'itens' }}</p>
            </div>

            <!-- Método de pagamento -->
            <div>
              <p class="text-bc-muted text-sm mb-2">Método de pagamento</p>
              <div class="grid grid-cols-3 gap-2">
                <button v-for="m in paymentMethods" :key="m.value"
                  @click="paymentMethod = m.value"
                  :class="paymentMethod === m.value
                    ? 'border-bc-gold bg-bc-gold/10 text-bc-light'
                    : 'border-bc-gold/20 text-bc-muted hover:border-bc-gold/50'"
                  class="flex flex-col items-center gap-1 p-3 rounded-xl border text-xs font-semibold transition">
                  <span class="text-xl">{{ m.icon }}</span>
                  {{ m.label }}
                </button>
              </div>
            </div>

            <!-- Número de telefone (para eMola / M-Pesa) -->
            <div v-if="paymentMethod !== 'cash'">
              <label class="text-bc-muted text-sm block mb-1">Número de telefone</label>
              <input v-model="paymentPhone" type="tel" placeholder="+258 8X XXX XXXX"
                class="input-african w-full" />
            </div>

            <!-- Erro -->
            <p v-if="checkoutError" class="text-red-400 text-sm text-center">{{ checkoutError }}</p>

            <!-- Botão confirmar -->
            <button @click="confirmPayment"
              :disabled="paying || !paymentMethod"
              class="btn-gold w-full py-4 text-base font-bold disabled:opacity-50 flex items-center justify-center gap-2">
              <svg v-if="paying" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ paying ? 'A processar...' : 'Confirmar Pagamento' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Modal: Compra concluída                                        -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="orderDone" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-sm mx-4 bg-bc-dark rounded-3xl shadow-2xl p-8 text-center">
          <!-- Ícone de sucesso animado -->
          <div class="w-20 h-20 rounded-full bg-green-500/20 flex items-center justify-center mx-auto mb-5">
            <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <h2 class="text-bc-light text-2xl font-black mb-2">Compra Concluída!</h2>
          <p class="text-bc-muted text-sm mb-1">Pedido: <span class="text-bc-gold font-mono font-bold">{{ orderResult?.order_number }}</span></p>
          <p class="text-green-400 text-sm font-semibold mb-6">Podes sair da loja. Boa compra! 🎉</p>

          <!-- Resumo do recibo -->
          <div class="bg-bc-surface rounded-2xl p-4 text-left text-xs space-y-2 mb-6">
            <div class="flex justify-between text-bc-muted">
              <span>Loja</span>
              <span class="text-bc-light font-medium">{{ store?.name }}</span>
            </div>
            <div class="flex justify-between text-bc-muted">
              <span>Itens</span>
              <span class="text-bc-light font-medium">{{ cartCount }}</span>
            </div>
            <div class="flex justify-between text-bc-muted">
              <span>Pagamento</span>
              <span class="text-bc-light font-medium capitalize">{{ orderResult?.payment_method }}</span>
            </div>
            <div class="flex justify-between text-bc-muted border-t border-bc-gold/20 pt-2">
              <span class="font-semibold">Total</span>
              <span class="text-bc-gold font-black text-sm">{{ formatMZN(orderResult?.total) }}</span>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <button @click="printReceipt" class="w-full border border-bc-gold/30 text-bc-gold rounded-xl py-2.5 text-sm font-semibold hover:bg-bc-gold/10 transition">
              🖨️ Imprimir Recibo
            </button>
            <RouterLink :to="`/lojas/${route.params.slug}`" class="btn-gold w-full py-2.5 text-sm font-semibold rounded-xl text-center">
              Ver mais produtos
            </RouterLink>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()

// ─── Estado da loja ───────────────────────────────────────────────────────
const store = ref(null)

// ─── Câmara / Scanner ────────────────────────────────────────────────────
const videoEl     = ref(null)
const cameraActive = ref(false)
const cameraError  = ref('')
const scanning     = ref(false)
let   stream       = null
let   detector     = null
let   scanFrame    = null

// ─── Produto / pesquisa ──────────────────────────────────────────────────
const manualBarcode = ref('')
const foundProduct  = ref(null)
const productQty    = ref(1)
const looking       = ref(false)
const addedFeedback = ref(false)

// ─── Carrinho ────────────────────────────────────────────────────────────
const cart      = ref([])   // [{ product, qty }]
const showCart  = ref(false)

const cartCount = computed(() => cart.value.reduce((s, i) => s + i.qty, 0))
const cartTotal = computed(() => cart.value.reduce((s, i) => s + i.product.price * i.qty, 0))

// ─── Checkout ────────────────────────────────────────────────────────────
const showCheckout  = ref(false)
const paymentMethod = ref('cash')
const paymentPhone  = ref('')
const paying        = ref(false)
const checkoutError = ref('')

// ─── Resultado ───────────────────────────────────────────────────────────
const orderDone   = ref(false)
const orderResult = ref(null)

const paymentMethods = [
  { value: 'cash',  icon: '💵', label: 'Dinheiro' },
  { value: 'mpesa', icon: '📱', label: 'M-Pesa'   },
  { value: 'emola', icon: '💳', label: 'eMola'    },
]

// ─── Utilitários ─────────────────────────────────────────────────────────
function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

// ─── Câmara ──────────────────────────────────────────────────────────────
async function startCamera() {
  cameraError.value = ''
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
    })
    videoEl.value.srcObject = stream
    // Iniciar reprodução manualmente (sem autoplay no elemento) — silencia
    // o AbortError que ocorre quando o componente desmonta antes do play() terminar
    videoEl.value.play().catch(() => {})
    cameraActive.value = true

    // BarcodeDetector (Chrome 83+ / Android WebView)
    if ('BarcodeDetector' in window) {
      detector = new window.BarcodeDetector({
        formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'qr_code', 'upc_a', 'upc_e'],
      })
      scanning.value = true
      scanLoop()
    } else {
      cameraError.value = 'O teu browser não suporta leitura automática de códigos. Usa o campo manual em baixo.'
      cameraActive.value = true
    }
  } catch (e) {
    if (e.name === 'NotAllowedError') {
      cameraError.value = 'Permissão de câmara negada. Activa a câmara nas definições do browser.'
    } else {
      cameraError.value = 'Não foi possível aceder à câmara. Tenta novamente.'
    }
  }
}

async function scanLoop() {
  if (!scanning.value || !videoEl.value || videoEl.value.readyState < 2) {
    scanFrame = requestAnimationFrame(scanLoop)
    return
  }
  try {
    const results = await detector.detect(videoEl.value)
    if (results.length > 0) {
      const code = results[0].rawValue
      if (code && (!foundProduct.value || foundProduct.value._lastBarcode !== code)) {
        await lookupBarcode(code, true)
      }
    }
  } catch (_) { /* ignore frame errors */ }
  scanFrame = requestAnimationFrame(scanLoop)
}

function stopCamera() {
  scanning.value = false
  if (scanFrame) cancelAnimationFrame(scanFrame)
  if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null }
  cameraActive.value = false
}

// ─── Lookup de produto ───────────────────────────────────────────────────
async function lookupBarcode(code, fromCamera = false) {
  if (!code?.trim()) return
  looking.value = true
  try {
    const { data } = await axios.get(`/stores/${route.params.slug}/scan`, {
      params: { barcode: code.trim() }
    })
    foundProduct.value = { ...data, _lastBarcode: code }
    productQty.value = 1
    manualBarcode.value = ''
  } catch (e) {
    if (!fromCamera) {
      // Só mostra erro no input manual, câmara falha silenciosamente
      foundProduct.value = null
      alert(e.response?.data?.message ?? 'Produto não encontrado.')
    }
  } finally {
    looking.value = false
  }
}

// ─── Carrinho ────────────────────────────────────────────────────────────
function addToCart(product, qty) {
  const existing = cart.value.find(i => i.product.id === product.id)
  if (existing) {
    existing.qty += qty
  } else {
    cart.value.push({ product, qty })
  }
  foundProduct.value = null
  productQty.value = 1

  // Feedback visual
  addedFeedback.value = true
  setTimeout(() => { addedFeedback.value = false }, 1800)
}

function changeQty(item, delta) {
  item.qty = Math.max(1, item.qty + delta)
}

function removeFromCart(productId) {
  cart.value = cart.value.filter(i => i.product.id !== productId)
}

// ─── Checkout ────────────────────────────────────────────────────────────
async function confirmPayment() {
  checkoutError.value = ''
  paying.value = true
  try {
    const { data } = await axios.post(`/stores/${route.params.slug}/in-store-checkout`, {
      payment_method: paymentMethod.value,
      payment_phone:  paymentPhone.value || null,
      items: cart.value.map(i => ({ product_id: i.product.id, quantity: i.qty })),
    })
    orderResult.value = data.order
    showCheckout.value = false
    orderDone.value = true
    cart.value = []
  } catch (e) {
    checkoutError.value = e.response?.data?.message ?? 'Erro ao processar pagamento. Tenta novamente.'
  } finally {
    paying.value = false
  }
}

// ─── Recibo ──────────────────────────────────────────────────────────────
function printReceipt() {
  window.print()
}

// ─── Lifecycle ───────────────────────────────────────────────────────────
onMounted(async () => {
  try {
    const { data } = await axios.get(`/stores/${route.params.slug}`)
    store.value = data
  } catch (_) { /* loja não encontrada, o router vai lidar */ }
})

onUnmounted(stopCamera)
</script>

<style scoped>
/* Linha de scan animada */
@keyframes scanLine {
  0%   { top: 0; }
  50%  { top: calc(100% - 2px); }
  100% { top: 0; }
}
.scan-line {
  animation: scanLine 1.8s ease-in-out infinite;
}

/* Transições */
.slide-up-enter-active,
.slide-up-leave-active { transition: all 0.3s ease; }
.slide-up-enter-from  { opacity: 0; transform: translateY(20px); }
.slide-up-leave-to    { opacity: 0; transform: translateY(20px); }

.fade-enter-active,
.fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from,
.fade-leave-to    { opacity: 0; }

/* Estilos de impressão do recibo */
@media print {
  body > *:not(#receipt-print) { display: none !important; }
  #receipt-print { display: block !important; }
}
</style>
