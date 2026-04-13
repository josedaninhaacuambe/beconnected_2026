<template>
  <div>
    <!-- ── Hero Banner (Mobile: Full Screen, Desktop: 45vw) ─────────────────────────────────── -->
    <section class="relative w-full overflow-hidden" style="background-color:#FFFFFF; min-height: 100vh; md:min-height: clamp(320px, 45vw, 620px);">

      <!-- Imagem decorativa (cobre toda a secção) -->
      <picture v-if="!heroBroken">
        <!-- Mobile: imagem vertical optimizada -->
        <source media="(max-width: 767px)" :srcset="'/images/Hero-Mobile.png'" />
        <!-- Desktop: webp -->
        <source media="(min-width: 768px)" :srcset="'/images/Hero-Banner2.webp'" type="image/webp" />
        <img
          :src="heroBannerUrl"
          alt=""
          class="absolute inset-0 w-full h-full object-cover object-center"
          style="pointer-events: none;"
          fetchpriority="high"
          @error="heroBroken = true"
        />
      </picture>

      <!-- Overlay escuro (mobile only) para melhor legibilidade -->
      <div class="absolute inset-0 bg-black/30 md:hidden opacity-40"></div>

      <!-- ═════════════════════════════════════════════════════════════════ 
           CONTEÚDO PRINCIPAL (Texto + Pesquisa) 
           ═════════════════════════════════════════════════════════════════ -->
      <div class="relative z-10 flex flex-col justify-end md:justify-center min-h-screen md:min-h-[clamp(320px,45vw,620px)] px-4 sm:px-14 py-12 max-w-2xl"
           style="min-height: 100vh; md:min-height: clamp(320px, 45vw, 620px);">

        <h1 class="text-3xl sm:text-5xl md:text-6xl lg:text-7xl text-white leading-tight mb-4" style="color:#ffffff;">
          COMPRA. VENDE.<br>
          <span style="color:#F07820;">CONECTA.</span>
        </h1>
        
        <!-- Texto descritivo - oculto no mobile -->
        <p class="hidden md:block text-white text-base sm:text-xl mb-8 max-w-sm font-semibold drop-shadow-lg">
          O mercado digital de Moçambique
        </p>

        <!-- Barra de pesquisa rápida no hero -->
        <form @submit.prevent="goSearch" class="flex gap-2 mb-6 w-full max-w-lg">
          <input
            v-model="heroSearch"
            type="text"
            placeholder="Pesquisar produto, marca, loja..."
            class="flex-1 rounded-xl px-4 py-3 text-sm font-medium outline-none border-2 border-gray-300 focus:border-bc-gold bg-white text-black placeholder-gray-500"
          />
          <button
            type="submit"
            class="rounded-xl font-black uppercase tracking-wider text-white transition hover:opacity-90 active:scale-95 flex items-center gap-2"
            style="background-color:#F07820; padding: 0.75rem 1.25rem; font-size: 0.9rem;"
          >
            🔍
          </button>
        </form>

        <!-- Botões principais — compactos no mobile, maiores no desktop -->
        <div class="flex flex-wrap gap-2 sm:gap-3 mb-8">
          <RouterLink
            to="/lojas"
            class="inline-flex items-center gap-1.5 sm:gap-2 font-black uppercase tracking-wide rounded-xl text-white transition hover:opacity-90 active:scale-95 text-xs sm:text-sm px-3 py-2 sm:px-6 sm:py-3.5"
            style="background-color:#F07820;"
          >
            🏪 <span class="hidden xs:inline">Visitar </span>Lojas
          </RouterLink>
          <RouterLink
            to="/produtos"
            class="inline-flex items-center gap-1.5 sm:gap-2 font-black uppercase tracking-wide rounded-xl text-white transition hover:opacity-90 active:scale-95 text-xs sm:text-sm px-3 py-2 sm:px-6 sm:py-3.5"
            style="background-color:#F07820;"
          >
            🛍 Produtos
          </RouterLink>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════ 
             PESQUISA POR LOCALIZAÇÃO (Dentro da Imagem - Mobile) 
             ═════════════════════════════════════════════════════════════════ -->
        <div class="w-full max-w-2xl space-y-4 mt-auto mb-12">
          <h3 class="text-white font-bold text-lg">Pesquisar por Localização</h3>
          
          <!-- Botões de localização perto de mim -->
          <div class="flex justify-start gap-2 flex-wrap">
            <button
              @click="goNearby('stores')"
              :disabled="gpsLoading"
              class="inline-flex items-center gap-2 px-3 py-2.5 rounded-xl border-2 font-semibold text-sm transition active:scale-95 backdrop-blur-sm"
              :class="gpsLoading ? 'border-bc-gold/30 text-bc-muted bg-black/20' : 'border-bc-gold text-white bg-black/40 hover:bg-bc-gold hover:text-bc-dark'"
            >
              <span>{{ gpsLoading ? '⏳' : '📍' }}</span>
              {{ gpsLoading ? 'A localizar...' : 'Lojas perto de mim' }}
            </button>
            <button
              @click="goNearby('products')"
              :disabled="gpsLoading"
              class="inline-flex items-center gap-2 px-3 py-2.5 rounded-xl border-2 font-semibold text-sm transition active:scale-95 backdrop-blur-sm"
              :class="gpsLoading ? 'border-bc-gold/30 text-bc-muted bg-black/20' : 'border-bc-gold text-white bg-black/40 hover:bg-bc-gold hover:text-bc-dark'"
            >
              <span>{{ gpsLoading ? '⏳' : '🛍️' }}</span>
              {{ gpsLoading ? 'A localizar...' : 'Produtos perto de mim' }}
            </button>
          </div>

          <p v-if="gpsError" class="text-red-300 text-xs bg-red-900/30 px-3 py-2 rounded-lg backdrop-blur-sm">{{ gpsError }}</p>

          <!-- Pesquisa por região (mobile) -->
          <div class="hidden md:flex items-center gap-3">
            <div class="flex-1 border-t border-bc-light/20"></div>
            <span class="text-white text-xs font-semibold">Ou pesquisa por região</span>
            <div class="flex-1 border-t border-bc-light/20"></div>
          </div>

          <div class="hidden md:flex md:flex-row gap-3 max-w-2xl">
            <select v-model="selectedProvince" @change="loadCities" class="select-african flex-1 bg-black/40 border-bc-light/30 text-white">
              <option value="" style="background:#1c2b3c; color: #ffffff;">Todas as Províncias</option>
              <option v-for="p in provinces" :key="p.id" :value="p.id" style="background:#1c2b3c; color: #ffffff;">{{ p.name }}</option>
            </select>
            <select v-model="selectedCity" class="select-african flex-1 bg-black/40 border-bc-light/30 text-white" :disabled="!selectedProvince">
              <option value="" style="background:#1c2b3c; color: #ffffff;">Todas as Cidades</option>
              <option v-for="c in cities" :key="c.id" :value="c.id" style="background:#1c2b3c; color: #ffffff;">{{ c.name }}</option>
            </select>
            <button @click="searchByLocation" class="btn-gold px-6">Ver</button>
          </div>
        </div>

        <!-- Indicadores de confiança (apenas desktop) -->
        <div class="hidden md:flex md:flex-wrap gap-6 mt-10 text-sm sm:text-base text-gray-100">
          <div class="flex items-center gap-2"><span style="color:#F07820;">🏪</span><span>+500 Lojas</span></div>
          <div class="flex items-center gap-2"><span style="color:#F07820;">📦</span><span>Entrega em todo o país</span></div>
          <div class="flex items-center gap-2"><span style="color:#F07820;">📱</span><span>eMola &amp; M-Pesa</span></div>
        </div>
      </div>
    </section>

    <!-- ⚡ HOOK 5: Pulso Vivo — Activity ticker (logo abaixo do hero) -->
    <LivePulse />

    <!-- ⚡ HOOK 1: Relâmpago — Flash Deals (só aparece se houver deals ativos) -->
    <FlashDeals />

    <!-- 🔥 HOOK 2: Em Chamas — Trending Products -->
    <TrendingProducts />

    <!-- 🌅 HOOK 3: Descoberta do Dia — Daily Drop -->
    <DailyDrop />

    <!-- Categorias de lojas -->
    <section class="container mx-auto px-4 py-12">
      <h2 class="text-2xl font-bold text-bc-gold mb-6">Categorias de Lojas</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <template v-if="isHomeDataLoading">
          <Skeleton
            :count="10"
            item-class="animate-pulse rounded-xl bg-bc-surface h-28"
            container-class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4"
          />
        </template>
        <template v-else>
          <RouterLink
            v-for="cat in storeCategories"
            :key="cat.id"
            :to="`/lojas?category=${cat.id}`"
            class="card-african flex flex-col items-center text-center p-4 hover:border-bc-gold/60 transition"
          >
            <span class="text-3xl mb-2">{{ getCategoryEmoji(cat.slug) }}</span>
            <span class="text-bc-light text-sm font-medium">{{ cat.name }}</span>
          </RouterLink>
        </template>
      </div>
    </section>

    <!-- 💸 HOOK 4: Preço Caiu — Price Drops -->
    <PriceDrops />

    <!-- Lojas em destaque -->
    <section class="container mx-auto px-4 pb-12">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-bc-gold">Lojas em Destaque</h2>
        <RouterLink to="/lojas" class="text-bc-gold text-sm hover:underline">Ver todas →</RouterLink>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <template v-if="isHomeDataLoading">
          <Skeleton
            :count="8"
            item-class="p-4 bg-bc-surface rounded-xl animate-pulse h-56"
            container-class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"
          />
        </template>
        <template v-else>
          <RouterLink
            v-for="store in featuredStores"
            :key="store.id"
            :to="`/lojas/${store.slug}`"
            class="card-african overflow-hidden hover:border-bc-gold/60 transition"
          >
            <div class="h-32 bg-bc-surface relative overflow-hidden">
              <AppImg :src="store.banner ? `/storage/${store.banner}` : ''" type="banner" class="w-full h-full object-cover" :alt="store.name" />
              <span v-if="store.is_featured" class="absolute top-2 right-2 bg-bc-gold text-bc-dark text-xs font-bold px-2 py-0.5 rounded-full">⭐ DESTAQUE</span>
            </div>
            <div class="p-3">
              <div class="flex items-center gap-2 mb-1">
                <div class="w-8 h-8 bg-bc-gold/10 rounded-full flex items-center justify-center overflow-hidden">
                  <AppImg :src="store.logo ? `/storage/${store.logo}` : ''" type="store" class="w-8 h-8 rounded-full object-cover" />
                </div>
                <h3 class="text-bc-light font-semibold text-sm">{{ store.name }}</h3>
              </div>
              <p class="text-bc-muted text-xs">{{ store.city?.name }}, {{ store.province?.name }}</p>
              <div class="flex items-center justify-between mt-2">
                <span class="text-yellow-400 text-xs">★ {{ store.rating?.toFixed(1) }}</span>
                <span class="text-bc-muted text-xs">{{ store.category?.name }}</span>
              </div>
            </div>
          </RouterLink>
        </template>
      </div>
    </section>

    <!-- Banner App -->
    <section class="bg-white mx-4 rounded-2xl p-6 mb-12 flex flex-col sm:flex-row items-center justify-between gap-4" style="border: 1px solid #e0e0e0;">
      <div>
        <h3 class="text-black font-bold text-xl">Baixa a App Beconnect</h3>
        <p class="text-gray-700 text-sm">Disponível para telemóvel e computador. Compra ainda mais fácil em qualquer dispositivo.</p>
      </div>
      <button @click="installPWA" class="bg-bc-gold text-white font-bold px-6 py-3 rounded-xl hover:bg-orange-600 transition whitespace-nowrap">
        📱 Instalar App
      </button>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted, defineAsyncComponent } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { trackEvent } from '@/utils/analytics.js'

