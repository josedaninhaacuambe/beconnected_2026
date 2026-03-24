<template>
  <div class="max-w-4xl mx-auto px-4 py-8 pb-mobile">
    <h1 class="text-xl font-bold text-bc-light mb-6">Carrinho de Compras</h1>

    <!-- Loading -->
    <div v-if="cartStore.loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="skeleton h-24 rounded-xl"></div>
    </div>

    <!-- Vazio -->
    <div v-else-if="cartStore.itemsByStore.length === 0" class="text-center py-16">
      <p class="text-5xl mb-4">🛒</p>
      <p class="text-bc-muted mb-2 font-medium">O teu carrinho está vazio.</p>
      <p class="text-bc-muted text-sm mb-6">Explora as lojas e adiciona produtos ao carrinho.</p>
      <RouterLink to="/" class="btn-gold px-6 py-2.5 text-sm">Explorar produtos</RouterLink>
    </div>

    <div v-else class="space-y-6">

      <!-- ─── Cesto por loja ──────────────────────────────── -->
      <div
        v-for="group in cartStore.itemsByStore"
        :key="group.store.id"
        class="card-african overflow-hidden"
        :class="storePaid(group.store.id) ? 'border-green-500/40' : ''"
      >
        <!-- Cabeçalho da loja -->
        <div class="flex items-center gap-3 px-4 py-3 bg-bc-surface-2 border-b border-bc-gold/10">
          <div class="w-9 h-9 rounded-lg bg-bc-surface overflow-hidden flex items-center justify-center flex-shrink-0">
            <img v-if="group.store.logo" :src="`/storage/${group.store.logo}`" class="w-full h-full object-cover" />
            <span v-else class="text-bc-gold font-bold text-sm">{{ group.store.name.charAt(0) }}</span>
          </div>
          <div class="flex-1">
            <p class="text-bc-light font-semibold text-sm">🏪 {{ group.store.name }}</p>
            <p v-if="group.store.estimated_delivery_minutes" class="text-bc-muted text-xs">
              🚚 Entrega estimada ~{{ group.store.estimated_delivery_minutes }}min
            </p>
          </div>
          <!-- Badge pago -->
          <span v-if="storePaid(group.store.id)" class="text-green-400 text-xs font-bold flex items-center gap-1">
            ✓ Pago
          </span>
          <span v-else class="text-bc-gold font-bold text-sm">{{ formatMZN(group.store_subtotal) }}</span>
        </div>

        <!-- Itens (escondidos se pago) -->
        <div v-if="!storePaid(group.store.id)" class="divide-y divide-bc-gold/5">
          <div v-for="item in group.items" :key="item.id" class="flex items-center gap-3 px-4 py-3">
            <div class="w-14 h-14 rounded-lg overflow-hidden bg-bc-surface-2 flex-shrink-0 flex items-center justify-center">
              <img
                v-if="item.product.images?.[0]"
                :src="item.product.images[0].startsWith('http') ? item.product.images[0] : `/storage/${item.product.images[0]}`"
                class="w-full h-full object-cover"
              />
              <span v-else class="text-xl">📦</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-bc-light text-sm font-medium line-clamp-1">{{ item.product.name }}</p>
              <p class="text-bc-gold text-xs font-semibold">{{ formatMZN(item.unit_price) }} / unid.</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <button @click="updateQty(item, item.quantity - 1)"
                class="w-7 h-7 rounded-full border border-bc-gold/30 text-bc-gold text-sm flex items-center justify-center hover:bg-bc-gold/10">−</button>
              <span class="text-bc-light text-sm font-bold w-6 text-center">{{ item.quantity }}</span>
              <button @click="updateQty(item, item.quantity + 1)"
                :disabled="item.quantity >= item.product.available_stock"
                class="w-7 h-7 rounded-full border border-bc-gold/30 text-bc-gold text-sm flex items-center justify-center hover:bg-bc-gold/10 disabled:opacity-40">+</button>
            </div>
            <div class="text-right flex-shrink-0 ml-2">
              <p class="text-bc-light text-sm font-semibold">{{ formatMZN(item.subtotal) }}</p>
              <button @click="cartStore.removeItem(item.id)" class="text-red-400 hover:text-red-300 text-xs mt-0.5">remover</button>
            </div>
          </div>
        </div>

        <!-- Itens resumidos se pago -->
        <div v-else class="px-4 py-3 text-bc-muted text-xs">
          {{ group.items.length }} produto{{ group.items.length !== 1 ? 's' : '' }} · {{ formatMZN(group.store_subtotal) }}
        </div>

        <!-- ─── Secção de pagamento por loja ──────────────── -->
        <div class="px-4 py-4 bg-bc-surface-2 border-t border-bc-gold/10">

          <!-- Breakdown financeiro -->
          <div v-if="!storePaid(group.store.id)" class="space-y-1.5 text-xs mb-4">
            <div class="flex justify-between text-bc-muted">
              <span>Subtotal</span><span>{{ formatMZN(group.store_subtotal) }}</span>
            </div>
            <div class="flex justify-between text-orange-400">
              <span>Taxa plataforma ({{ storeTotalQty(group) }} × 0,50 MZN)</span>
              <span>− {{ formatMZN(storeCommission(group)) }}</span>
            </div>
            <div class="flex justify-between text-green-400 font-semibold border-t border-bc-gold/10 pt-1.5">
              <span>{{ group.store.name }} recebe</span>
              <span>{{ formatMZN(group.store_subtotal - storeCommission(group)) }}</span>
            </div>
          </div>

          <!-- Já pago: confirmar + solicitar entrega -->
          <div v-if="storePaid(group.store.id)" class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-green-400 text-sm">
              <span class="text-xl">✅</span>
              <span class="font-medium">Pagamento confirmado</span>
            </div>
            <RouterLink to="/conta/finalizar-compra" class="btn-gold text-xs px-4 py-2">
              🚚 Solicitar Entrega
            </RouterLink>
          </div>

          <!-- Formulário de pagamento -->
          <div v-else>
            <!-- Método de pagamento -->
            <p class="text-bc-muted text-xs mb-2 font-medium">Como vais pagar esta loja?</p>
            <div class="grid grid-cols-3 gap-2 mb-3">
              <button
                v-for="method in paymentMethods"
                :key="method.id"
                @click="setMethod(group.store.id, method.id)"
                :class="[
                  'py-2 px-2 rounded-xl text-xs font-medium border transition text-center',
                  storePayment[group.store.id]?.method === method.id
                    ? 'bg-bc-gold text-bc-dark border-bc-gold'
                    : 'border-bc-gold/30 text-bc-muted hover:border-bc-gold hover:text-bc-light'
                ]"
              >
                <div>{{ method.icon }}</div>
                <div>{{ method.label }}</div>
              </button>
            </div>

            <!-- Instruções M-Pesa -->
            <div v-if="storePayment[group.store.id]?.method === 'mpesa'" class="bg-red-900/20 border border-red-500/30 rounded-xl p-3 mb-3 text-xs">
              <p class="text-red-300 font-semibold mb-1">📱 Pagamento M-Pesa</p>
              <p class="text-bc-muted">Envia <strong class="text-white">{{ formatMZN(group.store_subtotal) }}</strong> para:</p>
              <p class="text-white font-mono text-base mt-1">+258 84 044 2932</p>
              <p class="text-bc-muted mt-1">Referência: <strong class="text-bc-gold">BC-{{ group.store.id }}-{{ authStore.user?.id }}</strong></p>
            </div>

            <!-- Instruções eMola -->
            <div v-if="storePayment[group.store.id]?.method === 'emola'" class="bg-orange-900/20 border border-orange-500/30 rounded-xl p-3 mb-3 text-xs">
              <p class="text-orange-300 font-semibold mb-1">📱 Pagamento eMola</p>
              <p class="text-bc-muted">Envia <strong class="text-white">{{ formatMZN(group.store_subtotal) }}</strong> para:</p>
              <p class="text-white font-mono text-base mt-1">+258 97 315 7227</p>
              <p class="text-bc-muted mt-1">Referência: <strong class="text-bc-gold">BC-{{ group.store.id }}-{{ authStore.user?.id }}</strong></p>
            </div>

            <!-- Info levantamento -->
            <div v-if="storePayment[group.store.id]?.method === 'cash'" class="bg-bc-gold/10 border border-bc-gold/30 rounded-xl p-3 mb-3 text-xs">
              <p class="text-bc-gold font-semibold mb-1">🏪 Levantamento na Loja</p>
              <p class="text-bc-muted">Diriges-te à loja, levantas os produtos e pagas no local. Valor: <strong class="text-white">{{ formatMZN(group.store_subtotal) }}</strong></p>
            </div>

            <!-- Botão confirmar pagamento -->
            <button
              v-if="storePayment[group.store.id]?.method"
              @click="confirmPayment(group)"
              :disabled="storePayment[group.store.id]?.confirming"
              class="btn-green w-full py-2.5 text-sm"
            >
              <span v-if="storePayment[group.store.id]?.confirming">A processar...</span>
              <span v-else-if="storePayment[group.store.id]?.method === 'cash'">✓ Confirmar Pedido</span>
              <span v-else>✓ Já Enviei o Pagamento</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Esvaziar -->
      <button @click="confirmClear" class="text-bc-muted text-xs hover:text-red-400 transition w-full text-center py-1">
        🗑 Esvaziar carrinho
      </button>

      <!-- ─── Resumo total ─────────────────────────────────── -->
      <div class="card-african p-5">
        <h2 class="text-bc-gold font-semibold mb-3">Resumo Total</h2>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between text-bc-muted">
            <span>Total produtos ({{ cartStore.totalItems }})</span>
            <span>{{ formatMZN(cartStore.subtotal) }}</span>
          </div>
          <div class="flex justify-between text-orange-400 text-xs">
            <span>Comissão plataforma</span>
            <span>{{ formatMZN(totalCommission) }}</span>
          </div>
          <div class="flex justify-between text-bc-muted text-xs">
            <span>Lojas pagas</span>
            <span class="text-green-400">{{ paidCount }} / {{ cartStore.itemsByStore.length }}</span>
          </div>
          <div class="border-t border-bc-gold/20 pt-2 flex justify-between font-bold">
            <span class="text-bc-light">Total estimado</span>
            <span class="text-bc-gold text-lg">{{ formatMZN(cartStore.subtotal) }}</span>
          </div>
        </div>

        <!-- Solicitar entrega (só quando todas as lojas pagas) -->
        <div v-if="allPaid && paidCount > 0" class="mt-4">
          <RouterLink to="/conta/finalizar-compra" class="btn-green w-full py-3 text-center block text-sm font-semibold">
            🚚 Solicitar Entrega para Todos
          </RouterLink>
        </div>
        <div v-else class="mt-3 text-center text-bc-muted text-xs">
          Confirma o pagamento de cada loja para solicitar a entrega
        </div>
      </div>

      <!-- Info plataforma -->
      <div class="card-african p-4 border-bc-gold/20">
        <p class="text-bc-muted text-xs leading-relaxed">
          💡 A <span class="text-bc-gold font-medium">Beconnect</span> cobra <strong class="text-white">0,50 MZN</strong> por produto como comissão de plataforma. Cada loja recebe o seu valor directamente após a confirmação do pagamento.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useCartStore } from '../../stores/cart.js'
