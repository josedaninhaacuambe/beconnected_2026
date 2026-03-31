<template>
  <div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-bc-light mb-6">Lojas</h1>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-4">
      <input v-model="search" @input="load" type="text" placeholder="Pesquisar lojas..." class="input-african flex-1 min-w-48" />
      <select v-model="categoryId" @change="load" class="select-african">
        <option value="">Todas as categorias</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="provinceId" @change="load" class="select-african">
        <option value="">Todas as províncias</option>
        <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <!-- Botão Near Me -->
    <div class="flex items-center gap-3 mb-6">
      <button
        @click="findNearMe"
        :disabled="locating"
        class="flex items-center gap-2 px-4 py-2 rounded-xl border text-sm font-medium transition-all"
        :class="nearMeActive
          ? 'bg-bc-gold text-bc-dark border-bc-gold'
          : 'border-bc-gold/40 text-bc-gold hover:bg-bc-gold/10'"
      >
        <svg class="w-4 h-4" :class="locating ? 'animate-pulse' : ''" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
        </svg>
        {{ locating ? 'A localizar...' : nearMeActive ? `Lojas perto (${nearRadius}km)` : 'Perto de mim' }}
      </button>

      <div v-if="nearMeActive" class="flex items-center gap-2">
        <input
          v-model.number="nearRadius"
          @change="load"
          type="range"
          min="2"
          max="50"
          step="1"
          class="w-28 accent-bc-gold"
        />
        <span class="text-bc-muted text-xs w-10">{{ nearRadius }}km</span>
        <button @click="clearNearMe" class="text-bc-muted hover:text-bc-gold text-xs underline">limpar</button>
      </div>

      <p v-if="locationError" class="text-red-400 text-xs">{{ locationError }}</p>
    </div>

    <!-- Grid de lojas -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <Skeleton
        :count="6"
        item-class="skeleton h-48 rounded-2xl"
        container-class="grid grid-cols-1 md:grid-cols-3 gap-4"
      />
    </div>

    <div v-else-if="isCacheUsed" class="text-sm text-bc-muted mb-3">
      {{ cacheStatus() }} (conteúdo em cache enquanto atualiza no servidor...)
    </div>

    <div v-else-if="stores.length === 0" class="text-center py-16 text-bc-muted">
      Nenhuma loja encontrada.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <RouterLink
        v-for="store in stores"
        :key="store.id"
        :to="`/lojas/${store.slug}`"
        class="card-african overflow-hidden hover:shadow-lg transition-all group"
      >
        <!-- Capa / Banner -->
        <div class="relative h-36 bg-bc-navy">
          <!-- Imagem de capa (clipada ao banner) -->
          <div class="absolute inset-0 overflow-hidden">
            <img
              v-if="store.banner"
              :src="store.banner.startsWith('http') ? store.banner : `/storage/${store.banner}`"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              :alt="store.name"
            />
            <div v-else class="w-full h-full bg-gradient-to-br from-bc-navy to-bc-gold/30 flex items-center justify-center">
              <span class="text-white/20 text-5xl font-black tracking-wider">{{ store.name?.charAt(0) }}</span>
            </div>
          </div>

          <!-- Badge distância -->
          <span
            v-if="store.distance != null"
            class="absolute top-2 right-2 bg-black/60 text-white text-[10px] font-bold px-2 py-0.5 rounded-full z-10"
          >
            📍 {{ store.distance.toFixed(1) }}km
          </span>

          <!-- Logo posicionado na borda inferior da capa -->
          <div class="absolute bottom-0 left-4 translate-y-1/2 z-20 w-16 h-16 rounded-xl border-[3px] border-white shadow-lg overflow-hidden bg-white flex-shrink-0">
            <img
              v-if="store.logo"
              :src="store.logo.startsWith('http') ? store.logo : `/storage/${store.logo}`"
              class="w-full h-full object-cover"
              :alt="store.name"
            />
            <div v-else class="w-full h-full bg-bc-gold flex items-center justify-center">
              <span class="text-white font-black text-xl">{{ store.name?.charAt(0) }}</span>
            </div>
          </div>
        </div>

        <!-- Info abaixo da capa -->
        <div class="px-4 pb-4 pt-11">
          <!-- Nome e categoria -->
          <p class="text-bc-light font-bold text-base group-hover:text-bc-gold transition leading-tight">
            {{ store.name }}
          </p>
          <p v-if="store.category?.name" class="text-bc-muted text-xs mb-2">{{ store.category.name }}</p>

          <!-- Estrelas de avaliação -->
          <div class="flex items-center gap-1.5 mb-2">
            <div class="flex items-center gap-0.5">
              <template v-for="i in 5" :key="i">
                <svg
                  class="w-3.5 h-3.5"
                  :class="i <= Math.round(store.rating ?? 0) ? 'text-yellow-400' : 'text-gray-300'"
                  fill="currentColor" viewBox="0 0 20 20"
                >
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
              </template>
            </div>
            <span class="text-xs text-bc-muted">
              {{ store.rating ? store.rating.toFixed(1) : 'Sem avaliação' }}
              <span v-if="store.total_reviews"> ({{ store.total_reviews }})</span>
            </span>
          </div>

          <!-- Produtos + localização -->
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-1 text-xs text-bc-muted">
              <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
              </svg>
              <span>{{ [store.city?.name, store.province?.name].filter(Boolean).join(', ') || 'Moçambique' }}</span>
            </div>
            <span class="text-bc-gold text-xs font-semibold">
              {{ store.total_products ?? 0 }} produtos
            </span>
          </div>

          <!-- Descrição -->
          <p class="text-bc-muted text-xs line-clamp-2 min-h-[2rem]">
            {{ store.description || 'Clica para explorar os produtos desta loja.' }}
          </p>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import Skeleton from '@/components/Skeleton.vue'
