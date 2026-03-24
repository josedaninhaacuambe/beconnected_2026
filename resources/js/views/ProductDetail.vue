<template>
  <div v-if="product" class="max-w-5xl mx-auto px-4 py-8 pb-mobile">
    <RouterLink :to="`/lojas/${route.params.storeSlug}`" class="text-bc-muted hover:text-bc-gold text-sm mb-6 inline-flex items-center gap-1">
      ← Voltar à loja
    </RouterLink>

    <!-- Produto principal -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
      <!-- Galeria de imagens -->
      <div>
        <div class="aspect-square rounded-2xl overflow-hidden bg-bc-surface-2">
          <img
            :src="currentImage"
            class="w-full h-full object-cover"
            :alt="product.name"
            @error="currentImage = null"
          />
          <div v-if="!currentImage" class="w-full h-full flex items-center justify-center text-bc-gold/20 text-8xl">📦</div>
        </div>
        <div v-if="allImages.length > 1" class="flex gap-2 mt-3 overflow-x-auto pb-1">
          <button
            v-for="(img, i) in allImages"
            :key="i"
            @click="currentImage = img"
            class="flex-shrink-0 w-16 h-16 rounded-xl overflow-hidden border-2 transition"
            :class="currentImage === img ? 'border-bc-gold' : 'border-bc-surface-2 hover:border-bc-gold/40'"
          >
            <img :src="img" class="w-full h-full object-cover" />
          </button>
        </div>
      </div>

      <!-- Info + compra -->
      <div class="flex flex-col">
        <p class="text-bc-muted text-xs mb-1">{{ product.category?.name }}</p>
        <h1 class="text-2xl font-bold text-bc-light mb-1">{{ product.name }}</h1>
        <p v-if="product.brand" class="text-bc-muted text-sm mb-3">
          {{ product.brand.name }}<span v-if="product.model"> · {{ product.model }}</span>
        </p>

        <!-- Preço -->
        <div class="flex items-baseline gap-3 mb-4">
          <span class="text-3xl font-bold text-bc-gold">{{ formatMZN(product.price) }}</span>
          <span v-if="product.compare_price && product.compare_price > product.price" class="text-bc-muted line-through text-lg">
            {{ formatMZN(product.compare_price) }}
          </span>
          <span v-if="discount > 0" class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
            -{{ discount }}%
          </span>
        </div>

        <!-- Stock -->
        <div class="mb-5">
          <span v-if="stockQty > 0" class="inline-flex items-center gap-1.5 text-green-400 text-sm bg-green-400/10 px-3 py-1 rounded-full">
            <span class="w-2 h-2 bg-green-400 rounded-full"></span>
            Em stock · {{ stockQty }} unidades
          </span>
          <span v-else class="inline-flex items-center gap-1.5 text-red-400 text-sm bg-red-400/10 px-3 py-1 rounded-full">
            <span class="w-2 h-2 bg-red-400 rounded-full"></span>
            Esgotado
          </span>
        </div>

        <!-- Quantidade -->
        <div class="mb-5">
          <p class="text-bc-muted text-xs mb-2">Quantidade</p>
          <div class="flex items-center gap-4 bg-bc-surface rounded-xl p-3 w-fit">
            <button
              @click="qty = Math.max(1, qty - 1)"
              class="w-9 h-9 rounded-full border border-bc-gold/40 text-bc-gold font-bold text-lg flex items-center justify-center hover:bg-bc-gold/10 transition"
            >−</button>
            <span class="text-bc-light font-bold text-xl w-10 text-center tabular-nums">{{ qty }}</span>
            <button
              @click="qty = Math.min(stockQty, qty + 1)"
              :disabled="qty >= stockQty"
              class="w-9 h-9 rounded-full border border-bc-gold/40 text-bc-gold font-bold text-lg flex items-center justify-center hover:bg-bc-gold/10 transition disabled:opacity-40"
            >+</button>
          </div>
          <p class="text-bc-muted text-xs mt-2">Subtotal: <span class="text-bc-gold font-semibold">{{ formatMZN(product.price * qty) }}</span></p>
        </div>

        <!-- Botão adicionar -->
        <button
          @click="addToCart"
          :disabled="!stockQty || addingToCart"
          class="btn-green w-full py-3 text-base mb-3 disabled:opacity-50"
        >
          <span v-if="addingToCart">A adicionar...</span>
          <span v-else-if="added">✓ Adicionado ao carrinho!</span>
          <span v-else>🛒 Adicionar ao Carrinho</span>
        </button>

        <p v-if="cartError" class="text-red-400 text-xs text-center">{{ cartError }}</p>

        <!-- SKU / Barcode -->
        <div class="mt-4 pt-4 border-t border-bc-gold/10 space-y-1">
          <p v-if="product.sku" class="text-bc-muted text-xs">SKU: <span class="text-bc-light">{{ product.sku }}</span></p>
          <p v-if="product.barcode" class="text-bc-muted text-xs">Código de barras: <span class="text-bc-light">{{ product.barcode }}</span></p>
        </div>
      </div>
    </div>

    <!-- Descrição e detalhes -->
    <div class="card-african p-6 mb-8">
      <h2 class="text-bc-gold font-semibold mb-4">Descrição do Produto</h2>
      <p v-if="product.description" class="text-bc-muted text-sm leading-relaxed whitespace-pre-line">{{ product.description }}</p>
      <p v-else class="text-bc-muted text-sm italic">Sem descrição disponível.</p>

      <!-- Atributos adicionais -->
      <div v-if="product.attributes && Object.keys(product.attributes).length" class="mt-5 pt-5 border-t border-bc-gold/10">
        <h3 class="text-bc-light text-sm font-semibold mb-3">Características</h3>
        <div class="grid grid-cols-2 gap-2">
          <div v-for="(val, key) in product.attributes" :key="key" class="flex justify-between bg-bc-surface-2 rounded-lg px-3 py-2">
            <span class="text-bc-muted text-xs capitalize">{{ key }}</span>
            <span class="text-bc-light text-xs font-medium">{{ val }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Avaliações -->
    <div v-if="product.reviews?.length" class="card-african p-6 mb-8">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-bc-gold font-semibold">Avaliações</h2>
        <div class="flex items-center gap-2">
          <span class="text-yellow-400 font-bold">★ {{ product.rating?.toFixed(1) }}</span>
          <span class="text-bc-muted text-xs">({{ product.total_reviews }} avaliações)</span>
        </div>
      </div>
      <div class="space-y-3">
        <div v-for="review in product.reviews.slice(0, 5)" :key="review.id" class="bg-bc-surface-2 rounded-xl p-3">
          <div class="flex items-center justify-between mb-1">
            <p class="text-bc-light text-sm font-medium">{{ review.user?.name }}</p>
            <span class="text-yellow-400 text-xs">{{ '★'.repeat(review.rating) }}</span>
          </div>
          <p class="text-bc-muted text-xs">{{ review.comment }}</p>
        </div>
      </div>
    </div>

    <!-- Outros produtos da mesma loja -->
    <div v-if="relatedProducts.length" class="mb-8">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-bc-gold font-semibold">Mais desta loja</h2>
        <RouterLink :to="`/lojas/${route.params.storeSlug}`" class="text-bc-muted text-xs hover:text-bc-gold">Ver todos →</RouterLink>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <RouterLink
          v-for="p in relatedProducts"
          :key="p.id"
          :to="`/lojas/${route.params.storeSlug}/produtos/${p.slug}`"
          class="card-african overflow-hidden hover:border-bc-gold/60 transition"
        >
          <div class="h-36 bg-bc-surface-2 overflow-hidden">
            <img
              v-if="p.images?.[0]"
              :src="p.images[0].startsWith('http') ? p.images[0] : `/storage/${p.images[0]}`"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-bc-gold/20 text-3xl">📦</div>
          </div>
          <div class="p-3">
            <p class="text-bc-light text-xs font-medium line-clamp-2 mb-1">{{ p.name }}</p>
            <p class="text-bc-gold text-sm font-bold">{{ formatMZN(p.price) }}</p>
          </div>
        </RouterLink>
      </div>
    </div>
  </div>

  <!-- Loading skeleton -->
  <div v-else-if="loading" class="max-w-5xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
      <div class="skeleton aspect-square rounded-2xl"></div>
      <div class="space-y-4">
        <div class="skeleton h-6 rounded w-1/3"></div>
        <div class="skeleton h-8 rounded w-3/4"></div>
        <div class="skeleton h-10 rounded w-1/2"></div>
        <div class="skeleton h-24 rounded"></div>
        <div class="skeleton h-12 rounded"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { useCartStore } from '../stores/cart.js'
import { useAuthStore } from '../stores/auth.js'
import { useLoginModal } from '../composables/useLoginModal.js'

const route = useRoute()
const cartStore = useCartStore()
const authStore = useAuthStore()
const { open: openLoginModal } = useLoginModal()

const product = ref(null)
const relatedProducts = ref([])
const loading = ref(true)
const qty = ref(1)
const addingToCart = ref(false)
const added = ref(false)
const cartError = ref('')
const currentImage = ref(null)

const stockQty = computed(() => product.value?.stock?.quantity ?? product.value?.stock_quantity ?? 0)

const allImages = computed(() => {
  const imgs = product.value?.images ?? []
  return imgs.map(img => img.startsWith('http') ? img : `/storage/${img}`)
})

const discount = computed(() => {
  const p = product.value
  if (!p?.compare_price || p.compare_price <= p.price) return 0
  return Math.round((1 - p.price / p.compare_price) * 100)
})

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

function addToCart() {
  if (!authStore.isAuthenticated) {
    openLoginModal({ afterLogin: () => doAddToCart() })
    return
  }
  doAddToCart()
}

async function doAddToCart() {
  addingToCart.value = true
  cartError.value = ''
  try {
    await cartStore.addItem(product.value.id, qty.value)
    added.value = true
    setTimeout(() => { added.value = false }, 3000)
  } catch (e) {
    cartError.value = e.response?.data?.message || 'Erro ao adicionar.'
  } finally {
    addingToCart.value = false
  }
}

async function loadProduct() {
  loading.value = true
  qty.value = 1
  added.value = false
  try {
    const { data } = await axios.get(`/stores/${route.params.storeSlug}/products/${route.params.productSlug}`)
    product.value = data
    currentImage.value = allImages.value[0] ?? null

    // Carregar produtos relacionados (mesma loja, excluir este)
    const res = await axios.get(`/stores/${route.params.storeSlug}/products`, {
      params: { per_page: 8 }
    })
    const all = res.data.data ?? res.data
    relatedProducts.value = all.filter(p => p.id !== data.id).slice(0, 4)
  } finally {
    loading.value = false
  }
}

// Recarregar quando a rota muda (navegar entre produtos)
watch(() => route.params.productSlug, (slug) => {
  if (slug) loadProduct()
})

onMounted(loadProduct)
</script>