// LivePulse está acima da dobra — carrega imediatamente
import LivePulse from '@/components/hooks/LivePulse.vue'
import Skeleton from '@/components/Skeleton.vue'

// Hooks abaixo da dobra — carregam em paralelo após o hero ser pintado
const FlashDeals       = defineAsyncComponent(() => import('@/components/hooks/FlashDeals.vue'))
const TrendingProducts = defineAsyncComponent(() => import('@/components/hooks/TrendingProducts.vue'))
const DailyDrop        = defineAsyncComponent(() => import('@/components/hooks/DailyDrop.vue'))
const PriceDrops       = defineAsyncComponent(() => import('@/components/hooks/PriceDrops.vue'))

const router = useRouter()
const heroBroken = ref(false)
const heroSearch = ref('')

function goSearch() {
  const q = heroSearch.value.trim()
  router.push({ name: 'search', query: q ? { q } : {} })
}
const heroBannerUrl = '/images/Hero-Banner2.png'
const provinces = ref([])
const cities = ref([])
const storeCategories = ref([])
const featuredStores = ref([])
const selectedProvince = ref('')
const selectedCity = ref('')
const isHomeDataLoading = ref(true)
let deferredPrompt = null

const categoryEmojis = {
  supermercado: '🛒', mercearia: '🏪', ferragem: '🔨', 'roupa-moda': '👗',
  boutique: '💍', electronica: '📱', farmacia: '💊', padaria: '🥖',
  restauracao: '🍽', livraria: '📚', calcado: '👟', moveis: '🛋',
  automovel: '🚗', beleza: '💄', desporto: '🏋', brinquedos: '🧸',
  'pet-shop': '🐾', agricultura: '🌱', electrico: '⚡', outros: '📦',
}