import { trackEvent } from '@/utils/analytics.js'

const stores = ref([])
const categories = ref([])
const provinces = ref([])
const loading = ref(true)
const isCacheUsed = ref(false)
const cacheLastUpdated = ref(null)
const search = ref('')
const categoryId = ref('')
const provinceId = ref('')

const STORE_CACHE_KEY = 'beconnect_stores_cache'
const STORE_CACHE_TTL_MS = 2 * 60 * 1000 // 2 minutos

function setStoreCache(payload) {
  localStorage.setItem(
    STORE_CACHE_KEY,
    JSON.stringify({ updatedAt: Date.now(), stores: payload })
  )
}

function getStoreCache() {
  try {
    const raw = localStorage.getItem(STORE_CACHE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed?.updatedAt || !Array.isArray(parsed.stores)) return null

    if (Date.now() - parsed.updatedAt > STORE_CACHE_TTL_MS) {
      localStorage.removeItem(STORE_CACHE_KEY)
      return null
    }

    return parsed
  } catch (error) {
    localStorage.removeItem(STORE_CACHE_KEY)
    return null
  }
}

function cacheStatus() {
  if (!isCacheUsed.value || !cacheLastUpdated.value) return null
  return `Dados de cache carregados há ${Math.round((Date.now() - cacheLastUpdated.value) / 1000)}s`
}

// Near me
const nearMeActive = ref(false)
const locating = ref(false)
const locationError = ref('')
const nearLat = ref(null)
const nearLng = ref(null)
const nearRadius = ref(10)

async function load() {
  const wasCacheLoaded = isCacheUsed.value

  if (!wasCacheLoaded) {
    loading.value = true
  }

  try {
    const params = {
      search: search.value,
      category_id: categoryId.value,
      province_id: provinceId.value,
    }
    if (nearMeActive.value && nearLat.value && nearLng.value) {
      params.lat = nearLat.value
      params.lng = nearLng.value
      params.radius = nearRadius.value
    }
    const { data } = await axios.get('/stores', { params })
    stores.value = data.data ?? data
    setStoreCache(stores.value)
    isCacheUsed.value = false
    cacheLastUpdated.value = Date.now()
    trackEvent('stores_data_loaded', { source: 'network', count: stores.value.length })
  } catch (error) {
    if (!wasCacheLoaded) {
      trackEvent('hook_load_failed', { hook: 'Stores', message: error.message || 'unknown' })
    }
  } finally {
    loading.value = false
  }
}

function findNearMe() {
  if (nearMeActive.value) return
  if (!navigator.geolocation) {
    locationError.value = 'O teu dispositivo não suporta geolocalização.'
    return
  }
  locating.value = true
  locationError.value = ''
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      nearLat.value = pos.coords.latitude
      nearLng.value = pos.coords.longitude
      nearMeActive.value = true
      locating.value = false
      load()
    },
    (err) => {
      locating.value = false
      if (err.code === 1) {
        locationError.value = 'Permissão de localização negada. Activa a localização no browser.'
      } else {
        locationError.value = 'Não foi possível obter a localização. Tenta novamente.'
      }
    },
    { timeout: 10000, maximumAge: 60000 }
  )
}

function clearNearMe() {
  nearMeActive.value = false
  nearLat.value = null
  nearLng.value = null
  locationError.value = ''
  load()
}

onMounted(async () => {
  trackEvent('home_skeleton_shown', { page: 'Stores' })

  const cache = getStoreCache()
  if (cache) {
    stores.value = cache.stores
    isCacheUsed.value = true
    cacheLastUpdated.value = cache.updatedAt
    loading.value = false
    trackEvent('stores_data_loaded', { source: 'cache', count: stores.value.length })
  }

  try {
    const [catsRes, provsRes] = await Promise.all([
      axios.get('/store-categories'),
      axios.get('/locations/provinces'),
    ])
    categories.value = catsRes.data
    provinces.value = provsRes.data
  } catch (e) {
    trackEvent('hook_load_failed', { hook: 'StoresMeta', message: e.message || 'unknown' })
  } finally {
    await load()
  }
})
</script>
