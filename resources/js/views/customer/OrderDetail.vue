<template>
  <div class="max-w-3xl mx-auto px-4 py-8">
    <RouterLink to="/conta/pedidos" class="text-bc-muted hover:text-bc-gold text-sm mb-4 inline-block">← Voltar</RouterLink>

    <div v-if="loading" class="space-y-3">
      <div class="skeleton h-12 rounded-xl"></div>
      <div class="skeleton h-48 rounded-xl"></div>
    </div>

    <div v-else-if="order">
      <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-bold text-bc-light">{{ order.order_number }}</h1>
        <span :class="statusBadge(order.status)">{{ order.status }}</span>
      </div>

      <div class="card-african p-5 mb-4">
        <p class="text-bc-muted text-xs mb-3">Produtos</p>
        <div class="space-y-3">
          <div v-for="storeOrder in order.store_orders" :key="storeOrder.id">
            <p class="text-bc-gold text-xs font-semibold mb-2">🏪 {{ storeOrder.store?.name }}</p>
            <div v-for="item in storeOrder.items" :key="item.id" class="flex items-center justify-between text-sm">
              <span class="text-bc-light">{{ item.product?.name }} × {{ item.quantity }}</span>
              <span class="text-bc-gold">{{ formatMZN(item.subtotal) }}</span>
            </div>
          </div>
        </div>
        <div class="border-t border-bc-gold/20 mt-3 pt-3 flex justify-between font-bold">
          <span class="text-bc-light">Total</span>
          <span class="text-bc-gold">{{ formatMZN(order.total_amount) }}</span>
        </div>
      </div>

      <!-- Rastreamento -->
      <div v-if="order.delivery" class="card-african p-5">
        <p class="text-bc-gold font-semibold mb-2">Rastreamento</p>
        <p class="text-bc-muted text-sm">Código: <span class="font-mono text-bc-light">{{ order.delivery.tracking_code }}</span></p>
        <RouterLink :to="`/rastrear/${order.delivery.tracking_code}`" class="text-bc-gold text-sm hover:underline">
          Ver em tempo real →
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const order = ref(null)
const loading = ref(true)

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}
function statusBadge(s) {
  return 'text-xs px-2 py-0.5 rounded-full ' + ({
    pending: 'bg-orange-500/20 text-orange-300',
    confirmed: 'bg-blue-500/20 text-blue-300',
    delivered: 'bg-green-500/20 text-green-300',
    cancelled: 'bg-red-500/20 text-red-300',
  }[s] ?? 'bg-bc-surface text-bc-muted')
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