function getCategoryEmoji(slug) {
  return categoryEmojis[slug] || '🏪'
}

async function loadCities() {
  selectedCity.value = ''
  if (selectedProvince.value) {
    const { data } = await axios.get('/locations/cities', { params: { province_id: selectedProvince.value } })
    cities.value = data
  }
}

function searchByLocation() {
  router.push({ name: 'stores', query: { province_id: selectedProvince.value, city_id: selectedCity.value } })
}

// GPS — perto de mim
const gpsLoading = ref(false)
const gpsError   = ref('')

function goNearby(dest) {
  gpsError.value = ''
  if (!navigator.geolocation) {
    gpsError.value = 'O teu browser não suporta geolocalização.'
    return
  }
  gpsLoading.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      gpsLoading.value = false
      const { latitude: lat, longitude: lng } = pos.coords
      if (dest === 'stores') {
        router.push({ name: 'stores', query: { lat, lng, radius: 10, nearMe: 1 } })
      } else {
        router.push({ name: 'products', query: { lat, lng, radius: 10, nearMe: 1 } })
      }
    },
    (err) => {
      gpsLoading.value = false
      gpsError.value = err.code === 1
        ? 'Permissão de localização negada. Activa nas definições do browser.'
        : 'Não foi possível obter a localização. Tenta novamente.'
    },
    { timeout: 10000, maximumAge: 60000 }
  )
}

