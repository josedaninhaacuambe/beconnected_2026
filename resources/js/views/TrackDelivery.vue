<template>
  <div class="container mx-auto px-4 py-12 max-w-lg">
    <h1 class="text-2xl font-bold text-bc-gold mb-2">Rastrear Entrega</h1>
    <p class="text-bc-muted mb-6">Introduza o código de rastreamento para ver o estado da sua encomenda.</p>

    <div class="flex gap-2 mb-8">
      <input v-model="code" type="text" placeholder="TRK-XXXXX" class="input-african flex-1 uppercase" />
      <button @click="track" class="btn-gold px-5">Rastrear</button>
    </div>

    <div v-if="delivery" class="card-african p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-bc-light font-bold">Código: {{ delivery.tracking_code }}</h2>
        <span :class="statusClass">{{ statusLabel }}</span>
      </div>

      <!-- Timeline -->
      <div class="space-y-3">
        <div v-for="step in timeline" :key="step.status" class="flex items-center gap-3">
          <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-sm flex-shrink-0', step.done ? 'bg-bc-gold text-bc-dark' : 'bg-bc-surface-2 text-bc-muted']">
            {{ step.done ? '✓' : '○' }}
          </div>
          <div>
            <p :class="['text-sm font-medium', step.done ? 'text-bc-light' : 'text-bc-muted']">{{ step.label }}</p>
            <p v-if="step.time" class="text-bc-muted text-xs">{{ step.time }}</p>
          </div>
        </div>
      </div>

      <div v-if="delivery.driver" class="mt-4 pt-4 border-t border-bc-gold/20">
        <p class="text-bc-muted text-sm">Estafeta: <span class="text-bc-light">{{ delivery.driver.name }}</span></p>
        <p class="text-bc-muted text-sm">Veículo: <span class="text-bc-light">{{ delivery.driver.vehicle_type }}</span></p>
      </div>
    </div>

    <div v-if="error" class="text-red-400 text-center mt-4">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const code = ref(route.params.code || '')
const delivery = ref(null)
const error = ref('')

const statusMap = {
  pending: { label: 'Pendente', class: 'text-yellow-400 bg-yellow-900/30 px-2 py-0.5 rounded-full text-xs' },
  assigned: { label: 'Atribuído', class: 'text-blue-400 bg-blue-900/30 px-2 py-0.5 rounded-full text-xs' },
  picking_up: { label: 'A recolher', class: 'text-orange-400 bg-orange-900/30 px-2 py-0.5 rounded-full text-xs' },
  in_transit: { label: 'Em trânsito', class: 'text-purple-400 bg-purple-900/30 px-2 py-0.5 rounded-full text-xs' },
  delivered: { label: 'Entregue ✓', class: 'text-green-400 bg-green-900/30 px-2 py-0.5 rounded-full text-xs' },
}

const statusClass = computed(() => statusMap[delivery.value?.status]?.class || '')
const statusLabel = computed(() => statusMap[delivery.value?.status]?.label || '')

const timeline = computed(() => {
  if (!delivery.value) return []
  const steps = ['pending', 'assigned', 'picking_up', 'in_transit', 'delivered']
  const current = steps.indexOf(delivery.value.status)
  return [
    { status: 'pending', label: 'Pedido confirmado', done: current >= 0 },
    { status: 'assigned', label: 'Estafeta atribuído', done: current >= 1 },
    { status: 'picking_up', label: 'A recolher encomenda', done: current >= 2, time: delivery.value.picked_up_at },
    { status: 'in_transit', label: 'Em trânsito para si', done: current >= 3 },
    { status: 'delivered', label: 'Entregue!', done: current >= 4, time: delivery.value.delivered_at },
  ]
})

async function track() {
  if (!code.value) return
  error.value = ''
  try {
    const { data } = await axios.get(`/delivery/track/${code.value.toUpperCase()}`)
    delivery.value = data
  } catch {
    error.value = 'Código de rastreamento não encontrado.'
    delivery.value = null
  }
}

onMounted(() => { if (code.value) track() })
</script>
