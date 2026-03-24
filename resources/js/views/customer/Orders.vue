<template>
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-xl font-bold text-bc-light mb-6">Os Meus Pedidos</h1>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="skeleton h-20 rounded-xl"></div>
    </div>

    <div v-else-if="orders.length === 0" class="text-center py-16 text-bc-muted">
      Nenhum pedido ainda.
    </div>

    <div v-else class="space-y-3">
      <RouterLink
        v-for="order in orders"
        :key="order.id"
        :to="`/conta/pedidos/${order.id}`"
        class="card-african p-4 flex items-center justify-between hover:border-bc-gold/40 transition block"
      >
        <div>
          <p class="text-bc-light font-semibold">{{ order.order_number }}</p>
          <p class="text-bc-muted text-xs">{{ formatDate(order.created_at) }} · {{ order.items_count ?? '?' }} produtos</p>
        </div>
        <div class="text-right">
          <p class="text-bc-gold font-bold">{{ formatMZN(order.total_amount) }}</p>
          <span :class="statusBadge(order.status)">{{ order.status }}</span>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const orders = ref([])
const loading = ref(true)

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}
function formatDate(d) { return new Date(d).toLocaleDateString('pt-MZ') }
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
    const { data } = await axios.get('/orders')
    orders.value = data.data ?? data
  } finally {
    loading.value = false
  }
})
</script>
