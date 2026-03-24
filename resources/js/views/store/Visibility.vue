<template>
  <div class="p-6 pb-mobile">
    <h1 class="text-2xl font-bold text-bc-gold mb-2">Posicionamento & Visibilidade</h1>
    <p class="text-bc-muted mb-6">Destaque a sua loja e apareça nas primeiras posições de pesquisa.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="plan in plans"
        :key="plan.id"
        class="card-african p-5 relative"
        :class="{'border-bc-gold': plan.name === 'Premium'}"
      >
        <div v-if="plan.name === 'Premium'" class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="badge-featured px-3 py-1">Mais Popular</span>
        </div>

        <h3 class="text-bc-light font-bold text-lg mb-1">{{ plan.name }}</h3>
        <p class="price-tag text-2xl mb-2">{{ formatPrice(plan.price) }}</p>
        <p class="text-bc-muted text-xs mb-1">{{ plan.duration_days }} dias de destaque</p>
        <p v-if="plan.is_featured_badge" class="text-bc-gold text-xs mb-3">⭐ Badge de destaque incluído</p>
        <p class="text-bc-muted text-sm mb-4">{{ plan.description }}</p>

        <!-- Barra de boost visual -->
        <div class="mb-4">
          <div class="h-2 bg-bc-surface-2 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-bc-gold to-bc-orange rounded-full" :style="`width: ${plan.position_boost}%`"></div>
          </div>
          <p class="text-bc-muted text-xs mt-1">Boost de visibilidade: {{ plan.position_boost }}%</p>
        </div>

        <button @click="selectPlan(plan)" class="btn-gold w-full text-sm">
          Contratar
        </button>
      </div>
    </div>

    <!-- Modal de pagamento -->
    <div v-if="selectedPlan" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
      <div class="card-african p-6 w-full max-w-md">
        <h3 class="text-bc-gold font-bold text-lg mb-4">Contratar Plano {{ selectedPlan.name }}</h3>
        <p class="text-bc-muted text-sm mb-4">Total: <span class="text-bc-gold font-bold">{{ formatPrice(selectedPlan.price) }}</span></p>

        <div class="space-y-3">
          <label v-for="method in ['mpesa', 'emola']" :key="method" class="flex items-center gap-3 cursor-pointer">
            <input type="radio" v-model="paymentMethod" :value="method" />
            <span class="text-bc-light">{{ method === 'mpesa' ? 'M-Pesa (Vodacom)' : 'eMola (Movitel)' }}</span>
          </label>
          <input v-model="paymentPhone" type="tel" placeholder="Número de telefone" class="input-african" />
        </div>

        <div class="flex gap-3 mt-4">
          <button @click="selectedPlan = null" class="btn-ghost flex-1">Cancelar</button>
          <button @click="purchasePlan" :disabled="loading" class="btn-gold flex-1">
            {{ loading ? 'A processar...' : 'Pagar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const plans = ref([])
const selectedPlan = ref(null)
const paymentMethod = ref('mpesa')
const paymentPhone = ref('')
const loading = ref(false)

function formatPrice(v) {
  return new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' }).format(v || 0)
}

function selectPlan(plan) { selectedPlan.value = plan }

async function purchasePlan() {
  loading.value = true
  try {
    await axios.post('/store/visibility/purchase', {
      visibility_plan_id: selectedPlan.value.id,
      payment_method: paymentMethod.value,
      payment_phone: paymentPhone.value,
    })
    alert('Pedido enviado! Complete o pagamento no seu telemóvel.')
    selectedPlan.value = null
  } catch (e) {
    alert(e.response?.data?.message || 'Erro ao processar.')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  const { data } = await axios.get('/visibility-plans')
  plans.value = data
})
</script>
