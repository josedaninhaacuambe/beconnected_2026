<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-xl font-bold text-bc-light mb-6">Pedidos da Loja</h1>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl"></div>
    </div>

    <div v-else-if="orders.length === 0" class="text-center py-16 text-bc-muted">Nenhum pedido ainda.</div>

    <div v-else class="card-african overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-bc-gold/20 text-bc-muted text-xs uppercase">
            <th class="text-left p-4">Pedido</th>
            <th class="text-left p-4">Cliente</th>
            <th class="text-left p-4">Total</th>
            <th class="text-left p-4">Estado</th>
            <th class="text-left p-4">Data</th>
            <th class="text-right p-4">Acção</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orders" :key="o.id" class="border-b border-bc-gold/10 hover:bg-bc-gold/5">
            <td class="p-4 text-bc-light font-mono text-xs">{{ o.order?.order_number }}</td>
            <td class="p-4 text-bc-muted">{{ o.order?.user?.name }}</td>
            <td class="p-4 text-bc-gold font-semibold">{{ formatMZN(o.total_amount) }}</td>
            <td class="p-4">
              <select
                :value="o.status"
                @change="updateStatus(o, $event.target.value)"
                class="text-xs bg-bc-surface border border-bc-gold/20 rounded-lg px-2 py-1 text-bc-light"
              >
                <option value="pending">Pendente</option>
                <option value="confirmed">Confirmado</option>
                <option value="processing">A preparar</option>
                <option value="shipped">Enviado</option>
                <option value="delivered">Entregue</option>
                <option value="cancelled">Cancelado</option>
              </select>
            </td>
            <td class="p-4 text-bc-muted text-xs">{{ formatDate(o.created_at) }}</td>
            <td class="p-4 text-right text-xs text-bc-gold">{{ o.items_count }} itens</td>
          </tr>
        </tbody>
      </table>
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

async function updateStatus(order, status) {
  await axios.put(`/store/orders/${order.id}/status`, { status })
  order.status = status
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/store/orders')
    orders.value = data.data ?? data
  } finally {
    loading.value = false
  }
})
</script>
