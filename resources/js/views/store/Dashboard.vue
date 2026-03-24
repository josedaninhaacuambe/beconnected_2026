<template>
  <div class="p-6 pb-mobile">
    <!-- Header da loja -->
    <div class="card-african p-5 mb-6 relative overflow-hidden">
      <div class="african-pattern-bar absolute top-0 left-0 right-0 h-1"></div>
      <div class="flex items-center gap-4 pt-2">
        <div class="w-16 h-16 bg-bc-gold/10 rounded-full border-2 border-bc-gold/30 flex items-center justify-center flex-shrink-0">
          <img v-if="store?.logo" :src="`/storage/${store.logo}`" class="w-16 h-16 rounded-full object-cover" />
          <span v-else class="text-bc-gold text-2xl font-bold">{{ store?.name?.charAt(0) }}</span>
        </div>
        <div>
          <h1 class="text-xl font-bold text-bc-light">{{ store?.name }}</h1>
          <p class="text-bc-muted text-sm">{{ store?.category?.name }}</p>
          <span :class="['text-xs px-2 py-0.5 rounded-full', store?.status === 'active' ? 'bg-green-900 text-green-400' : 'bg-yellow-900 text-yellow-400']">
            {{ store?.status === 'active' ? '✓ Activa' : '⏳ Pendente aprovação' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Estatísticas -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div v-for="stat in stats" :key="stat.label" class="card-african p-4 text-center">
        <p class="text-bc-gold text-2xl font-bold">{{ stat.value }}</p>
        <p class="text-bc-muted text-xs mt-1">{{ stat.label }}</p>
      </div>
    </div>

    <!-- Alertas -->
    <div v-if="dashData?.stats?.low_stock_products > 0" class="bg-bc-orange/10 border border-bc-orange/30 rounded-xl p-4 mb-6 flex items-center gap-3">
      <span class="text-bc-orange text-xl">⚠</span>
      <div>
        <p class="text-bc-light font-medium">Stock baixo</p>
        <p class="text-bc-muted text-sm">{{ dashData.stats.low_stock_products }} produto(s) com stock baixo.</p>
        <RouterLink to="/loja/stock" class="text-bc-gold text-sm hover:underline">Gerir stock →</RouterLink>
      </div>
    </div>

    <!-- Acções rápidas -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
      <RouterLink to="/loja/produtos/novo" class="card-african p-4 text-center hover:border-bc-gold/60 transition">
        <span class="text-2xl block mb-1">➕</span>
        <span class="text-bc-light text-sm">Novo Produto</span>
      </RouterLink>
      <RouterLink to="/loja/pedidos" class="card-african p-4 text-center hover:border-bc-gold/60 transition relative">
        <span class="text-2xl block mb-1">📦</span>
        <span class="text-bc-light text-sm">Pedidos</span>
        <span v-if="dashData?.stats?.pending_orders > 0" class="absolute top-2 right-2 bg-bc-orange text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">{{ dashData.stats.pending_orders }}</span>
      </RouterLink>
      <RouterLink to="/loja/stock" class="card-african p-4 text-center hover:border-bc-gold/60 transition">
        <span class="text-2xl block mb-1">📊</span>
        <span class="text-bc-light text-sm">Stock</span>
      </RouterLink>
      <RouterLink to="/loja/visibilidade" class="card-african p-4 text-center hover:border-bc-gold/60 transition">
        <span class="text-2xl block mb-1">🚀</span>
        <span class="text-bc-light text-sm">Destaque</span>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const dashData = ref(null)
const store = computed(() => dashData.value?.store)

const stats = computed(() => [
  { label: 'Produtos', value: dashData.value?.stats?.total_products ?? 0 },
  { label: 'Pedidos Totais', value: dashData.value?.stats?.total_orders ?? 0 },
  { label: 'Pendentes', value: dashData.value?.stats?.pending_orders ?? 0 },
  { label: 'Receita Mensal', value: formatPrice(dashData.value?.stats?.monthly_revenue) },
])

function formatPrice(v) {
  return new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' }).format(v || 0)
}

onMounted(async () => {
  const { data } = await axios.get('/store/dashboard')
  dashData.value = data
})
</script>
