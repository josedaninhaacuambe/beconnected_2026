<template>
  <div class="container mx-auto px-4 py-8 pb-mobile">
    <h1 class="text-2xl font-bold text-bc-gold mb-6">Pesquisar Produtos</h1>

    <!-- Filtros -->
    <div class="card-african p-4 mb-6">
      <!-- Linha principal de filtros -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-3">
        <input v-model="filters.q" @input="debouncedSearch" type="text" placeholder="O que procuras?" class="input-african" />
        <input v-model="filters.brand" @input="debouncedSearch" type="text" placeholder="Marca" class="input-african" />
        <input v-model="filters.model" @input="debouncedSearch" type="text" placeholder="Modelo" class="input-african" />

        <!-- Localização — modo normal OU near-me -->
        <template v-if="!nearMe">
          <select v-model="filters.province_id" @change="loadCities(); debouncedSearch()" class="select-african">
            <option value="">Todas as Províncias</option>
            <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <select v-model="filters.city_id" @change="debouncedSearch" class="select-african" :disabled="!filters.province_id">
            <option value="">Todas as Cidades</option>
            <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </template>
        <template v-else>
          <!-- Raio slider -->
          <div class="col-span-2 flex items-center gap-3">
            <span class="text-bc-muted text-xs whitespace-nowrap">Raio: <strong class="text-bc-light">{{ filters.radius }} km</strong></span>
            <input
              v-model.number="filters.radius"
              @input="debouncedSearch"
              type="range" min="1" max="50" step="1"
              class="flex-1 accent-bc-gold"
            />
          </div>
        </template>
      </div>

      <!-- Botão "Perto de mim" -->
      <div class="flex items-center gap-3 flex-wrap">
        <button
          @click="toggleNearMe"
          :class="[
            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition border',
            nearMe
              ? 'bg-bc-gold text-bc-dark border-bc-gold'
              : 'border-bc-gold/40 text-bc-gold hover:border-bc-gold hover:bg-bc-gold/10'
          ]"
        >
          <span>📍</span>
          <span v-if="gpsLoading">A localizar…</span>
          <span v-else-if="nearMe">Perto de mim (activo)</span>
          <span v-else>Perto de mim</span>
          <span v-if="nearMe" @click.stop="disableNearMe" class="ml-1 opacity-70 hover:opacity-100 text-xs">✕</span>
        </button>

        <span v-if="gpsError" class="text-red-400 text-xs">{{ gpsError }}</span>
        <span v-if="nearMe && userLat" class="text-bc-muted text-xs">📡 GPS activo</span>
      </div>

      <!-- Info importante -->
      <div class="mt-3 flex items-center gap-2 text-bc-muted text-xs bg-bc-gold/5 border border-bc-gold/20 rounded-lg p-2">
        <span class="text-bc-gold">ℹ</span>
        Os preços são visíveis apenas ao entrar na loja. Esta pesquisa mostra onde o produto está disponível para promover uma concorrência justa.
      </div>
    </div>

    <!-- Resultados -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
      <div v-for="i in 8" :key="i" class="card-african overflow-hidden">
        <div class="skeleton h-40"></div>
        <div class="p-3 space-y-2">
          <div class="skeleton h-4 w-3/4"></div>
          <div class="skeleton h-3 w-1/2"></div>
        </div>
      </div>
    </div>

    <div v-else>
      <p class="text-bc-muted text-sm mb-4">{{ meta.total }} produto(s) encontrado(s)</p>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <RouterLink
          v-for="product in products"
          :key="product.id"
          :to="`/lojas/${product.store.slug}/produtos/${product.slug}`"
          class="card-african overflow-hidden hover:border-bc-gold/60 transition group"
        >
          <div class="h-40 bg-bc-surface-2 relative overflow-hidden">
            <img
              v-if="product.images?.[0]"
              :src="product.images[0].startsWith('http') ? product.images[0] : `/storage/${product.images[0]}`"
              class="w-full h-full object-cover group-hover:scale-105 transition"
              :alt="product.name"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-bc-gold/20 text-4xl">📦</div>
            <span v-if="!product.in_stock" class="absolute inset-0 bg-black/60 flex items-center justify-center text-red-400 font-bold text-sm">Esgotado</span>

            <!-- Badge de distância -->
            <span
              v-if="product.store?.distance_km != null"
              class="absolute top-2 right-2 bg-bc-dark/80 text-bc-gold text-xs px-2 py-0.5 rounded-full font-medium"
            >~{{ product.store.distance_km }} km</span>
          </div>
          <div class="p-3">
            <h3 class="text-bc-light font-medium text-sm mb-1 line-clamp-2">{{ product.name }}</h3>
            <p v-if="product.brand" class="text-bc-muted text-xs mb-1">{{ product.brand }}</p>
            <div class="flex items-center justify-between">
              <span class="text-bc-gold text-xs font-medium">{{ product.store.name }}</span>
              <span class="text-yellow-400 text-xs">★ {{ product.rating?.toFixed(1) }}</span>
            </div>
            <p class="text-bc-muted text-xs mt-1">
              📍 {{ product.store.distance_km != null ? `~${product.store.distance_km} km` : (product.store.city || '') }}
            </p>
            <!-- SEM PREÇO - apenas na loja -->
            <div class="mt-2 text-center">
              <span class="text-bc-gold/60 text-xs border border-bc-gold/20 rounded px-2 py-0.5">Ver preço na loja →</span>
            </div>
          </div>
        </RouterLink>
      </div>

      <!-- Paginação -->
      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-8">
        <button
          v-for="page in meta.last_page"
          :key="page"
          @click="goToPage(page)"
          :class="['px-3 py-1 rounded-lg text-sm', page === currentPage ? 'bg-bc-gold text-bc-dark font-bold' : 'text-bc-muted hover:text-bc-gold border border-bc-gold/20']"
        >{{ page }}</button>
      </div>
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
const loading = ref(false)
const currentPage = ref(1)
const provinces = ref([])
const cities = ref([])

// Near-me state
const nearMe = ref(false)
const gpsLoading = ref(false)
const gpsError = ref('')
const userLat = ref(null)
const userLng = ref(null)

const filters = reactive({
  q: route.query.q || '',
  brand: route.query.brand || '',
  model: route.query.model || '',
  province_id: route.query.province_id || '',
  city_id: route.query.city_id || '',
  radius: 10,
})

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(search, 400)
}

async function search(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const params = { page }
    if (filters.q) params.q = filters.q
    if (filters.brand) params.brand = filters.brand
    if (filters.model) params.model = filters.model

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
  if (nearMe.value) {
    disableNearMe()
    return
  }
  if (!navigator.geolocation) {
    gpsError.value = 'GPS não disponível neste dispositivo.'
    return
  }
  gpsLoading.value = true
  gpsError.value = ''
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      userLat.value = pos.coords.latitude
      userLng.value = pos.coords.longitude
      nearMe.value = true
      gpsLoading.value = false
      // Clear province/city filters
      filters.province_id = ''
      filters.city_id = ''
      search()
    },
    (err) => {
      gpsLoading.value = false
      if (err.code === 1) gpsError.value = 'Permissão de localização negada.'
      else if (err.code === 2) gpsError.value = 'Localização indisponível.'
      else gpsError.value = 'Erro ao obter localização.'
    },
    { timeout: 10000, maximumAge: 60000 }
  )
}

function disableNearMe() {
  nearMe.value = false
  userLat.value = null
  userLng.value = null
  gpsError.value = ''
  search()
}

onMounted(async () => {
  const { data } = await axios.get('/locations/provinces')
  provinces.value = data
  search()
})
</script>
