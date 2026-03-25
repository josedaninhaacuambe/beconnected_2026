<template>
  <div class="max-w-3xl mx-auto px-4 py-8 pb-mobile">
    <RouterLink to="/conta/pedidos" class="text-bc-muted hover:text-bc-gold text-sm mb-4 inline-block">← Voltar aos pedidos</RouterLink>

    <div v-if="loading" class="space-y-3">
      <div class="skeleton h-12 rounded-xl"></div>
      <div class="skeleton h-48 rounded-xl"></div>
    </div>

    <div v-else-if="order">
      <!-- Cabeçalho -->
      <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-bold text-bc-light">Pedido {{ order.order_number }}</h1>
        <span :class="statusBadge(order.status)" class="text-xs px-3 py-1 rounded-full font-semibold">{{ statusLabel(order.status) }}</span>
      </div>

      <!-- Produtos por loja -->
      <div class="card-african p-5 mb-4">
        <p class="text-bc-gold font-semibold text-sm mb-3">🧾 Produtos</p>
        <div class="space-y-4">
          <div v-for="storeOrder in order.store_orders" :key="storeOrder.id">
            <p class="text-bc-gold text-xs font-semibold mb-2 flex items-center gap-1">
              🏪 {{ storeOrder.store?.name }}
              <span :class="statusBadge(storeOrder.status)" class="text-xs px-2 py-0.5 rounded-full ml-2">{{ statusLabel(storeOrder.status) }}</span>
            </p>
            <div v-for="item in storeOrder.items" :key="item.id" class="flex items-center justify-between text-sm py-1">
              <span class="text-bc-light">{{ item.product_name ?? item.product?.name }} × {{ item.quantity }}</span>
              <span class="text-bc-gold">{{ formatMZN(item.total ?? item.subtotal) }}</span>
            </div>
          </div>
        </div>
        <div class="border-t border-bc-gold/20 mt-3 pt-3 space-y-1.5 text-sm">
          <div class="flex justify-between text-bc-muted">
            <span>Subtotal</span>
            <span>{{ formatMZN(order.subtotal) }}</span>
          </div>
          <div class="flex justify-between text-bc-muted">
            <span>Entrega</span>
            <span>{{ formatMZN(order.delivery_fee) }}</span>
          </div>
          <div class="flex justify-between font-bold text-base">
            <span class="text-bc-light">Total</span>
            <span class="text-bc-gold">{{ formatMZN(order.total) }}</span>
          </div>
        </div>
      </div>

      <!-- Rastreamento GPS -->
      <div v-if="order.delivery" class="card-african p-5 mb-4">
        <div class="flex items-center justify-between mb-3">
          <p class="text-bc-gold font-semibold text-sm">📡 Rastreamento da Entrega</p>
          <span :class="deliveryStatusBadge(order.delivery.status)" class="text-xs px-2 py-0.5 rounded-full">
            {{ deliveryStatusLabel(order.delivery.status) }}
          </span>
        </div>

        <!-- Código de rastreamento -->
        <div class="bg-bc-surface-2 rounded-lg p-3 mb-3">
          <p class="text-bc-muted text-xs mb-1">Codigo de rastreamento</p>
          <p class="font-mono text-bc-light text-sm font-bold">{{ order.delivery.tracking_code }}</p>
          <RouterLink :to="`/rastrear/${order.delivery.tracking_code}`" class="text-bc-gold text-xs hover:underline mt-1 inline-block">
            Ver em tempo real →
          </RouterLink>
        </div>

        <!-- Timeline da entrega -->
        <div class="space-y-2 mb-3">
          <div v-for="step in deliveryTimeline" :key="step.label" class="flex items-center gap-3">
            <span :class="step.done ? 'text-green-400' : 'text-bc-muted'">{{ step.done ? '✓' : '○' }}</span>
            <span :class="step.done ? 'text-bc-light' : 'text-bc-muted'" class="text-xs">{{ step.label }}</span>
            <span v-if="step.time" class="text-bc-muted text-xs ml-auto">{{ formatDate(step.time) }}</span>
          </div>
        </div>

        <!-- Estafeta info -->
        <div v-if="order.delivery.driver" class="bg-bc-surface-2 rounded-lg p-3 mb-3">
          <p class="text-bc-muted text-xs mb-2">🏍️ Estafeta</p>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-bc-light text-sm font-medium">{{ order.delivery.driver?.user?.name ?? order.delivery.driver?.name }}</p>
              <p class="text-bc-muted text-xs">{{ order.delivery.driver?.vehicle_type }}</p>
            </div>
            <div class="flex gap-2">
              <a
                v-if="order.delivery.driver?.user?.phone"
                :href="`tel:${order.delivery.driver.user.phone}`"
                class="text-bc-gold text-xs border border-bc-gold/30 rounded-lg px-2 py-1 hover:bg-bc-gold/10"
              >📞 Ligar</a>
              <a
                v-if="order.delivery.driver?.latitude && order.delivery.driver?.longitude"
                :href="`https://www.google.com/maps?q=${order.delivery.driver.latitude},${order.delivery.driver.longitude}`"
                target="_blank"
                class="text-green-400 text-xs border border-green-500/30 rounded-lg px-2 py-1 hover:bg-green-900/20"
              >📡 GPS</a>
            </div>
          </div>
        </div>

        <!-- Botão confirmar recebimento -->
        <div v-if="order.delivery.status === 'delivered' && !order.delivery.client_confirmed_at && !confirmed">
          <div class="bg-orange-900/20 border border-orange-500/30 rounded-lg p-3 mb-3">
            <p class="text-orange-400 text-xs font-semibold">⚠ O estafeta marcou como entregue. Confirmas o recebimento?</p>
          </div>
          <button @click="showRatingModal = true" class="btn-gold w-full py-3 text-sm font-bold">
            ✅ Confirmar recebimento e avaliar
          </button>
        </div>

        <!-- Confirmado -->
        <div v-else-if="order.delivery.client_confirmed_at || confirmed" class="bg-green-900/20 border border-green-500/30 rounded-lg p-3">
          <p class="text-green-400 text-xs font-semibold mb-1">✅ Recebimento confirmado!</p>
          <div v-if="order.delivery.driver_rating || ratingSubmitted" class="flex items-center gap-1 mt-1">
            <span v-for="i in 5" :key="i" :class="i <= (order.delivery.driver_rating || submittedRating) ? 'text-yellow-400' : 'text-bc-muted'">★</span>
            <span class="text-bc-muted text-xs ml-1">Obrigado pela avaliacão!</span>
          </div>
        </div>
      </div>

      <!-- Endereço de entrega -->
      <div class="card-african p-5 mb-4">
        <p class="text-bc-gold font-semibold text-sm mb-3">📍 Endereco de Entrega</p>
        <p class="text-bc-light text-sm">{{ order.delivery_address }}</p>
        <p class="text-bc-muted text-xs mt-1">{{ order.city?.name }}, {{ order.province?.name }}</p>
        <a
          v-if="order.delivery_latitude && order.delivery_longitude"
          :href="`https://www.google.com/maps?q=${order.delivery_latitude},${order.delivery_longitude}`"
          target="_blank"
          class="text-bc-gold text-xs hover:underline mt-2 inline-block"
        >🗺 Ver no mapa</a>
      </div>
    </div>

    <!-- Modal de avaliação -->
    <Teleport to="body">
      <div v-if="showRatingModal" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
        <div class="card-african p-6 w-full max-w-md">
          <h3 class="text-bc-gold font-bold text-lg mb-1">Confirmar Recebimento</h3>
          <p class="text-bc-muted text-sm mb-4">Avalia o servico do estafeta para ganhar estrelas</p>

          <!-- Estrelas -->
          <div class="flex justify-center gap-2 mb-4">
            <button
              v-for="i in 5" :key="i"
              @click="ratingValue = i"
              :class="['text-4xl transition hover:scale-110', i <= ratingValue ? 'text-yellow-400' : 'text-bc-muted']"
            >★</button>
          </div>
          <p class="text-center text-bc-muted text-sm mb-4">
            {{ ['', 'Muito mau', 'Mau', 'Razoavel', 'Bom', 'Excelente!'][ratingValue] }}
          </p>

          <textarea
            v-model="ratingComment"
            placeholder="Comentario opcional sobre o servico..."
            class="input-african resize-none mb-4"
            rows="2"
          ></textarea>

          <div class="flex gap-3">
            <button @click="showRatingModal = false" class="btn-ghost flex-1">Cancelar</button>
            <button @click="submitRating" :disabled="ratingValue === 0 || ratingLoading" class="btn-gold flex-1">
              {{ ratingLoading ? 'A enviar...' : '✅ Confirmar' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const order = ref(null)
const loading = ref(true)
const showRatingModal = ref(false)
const ratingValue = ref(0)
const ratingComment = ref('')
const ratingLoading = ref(false)
const ratingSubmitted = ref(false)
const submittedRating = ref(0)
const confirmed = ref(false)

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('pt-MZ', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function statusLabel(s) {
  return { pending: 'Pendente', confirmed: 'Confirmado', processing: 'A preparar', shipped: 'Enviado', delivered: 'Entregue', cancelled: 'Cancelado', completed: 'Concluido' }[s] ?? s
}

function statusBadge(s) {
  const map = {
    pending: 'bg-orange-500/20 text-orange-300',
    confirmed: 'bg-blue-500/20 text-blue-300',
    processing: 'bg-yellow-500/20 text-yellow-300',
    shipped: 'bg-purple-500/20 text-purple-300',
    delivered: 'bg-green-500/20 text-green-300',
    completed: 'bg-green-600/30 text-green-400',
    cancelled: 'bg-red-500/20 text-red-300',
  }
  return map[s] ?? 'bg-bc-surface text-bc-muted'
}

function deliveryStatusLabel(s) {
  return { pending: 'Pendente', assigned: 'Atribuido', picking_up: 'A recolher', in_transit: 'Em transito', delivered: 'Entregue', failed: 'Falhou' }[s] ?? s
}

function deliveryStatusBadge(s) {
  const map = {
    pending: 'bg-yellow-900/40 text-yellow-400',
    assigned: 'bg-blue-900/40 text-blue-400',
    picking_up: 'bg-orange-900/40 text-orange-400',
    in_transit: 'bg-purple-900/40 text-purple-400',
    delivered: 'bg-green-900/40 text-green-400',
    failed: 'bg-red-900/40 text-red-400',
  }
  return map[s] ?? 'bg-bc-surface text-bc-muted'
}

const deliveryTimeline = computed(() => {
  if (!order.value?.delivery) return []
  const d = order.value.delivery
  return [
    { label: 'Pedido criado', done: true, time: order.value.created_at },
    { label: 'Estafeta atribuido', done: !!d.assigned_at, time: d.assigned_at },
    { label: 'Produto recolhido na loja', done: !!d.picked_up_at, time: d.picked_up_at },
    { label: 'Em transito para ti', done: d.status === 'in_transit' || !!d.delivered_at, time: null },
    { label: 'Entregue', done: !!d.delivered_at, time: d.delivered_at },
    { label: 'Recebimento confirmado', done: !!d.client_confirmed_at || confirmed.value, time: d.client_confirmed_at },
  ]
})

async function submitRating() {
  if (!ratingValue.value || !order.value?.delivery?.id) return
  ratingLoading.value = true
  try {
    await axios.post(`/delivery/${order.value.delivery.id}/confirm-receipt`, {
      rating: ratingValue.value,
      comment: ratingComment.value || undefined,
    })
    submittedRating.value = ratingValue.value
    ratingSubmitted.value = true
    confirmed.value = true
    showRatingModal.value = false
    // Refresh the order to show updated state
    const { data } = await axios.get(`/orders/${route.params.numero}`)
    order.value = data
  } catch (e) {
    alert(e.response?.data?.message || 'Erro ao confirmar recebimento.')
  } finally {
    ratingLoading.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/orders/${route.params.numero}`)
    order.value = data
  } finally {
    loading.value = false
  }
})
</script>
