<template>
  <div class="p-6 max-w-2xl mx-auto">
    <h1 class="text-xl font-bold text-bc-light mb-6">Configurações da Loja</h1>

    <div v-if="loading" class="space-y-3">
      <div class="skeleton h-48 rounded-xl"></div>
      <div class="skeleton h-32 rounded-xl"></div>
    </div>

    <form v-else @submit.prevent="save" class="space-y-5">

      <!-- Banner da Loja -->
      <div class="card-african p-5">
        <h2 class="text-bc-gold font-semibold mb-3">Imagem de Capa</h2>
        <p class="text-bc-muted text-xs mb-3">Recomendado: 1200×400px (jpg/png, máx. 5MB)</p>
        <div
          class="relative w-full h-36 rounded-xl overflow-hidden border-2 border-dashed border-bc-gold/30 hover:border-bc-gold/60 cursor-pointer transition group"
          @click="$refs.bannerInput.click()"
        >
          <img v-if="bannerPreview" :src="bannerPreview" class="w-full h-full object-cover" />
          <div v-else-if="currentBanner" class="w-full h-full">
            <img :src="currentBanner.startsWith('http') ? currentBanner : `/storage/${currentBanner}`" class="w-full h-full object-cover" />
          </div>
          <div v-else class="w-full h-full flex flex-col items-center justify-center text-bc-muted gap-1">
            <span class="text-3xl">🖼</span>
            <span class="text-sm">Clica para adicionar capa</span>
          </div>
          <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
            <span class="text-white text-sm font-medium">📷 Alterar capa</span>
          </div>
        </div>
        <input ref="bannerInput" type="file" accept="image/*" class="hidden" @change="onBannerChange" />
        <p v-if="bannerFile" class="text-xs text-green-400 mt-1">✓ {{ bannerFile.name }} selecionada</p>
      </div>

      <!-- Logo da Loja -->
      <div class="card-african p-5">
        <h2 class="text-bc-gold font-semibold mb-3">Logo da Loja</h2>
        <p class="text-bc-muted text-xs mb-3">Recomendado: 400×400px (jpg/png, máx. 2MB)</p>
        <div class="flex items-center gap-4">
          <div
            class="w-24 h-24 rounded-2xl overflow-hidden border-2 border-dashed border-bc-gold/30 hover:border-bc-gold cursor-pointer flex items-center justify-center bg-bc-surface-2 transition relative group flex-shrink-0"
            @click="$refs.logoInput.click()"
          >
            <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-cover" />
            <img v-else-if="currentLogo" :src="currentLogo.startsWith('http') ? currentLogo : `/storage/${currentLogo}`" class="w-full h-full object-cover" />
            <span v-else class="text-bc-gold text-2xl font-bold">{{ form.name?.charAt(0) || '?' }}</span>
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-2xl">
              <span class="text-white text-xs">✏</span>
            </div>
          </div>
          <div>
            <button type="button" @click="$refs.logoInput.click()" class="btn-ghost text-sm px-4 py-2">
              📷 Alterar Logo
            </button>
            <p v-if="logoFile" class="text-xs text-green-400 mt-1">✓ {{ logoFile.name }}</p>
          </div>
        </div>
        <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="onLogoChange" />
      </div>

      <!-- Informações Gerais -->
      <div class="card-african p-5 space-y-4">
        <h2 class="text-bc-gold font-semibold">Informações Gerais</h2>
        <input v-model="form.name" type="text" placeholder="Nome da loja *" class="input-african" required />
        <textarea v-model="form.description" placeholder="Descrição" rows="3" class="input-african resize-none"></textarea>
        <input v-model="form.phone" type="tel" placeholder="Telefone da loja" class="input-african" />
        <input v-model="form.whatsapp" type="tel" placeholder="WhatsApp (opcional)" class="input-african" />
      </div>

      <!-- ─── Localização da Loja ──────────────────────────────── -->
      <div class="card-african p-5 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-bc-gold font-semibold">Localização da Loja</h2>
          <span v-if="form.latitude && form.longitude" class="text-green-400 text-xs flex items-center gap-1">📍 Localização definida</span>
        </div>
        <p class="text-bc-muted text-xs">O endereço exacto ajuda os clientes a encontrar a tua loja e melhora a pesquisa por proximidade.</p>

        <!-- Campo de endereço -->
        <div class="relative">
          <input
            ref="addressInput"
            v-model="form.address"
            type="text"
            placeholder="Pesquisar endereço no mapa..."
            class="input-african pr-10"
            @input="onAddressInput"
          />
          <!-- Indicador Google Maps activo -->
          <span v-if="mapsReady" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-bc-gold">🗺</span>
        </div>

        <!-- Botões de acção -->
        <div class="flex gap-2 flex-wrap">
          <button
            type="button"
            @click="detectGPS"
            :disabled="gpsLoading"
            class="flex items-center gap-2 text-sm border border-bc-gold/40 text-bc-gold rounded-xl px-4 py-2 hover:bg-bc-gold/10 transition"
          >
            <span>📍</span>
            <span>{{ gpsLoading ? 'A localizar...' : 'Usar a minha localização' }}</span>
          </button>
          <button
            v-if="form.latitude && form.longitude"
            type="button"
            @click="clearLocation"
            class="flex items-center gap-2 text-sm border border-red-400/40 text-red-400 rounded-xl px-4 py-2 hover:bg-red-400/10 transition"
          >
            ✕ Limpar localização
          </button>
        </div>

        <p v-if="gpsError" class="text-red-400 text-xs">{{ gpsError }}</p>

        <!-- Mapa satélite Google Maps -->
        <div v-if="mapsReady" class="space-y-1">
          <div ref="mapContainer" class="w-full h-72 rounded-xl overflow-hidden border border-bc-gold/20"></div>
          <p class="text-bc-muted text-xs">
            {{ form.latitude ? 'Arrasta o marcador 📍 para ajustar a posição exacta.' : 'Clica no mapa ou usa o botão acima para marcar a localização.' }}
          </p>
        </div>

        <!-- Fallback: sem API key -->
        <div v-else class="bg-bc-surface-2 rounded-xl p-4 text-xs text-bc-muted space-y-2">
          <p>⚠ Mapa interactivo requer chave <code class="text-bc-gold">VITE_GOOGLE_MAPS_KEY</code> no ficheiro <code class="text-bc-gold">.env</code>.</p>
          <div v-if="form.latitude" class="flex items-center gap-2 text-green-400">
            <span>📍</span> Localização definida
          </div>
        </div>
      </div>

      <!-- Webhook de Stock -->
      <div class="card-african p-5">
        <h2 class="text-bc-gold font-semibold mb-2">Webhook de Stock</h2>
        <p class="text-bc-muted text-xs mb-2">Sistemas externos podem enviar stock para este URL:</p>
        <div class="bg-bc-surface-2 rounded-xl p-3 font-mono text-xs text-bc-light break-all select-all">
          POST {{ appUrl }}/api/stores/{{ storeSlug }}/stock/webhook
        </div>
      </div>

      <p v-if="saved" class="text-green-400 text-sm text-center">✓ Configurações guardadas com sucesso!</p>
      <p v-if="error" class="text-red-400 text-sm">{{ error }}</p>

      <button type="submit" :disabled="saving" class="btn-green w-full py-3">
        {{ saving ? 'A guardar...' : 'Guardar Configurações' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

const loading   = ref(true)
const saving    = ref(false)
const saved     = ref(false)
const error     = ref('')
const storeSlug = ref('')
const appUrl    = window.location.origin

const currentLogo   = ref('')
const currentBanner = ref('')
const logoFile      = ref(null)
const bannerFile    = ref(null)
const logoPreview   = ref('')
const bannerPreview = ref('')

const gpsLoading = ref(false)
const gpsError   = ref('')
const mapsReady  = ref(false)

const addressInput  = ref(null)
const mapContainer  = ref(null)

const form = reactive({
  name: '', description: '', phone: '', whatsapp: '',
  address: '', latitude: null, longitude: null,
})

// Instâncias Google Maps
let mapInstance   = null
let markerInstance = null
let autocomplete  = null
let mapsScriptEl  = null

// ─── Google Maps ──────────────────────────────────────────────
const MAPS_KEY = import.meta.env.VITE_GOOGLE_MAPS_KEY

function loadGoogleMaps() {
  if (!MAPS_KEY) return
  if (window.google?.maps) { initMap(); return }

  window.__mapsCallback = () => { mapsReady.value = true; nextTick(initMap) }
  mapsScriptEl = document.createElement('script')
  mapsScriptEl.src = `https://maps.googleapis.com/maps/api/js?key=${MAPS_KEY}&libraries=places&callback=__mapsCallback`
  mapsScriptEl.async = true
  mapsScriptEl.defer = true
  document.head.appendChild(mapsScriptEl)
}

function initMap() {
  mapsReady.value = true
  nextTick(() => {
    if (!mapContainer.value) return

    const center = form.latitude && form.longitude
      ? { lat: parseFloat(form.latitude), lng: parseFloat(form.longitude) }
      : { lat: -18.9167, lng: 35.2833 } // Centro de Moçambique (Sofala)

    mapInstance = new window.google.maps.Map(mapContainer.value, {
      center,
      zoom: form.latitude ? 17 : 6,
      mapTypeId: 'hybrid',
      disableDefaultUI: false,
      zoomControl: true,
      mapTypeControl: true,
      mapTypeControlOptions: {
        style: window.google.maps.MapTypeControlStyle.DROPDOWN_MENU,
        mapTypeIds: ['hybrid', 'roadmap', 'satellite'],
      },
      streetViewControl: false,
    })

    markerInstance = new window.google.maps.Marker({
      position: center,
      map: mapInstance,
      draggable: true,
      title: 'Localização da loja',
      visible: !!(form.latitude && form.longitude),
    })

    // Arrastar marcador actualiza coordenadas
    markerInstance.addListener('dragend', (e) => {
      form.latitude  = e.latLng.lat().toFixed(7)
      form.longitude = e.latLng.lng().toFixed(7)
      reverseGeocode(e.latLng.lat(), e.latLng.lng())
    })

    // Clique no mapa move o marcador
    mapInstance.addListener('click', (e) => {
      markerInstance.setPosition(e.latLng)
      markerInstance.setVisible(true)
      form.latitude  = e.latLng.lat().toFixed(7)
      form.longitude = e.latLng.lng().toFixed(7)
      reverseGeocode(e.latLng.lat(), e.latLng.lng())
    })

    // Autocomplete no campo de endereço
    autocomplete = new window.google.maps.places.Autocomplete(addressInput.value, {
      componentRestrictions: { country: 'mz' },
      fields: ['formatted_address', 'geometry'],
    })
    autocomplete.addListener('place_changed', () => {
      const place = autocomplete.getPlace()
      if (!place.geometry) return
      form.address   = place.formatted_address
      form.latitude  = place.geometry.location.lat().toFixed(7)
      form.longitude = place.geometry.location.lng().toFixed(7)
      moveMapTo(parseFloat(form.latitude), parseFloat(form.longitude), 17)
    })
  })
}

function moveMapTo(lat, lng, zoom = 16) {
  if (!mapInstance || !markerInstance) return
  const pos = new window.google.maps.LatLng(lat, lng)
  mapInstance.setCenter(pos)
  mapInstance.setZoom(zoom)
  markerInstance.setPosition(pos)
  markerInstance.setVisible(true)
}

async function reverseGeocode(lat, lng) {
  try {
    const geocoder = new window.google.maps.Geocoder()
    geocoder.geocode({ location: { lat, lng } }, (results, status) => {
      if (status === 'OK' && results[0]) {
        form.address = results[0].formatted_address
      }
    })
  } catch {}
}

// ─── GPS ─────────────────────────────────────────────────────
function detectGPS() {
  if (!navigator.geolocation) { gpsError.value = 'GPS não disponível.'; return }
  gpsLoading.value = true
  gpsError.value   = ''
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const lat = pos.coords.latitude
      const lng = pos.coords.longitude
      form.latitude  = lat.toFixed(7)
      form.longitude = lng.toFixed(7)
      gpsLoading.value = false
      if (mapsReady.value) {
        moveMapTo(lat, lng, 17)
        reverseGeocode(lat, lng)
      }
    },
    (err) => {
      gpsLoading.value = false
      if (err.code === 1) gpsError.value = 'Permissão de localização negada.'
      else gpsError.value = 'Não foi possível obter a localização.'
    },
    { timeout: 10000 }
  )
}