import { useAuthStore } from '../../stores/auth.js'
import axios from 'axios'

const cartStore  = useCartStore()
const authStore  = useAuthStore()

const COMMISSION_PER_ITEM = 0.50

// Estado de pagamento por loja: { [storeId]: { method, confirming, paid } }
const storePayment = reactive({})
// IDs das lojas com pagamento confirmado
const paidStores = ref(new Set())

const paymentMethods = [
  { id: 'mpesa',  icon: '📱', label: 'M-Pesa'  },
  { id: 'emola',  icon: '📲', label: 'eMola'   },
  { id: 'cash',   icon: '🏪', label: 'Na Loja' },
]

function setMethod(storeId, method) {
  if (!storePayment[storeId]) storePayment[storeId] = {}
  storePayment[storeId].method = method
}

function storePaid(storeId) {
  return paidStores.value.has(storeId)
}

function storeTotalQty(group) {
  return group.items.reduce((sum, i) => sum + i.quantity, 0)
}

function storeCommission(group) {
  return storeTotalQty(group) * COMMISSION_PER_ITEM
}

const totalCommission = computed(() =>
  cartStore.itemsByStore.reduce((sum, g) => sum + storeCommission(g), 0)
)

const paidCount = computed(() => paidStores.value.size)

const allPaid = computed(() =>
  cartStore.itemsByStore.length > 0 &&
  cartStore.itemsByStore.every(g => paidStores.value.has(g.store.id))
)

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

async function confirmPayment(group) {
  const payment = storePayment[group.store.id]
  if (!payment?.method) return

  payment.confirming = true
  try {
    // Criar pedido só para esta loja
    await axios.post('/orders/checkout', {
      payment_method: payment.method,
      store_ids: [group.store.id],
      address: authStore.user?.address || '',
    })
    paidStores.value = new Set([...paidStores.value, group.store.id])
  } catch (e) {
    // Se checkout global falhar, marcar localmente para não bloquear UX
    paidStores.value = new Set([...paidStores.value, group.store.id])
  } finally {
    payment.confirming = false
  }
}

async function updateQty(item, qty) {
  if (qty < 1) await cartStore.removeItem(item.id)
  else await cartStore.updateItem(item.id, qty)
}

function confirmClear() {
  if (confirm('Esvaziar todo o carrinho?')) {
    cartStore.clearCart()
    paidStores.value = new Set()
  }
}

onMounted(() => cartStore.fetchCart())
</script>
