<template>
  <div>
    <!-- ── Hero Banner ─────────────────────────────────────────────────── -->
    <section class="relative w-full overflow-hidden" style="background-color:#1C2B3C; min-height: clamp(320px, 45vw, 620px);">

      <!-- Imagem decorativa (cobre toda a secção) -->
      <picture v-if="!heroBroken">
        <source :srcset="'/images/Hero-Banner2.webp'" type="image/webp" />
        <img
          :src="heroBannerUrl"
          alt=""
          class="absolute inset-0 w-full h-full object-cover object-center"
          style="pointer-events: none;"
          fetchpriority="high"
          @error="heroBroken = true"
        />
      </picture>

      <!-- Gradiente para legibilidade do texto à esquerda -->
      <div class="absolute inset-0" style="background: linear-gradient(to right, #1C2B3C 35%, #1C2B3Ccc 52%, transparent 70%);"></div>

      <!-- Conteúdo real (texto + botões funcionais) -->
      <div class="relative z-10 flex flex-col justify-center h-full px-6 sm:px-14 py-12 max-w-2xl"
           style="min-height: clamp(320px, 45vw, 620px);">

        <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black text-white leading-tight mb-4">
          COMPRA. VENDE.<br>
          <span style="color:#F07820;">CONECTA.</span>
        </h1>
        <p class="text-white/70 text-base sm:text-xl mb-8 max-w-sm">
          O mercado digital de Moçambique
        </p>

        <!-- Barra de pesquisa rápida no hero -->
        <form @submit.prevent="goSearch" class="flex gap-2 mb-6 w-full max-w-lg">
          <input
            v-model="heroSearch"
            type="text"
            placeholder="Pesquisar produto, marca, loja..."
            class="flex-1 rounded-xl px-4 py-3 text-sm font-medium outline-none border-2 border-white/20 focus:border-bc-gold bg-white/10 text-white placeholder-white/50 backdrop-blur-sm"
          />
          <button
            type="submit"
            class="rounded-xl font-black uppercase tracking-wider text-white transition hover:opacity-90 active:scale-95 flex items-center gap-2"
            style="background-color:#F07820; padding: 0.75rem 1.25rem; font-size: 0.9rem;"
          >
            🔍
          </button>
        </form>

        <!-- Botões grandes e funcionais -->
        <div class="flex flex-nowrap gap-3">
          <RouterLink
            to="/lojas"
            class="inline-flex items-center gap-3 font-black uppercase tracking-wider rounded-xl text-white transition hover:opacity-90 active:scale-95"
            style="background-color:#F07820; padding: 0.875rem 2rem; font-size: 1rem;"
          >
            🏪 Visitar Lojas
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          </RouterLink>
          <RouterLink
            to="/produtos"
            class="inline-flex items-center gap-3 font-black uppercase tracking-wider rounded-xl text-white transition hover:opacity-90 active:scale-95"
            style="background-color:#F07820; padding: 0.875rem 2rem; font-size: 1rem;"
          >
            🛍 Todos os Produtos
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          </RouterLink>
        </div>

        <!-- Indicadores de confiança -->
        <div class="flex flex-wrap gap-6 mt-10 text-sm sm:text-base text-white/70">
          <div class="flex items-center gap-2"><span style="color:#F07820;">🏪</span><span>+500 Lojas</span></div>
          <div class="flex items-center gap-2"><span style="color:#F07820;">📦</span><span>Entrega em todo o país</span></div>
          <div class="flex items-center gap-2"><span style="color:#F07820;">📱</span><span>eMola &amp; M-Pesa</span></div>
        </div>
      </div>
    </section>

    <!-- ⚡ HOOK 5: Pulso Vivo — Activity ticker (logo abaixo do hero) -->
    <LivePulse />

    <!-- Pesquisa por localização -->
    <section class="bg-bc-surface py-8 px-4 border-y border-bc-gold/10">
      <div class="container mx-auto">
        <h2 class="text-center text-bc-gold font-semibold mb-4">Pesquisar por Localização</h2>
        <div class="flex flex-col sm:flex-row gap-3 max-w-2xl mx-auto">
          <select v-model="selectedProvince" @change="loadCities" class="select-african flex-1">
            <option value="">Todas as Províncias</option>
            <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <select v-model="selectedCity" class="select-african flex-1" :disabled="!selectedProvince">
            <option value="">Todas as Cidades</option>
            <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <button @click="searchByLocation" class="btn-gold px-6">Ver Lojas</button>
        </div>
      </div>
    </section>

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
        <RouterLink
          v-for="cat in storeCategories"
          :key="cat.id"
          :to="`/lojas?category=${cat.id}`"
          class="card-african flex flex-col items-center text-center p-4 hover:border-bc-gold/60 transition"
        >
          <span class="text-3xl mb-2">{{ getCategoryEmoji(cat.slug) }}</span>
          <span class="text-bc-light text-sm font-medium">{{ cat.name }}</span>
        </RouterLink>
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
        <RouterLink
          v-for="store in featuredStores"
          :key="store.id"
          :to="`/lojas/${store.slug}`"
          class="card-african overflow-hidden hover:border-bc-gold/60 transition"
        >
          <div class="h-32 bg-bc-surface relative">
            <AppImg v-if="store.banner" :src="`/storage/${store.banner}`" class="w-full h-full object-cover" :alt="store.name" />
            <span v-if="store.is_featured" class="absolute top-2 right-2 bg-bc-gold text-bc-dark text-xs font-bold px-2 py-0.5 rounded-full">⭐ DESTAQUE</span>
          </div>
          <div class="p-3">
            <div class="flex items-center gap-2 mb-1">
              <div class="w-8 h-8 bg-bc-gold/10 rounded-full flex items-center justify-center">
                <AppImg v-if="store.logo" :src="`/storage/${store.logo}`" class="w-8 h-8 rounded-full object-cover" />
                <span v-else class="text-bc-gold text-sm font-bold">{{ store.name.charAt(0) }}</span>
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
      </div>
    </section>

    <!-- Banner Android -->
    <section class="bg-gradient-to-r from-bc-gold to-bc-orange mx-4 rounded-2xl p-6 mb-12 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div>
        <h3 class="text-bc-dark font-bold text-xl">Baixa a App Beconnect</h3>
        <p class="text-bc-dark/80 text-sm">Disponível para Android. Compra ainda mais fácil no teu telemóvel.</p>
      </div>
      <button @click="installPWA" class="bg-bc-dark text-bc-gold font-bold px-6 py-3 rounded-xl hover:bg-black transition whitespace-nowrap">
        📱 Instalar App
      </button>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted, defineAsyncComponent } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

// LivePulse está acima da dobra — carrega imediatamente
import LivePulse from '@/components/hooks/LivePulse.vue'

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

onMounted(async () => {
  const [provincesRes, categoriesRes, storesRes] = await Promise.all([
    axios.get('/locations/provinces'),
    axios.get('/store-categories'),
    axios.get('/stores', { params: { per_page: 8 } }),
  ])
  provinces.value = provincesRes.data
  storeCategories.value = categoriesRes.data
  featuredStores.value = storesRes.data.data

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    deferredPrompt = e
  })
})

async function installPWA() {
  if (deferredPrompt) {
    deferredPrompt.prompt()
    const { outcome } = await deferredPrompt.userChoice
    deferredPrompt = null
  }
}
</script>