function clearLocation() {
  form.latitude  = null
  form.longitude = null
  form.address   = ''
  if (markerInstance) markerInstance.setVisible(false)
}

function onAddressInput() {
  // Se limpou o campo manualmente, limpa as coordenadas
  if (!form.address) {
    form.latitude  = null
    form.longitude = null
    if (markerInstance) markerInstance.setVisible(false)
  }
}


// ─── Upload de imagens ────────────────────────────────────────
function onLogoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  logoFile.value    = file
  logoPreview.value = URL.createObjectURL(file)
}

function onBannerChange(e) {
  const file = e.target.files[0]
  if (!file) return
  bannerFile.value    = file
  bannerPreview.value = URL.createObjectURL(file)
}

// ─── Guardar ──────────────────────────────────────────────────
async function save() {
  saving.value = true
  error.value  = ''
  try {
    const fd = new FormData()
    fd.append('name',        form.name        ?? '')
    fd.append('description', form.description ?? '')
    fd.append('phone',       form.phone       ?? '')
    fd.append('whatsapp',    form.whatsapp    ?? '')
    fd.append('address',     form.address     ?? '')
    if (form.latitude)  fd.append('latitude',  form.latitude)
    if (form.longitude) fd.append('longitude', form.longitude)
    if (logoFile.value)   fd.append('logo',   logoFile.value)
    if (bannerFile.value) fd.append('banner', bannerFile.value)

    await axios.post('/store/update', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    saved.value = true
    setTimeout(() => saved.value = false, 3000)
    logoFile.value   = null
    bannerFile.value = null
  } catch (e) {
    const errs = e.response?.data?.errors
    if (errs) error.value = Object.values(errs).flat().join(' ')
    else error.value = e.response?.data?.message || 'Erro ao guardar.'
  } finally {
    saving.value = false
  }
}

// ─── Inicialização ────────────────────────────────────────────
onMounted(async () => {
  try {
    const { data } = await axios.get('/store')
    storeSlug.value    = data.slug       ?? ''
    currentLogo.value  = data.logo       ?? ''
    currentBanner.value = data.banner    ?? ''
    Object.assign(form, {
      name:        data.name        ?? '',
      description: data.description ?? '',
      phone:       data.phone       ?? '',
      whatsapp:    data.whatsapp    ?? '',
      address:     data.address     ?? '',
      latitude:    data.latitude    ?? null,
      longitude:   data.longitude   ?? null,
    })
  } finally {
    loading.value = false
    loadGoogleMaps()
  }
})

onUnmounted(() => {
  if (mapsScriptEl) mapsScriptEl.remove()
  delete window.__mapsCallback
})
</script>