onMounted(async () => {
    trackEvent('home_skeleton_shown', { page: 'Home' })
    try {
      const [provincesRes, categoriesRes, storesRes] = await Promise.all([
        axios.get('/locations/provinces'),
        axios.get('/store-categories'),
        axios.get('/stores', { params: { per_page: 8 } }),
      ])
      provinces.value = provincesRes.data
      storeCategories.value = categoriesRes.data
      featuredStores.value = storesRes.data.data
      trackEvent('home_data_loaded', { stores: featuredStores.value.length, categories: storeCategories.value.length })
    } catch (error) {
      console.error('Erro ao carregar home data', error)
      trackEvent('hook_load_failed', { hook: 'Home', message: error.message || 'unknown' })
    } finally {
      isHomeDataLoading.value = false
    }

    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault()
      deferredPrompt = e
    })
  })
async function installPWA() {
  // Detectar dispositivo
  const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
  const isAndroid = /Android/.test(navigator.userAgent)

  if (isMobile) {
    if (isIOS) {
      // iOS - redirecionar para App Store (ou mostrar instruções PWA)
      if (deferredPrompt) {
        // Tentar instalar PWA nativa
        deferredPrompt.prompt()
        const { outcome } = await deferredPrompt.userChoice
        deferredPrompt = null
      } else {
        // Mostrar instruções para instalar PWA no iOS
        alert('Para instalar no iOS:\n1. Toque no botão compartilhar (📤)\n2. Selecione "Adicionar à Tela de Início"\n3. Toque em "Adicionar"')
      }
    } else if (isAndroid) {
      // Android - tentar instalar PWA
      if (deferredPrompt) {
        deferredPrompt.prompt()
        const { outcome } = await deferredPrompt.userChoice
        deferredPrompt = null
      } else {
        // Mostrar instruções para instalar PWA no Android
        alert('Para instalar PWA no Android:\n1. Abra o menu (⋮)\n2. Selecione "Adicionar à tela inicial"\n3. Toque em "Adicionar"')
      }
    }
  } else {
    // Desktop - tentar instalar PWA ou mostrar instruções
    if (deferredPrompt) {
      deferredPrompt.prompt()
      const { outcome } = await deferredPrompt.userChoice
      deferredPrompt = null
    } else {
      // Desktop - mostrar instruções para instalar PWA
      alert('Para instalar no computador:\n\nChrome/Edge:\n1. Clique no ícone de instalação na barra de endereço\n\nFirefox:\n1. Clique no menu (☰)\n2. Selecione "Instalar Esta Aplicação"')
    }
  }
}
</script>
