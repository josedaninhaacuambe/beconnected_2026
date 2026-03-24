<template>
  <section v-if="products.length > 0" class="price-drops-section py-10 px-4">
    <div class="container mx-auto">
      <!-- Header -->
      <div class="flex items-center gap-3 mb-6">
        <span class="text-4xl">💸</span>
        <div>
          <h2 class="text-2xl font-black text-white tracking-tight">PREÇO CAIU</h2>
          <p class="text-red-300 text-sm font-medium">Aproveita Antes Que Suba</p>
        </div>
      </div>

      <!-- Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div
          v-for="product in products"
          :key="product.id"
          class="drop-card rounded-2xl overflow-hidden relative flex flex-col"
        >
          <!-- Pulsing % badge -->
          <div class="absolute top-3 left-3 z-10">
            <span class="pct-badge font-black text-sm px-2 py-1 rounded-xl shadow-lg">
              −{{ discountPct(product) }}%
            </span>
          </div>

          <!-- Image -->
          <div class="h-40 bg-bc-surface relative overflow-hidden">
            <img
              v-if="product.images && product.images.length"
              :src="`/storage/${product.images[0]}`"
              :alt="product.name"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-5xl">💸</div>
          </div>

          <!-- Content -->
          <div class="p-4 flex flex-col flex-1">
            <p class="text-bc-muted text-xs truncate mb-1">{{ product.store?.name }}</p>
            <h3 class="text-bc-light font-semibold text-sm mb-3 line-clamp-2 leading-snug">{{ product.name }}</h3>

            <!-- Prices -->
            <div class="mb-3">
              <div class="flex items-baseline gap-2 flex-wrap">
                <span class="text-bc-gold font-black text-xl">{{ fmt(product.price) }} MT</span>
                <span class="text-bc-muted text-sm line-through">{{ fmt(product.compare_price) }} MT</span>
              </div>
              <p class="text-green-400 text-xs mt-0.5 font-semibold">
                Poupas {{ fmt(product.compare_price - product.price) }} MT
              </p>
            </div>

            <!-- Stock urgency -->
            <div v-if="product.stock?.quantity > 0 && product.stock?.quantity < 10" class="stock-warning flex items-center gap-1 px-2 py-1 rounded-lg mb-3">
              <span class="text-xs">⚠</span>
              <span class="text-orange-300 text-xs font-semibold">Só restam {{ product.stock.quantity }}</span>
            </div>

            <RouterLink
              :to="`/lojas/${product.store?.slug}`"
              class="mt-auto block text-center bg-red-700 hover:bg-red-600 text-white font-bold text-sm py-2 rounded-xl transition"
            >
              Aproveitar Agora
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const products = ref([])

async function fetchDiscounts() {
  try {
    const { data } = await axios.get('/products/discounts')
    products.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    products.value = []
  }
}

function discountPct(product) {
  if (!product.compare_price || !product.price) return 0
  return Math.round(((product.compare_price - product.price) / product.compare_price) * 100)
}

function fmt(val) {
  if (val == null) return '0'
  return Number(val).toLocaleString('pt-MZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

onMounted(fetchDiscounts)
</script>

<style scoped>
.price-drops-section {
  background: linear-gradient(135deg, #130000 0%, #111111 100%);
}

.drop-card {
  background: #1a1010;
  border: 1px solid rgba(220, 38, 38, 0.2);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.drop-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(220, 38, 38, 0.2);
  border-color: rgba(220, 38, 38, 0.45);
}

.pct-badge {
  background: #dc2626;
  color: #fff;
  animation: heartbeat 2s ease-in-out infinite;
}

@keyframes heartbeat {
  0%, 100% { transform: scale(1); }
  14%       { transform: scale(1.1); }
  28%       { transform: scale(1); }
  42%       { transform: scale(1.05); }
  70%       { transform: scale(1); }
}

.stock-warning {
  background: rgba(255, 80, 0, 0.12);
  border: 1px solid rgba(255, 80, 0, 0.3);
}
</style>
