<template>
  <div class="container mx-auto px-4 py-8 pb-mobile">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-bc-gold">Todos os Produtos</h1>
        <p class="text-bc-muted text-sm">Produtos de lojas com contrato de visibilidade</p>
      </div>
      <span class="text-bc-muted text-sm">{{ meta.total }} produto(s)</span>
    </div>

    <!-- Filtros -->
    <div class="card-african p-4 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
        <div class="relative sm:col-span-2 lg:col-span-2">
          <input
            v-model="filters.q"
            @input="debouncedSearch"
            type="text"
            placeholder="Pesquisar produto, marca, modelo..."
            class="input-african w-full pr-10"
            autofocus
          />
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-bc-muted">🔍</span>
        </div>
        <select v-model="filters.province_id" @change="loadCities(); debouncedSearch()" class="select-african">
          <option value="">Todas as Provincias</option>
          <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select v-model="filters.city_id" @change="debouncedSearch" class="select-african" :disabled="!filters.province_id">
          <option value="">Todas as Cidades</option>
          <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <!-- Linha 2: categoria + marca + near-me -->
      <div class="flex flex-wrap gap-3 items-center">
        <select v-model="filters.category_id" @change="debouncedSearch" class="select-african flex-1 min-w-[140px]">
          <option value="">Todas as categorias</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <input
          v-model="filters.brand"
          @input="debouncedSearch"
          type="text"
          placeholder="Marca"
          class="input-african flex-1 min-w-[120px]"
        />
        <button
          @click="toggleNearMe"
          :class="[
            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition border flex-shrink-0',
            nearMe ? 'bg-bc-gold text-bc-dark border-bc-gold' : 'border-bc-gold/40 text-bc-gold hover:bg-bc-gold/10'
          ]"
        >
          <span v-if="gpsLoading">...</span>
          <span v-else>📍</span>
          <span v-if="nearMe">Perto de mim</span>
          <span v-else>Perto de mim</span>
          <span v-if="nearMe" @click.stop="disableNearMe" class="text-xs opacity-70">✕</span>
        </button>
      </div>

      <div v-if="nearMe" class="mt-3 flex items-center gap-3">
        <span class="text-bc-muted text-xs whitespace-nowrap">Raio: <strong class="text-bc-light">{{ filters.radius }} km</strong></span>
        <input v-model.number="filters.radius" @input="debouncedSearch" type="range" min="1" max="50" step="1" class="flex-1 accent-bc-gold" />
      </div>
    </div>

    <!-- Esqueleto de carregamento -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <div v-for="i in 10" :key="i" class="card-african overflow-hidden">
        <div class="skeleton h-40"></div>
        <div class="p-3 space-y-2">
          <div class="skeleton h-4 w-3/4"></div>
          <div class="skeleton h-3 w-1/2"></div>
        </div>
      </div>
    </div>

    <!-- Resultados -->
    <div v-else-if="products.length === 0" class="text-center py-20">
      <p class="text-4xl mb-3">🔍</p>
      <p class="text-bc-muted">Nenhum produto encontrado.</p>
      <p v-if="filters.q" class="text-bc-muted text-sm mt-1">Tenta pesquisar com outros termos.</p>
    </div>

    <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <RouterLink
        v-for="product in products"
        :key="product.id"
        :to="`/lojas/${product.store.slug}/produtos/${product.slug}`"
        class="card-african overflow-hidden hover:border-bc-gold/60 transition group"
      >
        <div class="h-40 bg-bc-surface-2 relative overflow-hidden">
          <AppImg
            :src="product.images?.[0] ? (product.images[0].startsWith('http') ? product.images[0] : `/storage/${product.images[0]}`) : ''"
            class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
            :alt="product.name"
          />
          <span v-if="!product.in_stock" class="absolute inset-0 bg-black/60 flex items-center justify-center text-red-400 font-bold text-sm">Esgotado</span>
          <span
            v-if="product.store?.distance_km != null"
            class="absolute top-2 right-2 bg-bc-dark/80 text-bc-gold text-xs px-2 py-0.5 rounded-full font-medium"
          >~{{ product.store.distance_km }} km</span>
        </div>
        <div class="p-3">
          <h3 class="text-bc-light font-medium text-sm mb-1 line-clamp-2">{{ product.name }}</h3>
          <p v-if="product.brand" class="text-bc-muted text-xs mb-1">{{ product.brand }}</p>
          <div class="flex items-center justify-between">
            <span class="text-bc-gold text-xs font-medium truncate mr-1">{{ product.store.name }}</span>
            <span class="text-yellow-400 text-xs flex-shrink-0">★ {{ product.rating?.toFixed(1) }}</span>
          </div>
          <p class="text-bc-muted text-xs mt-1 truncate">
            📍 {{ product.store.distance_km != null ? `~${product.store.distance_km} km` : (product.store.city || '') }}
          </p>
          <div class="mt-2 text-center">
            <span class="text-bc-gold/60 text-xs border border-bc-gold/20 rounded px-2 py-0.5">Ver preco na loja</span>
          </div>
        </div>
      </RouterLink>
    </div>

    <!-- Paginacao -->
    <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-8 flex-wrap">
      <button
        v-for="p in meta.last_page"
        :key="p"
        @click="goToPage(p)"
        :class="['px-3 py-1 rounded-lg text-sm', p === currentPage ? 'bg-bc-gold text-bc-dark font-bold' : 'text-bc-muted hover:text-bc-gold border border-bc-gold/20']"
      >{{ p }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const products = ref([])
const meta = ref({ total: 0, last_page: 1 })
const loading = ref(true)
const currentPage = ref(1)
const provinces = ref([])
const cities = ref([])
const categories = ref([])

const nearMe = ref(false)
const gpsLoading = ref(false)
const userLat = ref(null)
const userLng = ref(null)

const filters = reactive({
  q: route.query.q || '',
  brand: '',
  category_id: '',
  province_id: '',
  city_id: '',
  radius: 10,
})

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => search(1), 300)
}

async function search(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const params = { page }
    if (filters.q) params.q = filters.q
    if (filters.brand) params.brand = filters.brand
    if (filters.category_id) params.category_id = filters.category_id
    if (nearMe.value && userLat.value && userLng.value) {
      params.lat = userLat.value
      params.lng = userLng.value
      params.radius = filters.radius
    } else {
      if (filters.province_id) params.province_id = filters.province_id
      if (filters.city_id) params.city_id = filters.city_id
    }
    const { data } = await axios.get('/products/search', { params })
    products.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

async function loadCities() {
  filters.city_id = ''
  if (filters.province_id) {
    const { data } = await axios.get('/locations/cities', { params: { province_id: filters.province_id } })
    cities.value = data
  }
}

function goToPage(page) { search(page) }

function toggleNearMe() {
  if (nearMe.value) { disableNearMe(); return }
  if (!navigator.geolocation) return
  gpsLoading.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      userLat.value = pos.coords.latitude
      userLng.value = pos.coords.longitude
      nearMe.value = true
      gpsLoading.value = false
      filters.province_id = ''
      filters.city_id = ''
      search()
    },
    () => { gpsLoading.value = false },
    { timeout: 10000, maximumAge: 60000 }
  )
}

function disableNearMe() {
  nearMe.value = false
  userLat.value = null
  userLng.value = null
  search()
}

onMounted(async () => {
  const [provRes, catRes] = await Promise.all([
    axios.get('/locations/provinces'),
    axios.get('/products/categories'),
  ])
  provinces.value = provRes.data
  categories.value = catRes.data
  search()
})
</script>
