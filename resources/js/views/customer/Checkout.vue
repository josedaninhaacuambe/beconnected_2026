<template>
  <div class="max-w-2xl mx-auto px-4 py-8 pb-mobile">
    <div class="flex items-center gap-3 mb-6">
      <RouterLink to="/conta/carrinho" class="text-bc-muted hover:text-bc-gold text-sm">← Carrinho</RouterLink>
      <span class="text-bc-muted">/</span>
      <h1 class="text-xl font-bold text-bc-light">Checkout</h1>
    </div>

    <form @submit.prevent="placeOrder" class="space-y-5">

      <!-- ══════════════════════════════════════════
           1. ENDEREÇO DE ENTREGA
      ══════════════════════════════════════════ -->
      <div class="card-african p-5">
        <h2 class="text-bc-gold font-semibold mb-4 flex items-center gap-2">📍 Endereço de Entrega</h2>

        <!-- Opção: localização actual vs outro endereço -->
        <div class="flex gap-2 mb-4">
          <button
            type="button"
            @click="addressMode = 'auto'"
            :class="['flex-1 py-2 rounded-xl text-sm font-medium transition border', addressMode === 'auto' ? 'border-bc-gold bg-bc-gold/10 text-bc-gold' : 'border-bc-gold/20 text-bc-muted hover:border-bc-gold/40']"
          >📍 Minha localização</button>
          <button
            type="button"
            @click="addressMode = 'manual'"
            :class="['flex-1 py-2 rounded-xl text-sm font-medium transition border', addressMode === 'manual' ? 'border-bc-gold bg-bc-gold/10 text-bc-gold' : 'border-bc-gold/20 text-bc-muted hover:border-bc-gold/40']"
          >✏️ Outro endereço</button>
        </div>

        <!-- Auto: GPS -->
        <div v-if="addressMode === 'auto'" class="space-y-3">
          <div
            v-if="!gpsLocation"
            class="border-2 border-dashed border-bc-gold/30 rounded-xl p-5 text-center"
          >
            <p class="text-3xl mb-2">📡</p>
            <p class="text-bc-muted text-sm mb-3">Detecção automática da tua localização actual</p>
            <button
              type="button"
              @click="detectLocation"
              :disabled="gpsLoading"
              class="btn-gold px-5 py-2 text-sm"
            >
              <span v-if="gpsLoading" class="flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                A detectar...
              </span>
              <span v-else>📍 Detectar localização</span>
            </button>
            <p v-if="gpsError" class="text-red-400 text-xs mt-2">{{ gpsError }}</p>
          </div>

          <div v-else class="space-y-2">
            <div class="bg-bc-surface-2 rounded-xl p-3 flex items-center gap-3">
              <span class="text-xl">📍</span>
              <p class="text-bc-light text-sm font-medium flex-1 truncate">{{ gpsLocation.address }}</p>
              <button type="button" @click="gpsLocation = null" class="text-bc-muted hover:text-bc-gold text-xs flex-shrink-0">Alterar</button>
            </div>
            <!-- Mapa satélite com pin da localização -->
            <div class="rounded-xl overflow-hidden h-64 bg-bc-surface-2">
              <div ref="mapEl" class="w-full h-full"></div>
              <div v-if="!mapsLoaded" class="w-full h-full flex flex-col items-center justify-center gap-2 text-bc-muted text-xs -mt-64">
                <span class="text-3xl">🗺</span>
                Mapa a carregar...
              </div>
            </div>
          </div>

          <!-- Nota manual complementar -->
          <input
            v-if="gpsLocation"
            v-model="form.delivery_address"
            type="text"
            placeholder="Complemento: bloco, andar, referência..."
            class="input-african"
          />
        </div>

        <!-- Manual: Google Maps Autocomplete -->
        <div v-if="addressMode === 'manual'" class="space-y-3">
          <div class="relative">
            <input
              ref="addressInputEl"
              v-model="form.delivery_address"
              type="text"
              placeholder="Pesquisar endereço no Google Maps..."
              class="input-african pr-10"
              required
              @focus="initAutocomplete"
            />
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-bc-muted text-lg">🔍</span>
          </div>
          <p v-if="!mapsKeyAvailable" class="text-bc-muted text-xs bg-bc-surface-2 rounded-lg p-2">
            ⚠ Pesquisa automática requer chave Google Maps. Escreve o endereço manualmente.
          </p>

          <!-- Mini mapa com localização manual -->
          <div v-if="form.delivery_latitude && mapsLoaded" class="rounded-xl overflow-hidden h-64 bg-bc-surface-2">
            <div ref="mapEl" class="w-full h-full"></div>
          </div>
        </div>

        <!-- Localização administrativa — oculta quando GPS activo -->
        <div v-if="addressMode === 'manual' || !gpsLocation" class="grid grid-cols-2 gap-3 mt-4">
          <div>
            <label class="text-bc-muted text-xs mb-1 block">Província *</label>
            <select v-model="form.province_id" @change="loadCities" class="select-african">
              <option value="">Seleccionar</option>
              <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-bc-muted text-xs mb-1 block">Cidade *</label>
            <select v-model="form.city_id" @change="onCityChange" class="select-african" :disabled="!form.province_id">
              <option value="">Seleccionar</option>
              <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>

        <textarea
          v-model="form.notes"
          placeholder="Notas para o estafeta (ponto de referência, instruções de entrega...)"
          class="input-african resize-none mt-3"
          rows="2"
        ></textarea>
      </div>

      <!-- ══════════════════════════════════════════
           2. PESO E ESTIMATIVA DE ENTREGA
      ══════════════════════════════════════════ -->
      <div class="card-african p-5">
        <h2 class="text-bc-gold font-semibold mb-4 flex items-center gap-2">🚚 Entrega</h2>

        <div class="flex items-center gap-3 mb-4">
          <div class="flex-1">
            <label class="text-bc-muted text-xs mb-1 block">Peso total estimado (kg)</label>
            <div class="flex items-center gap-2">
              <input
                v-model="form.weight_kg"
                type="number"
                min="0.1"
                step="0.5"
                placeholder="1.0"
                class="input-african flex-1"
                @change="fetchEstimate"
              />
              <span class="text-bc-muted text-xs">kg</span>
            </div>
          </div>
          <button
            type="button"
            @click="fetchEstimate"
            :disabled="!form.city_id || estimateLoading"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-bc-gold/40 text-bc-gold text-sm hover:bg-bc-gold/10 transition disabled:opacity-40 mt-5"
          >
            <svg v-if="estimateLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span v-else>🔄</span>
            Calcular
          </button>
        </div>

        <!-- Resultado da estimativa -->
        <div v-if="estimate" class="bg-bc-surface-2 rounded-xl p-4 space-y-3">
          <!-- Taxa + tempo -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-2xl">💰</span>
              <div>
                <p class="text-bc-light font-bold text-xl">{{ formatMZN(estimate.fee) }}</p>
                <p class="text-bc-muted text-xs">Taxa de entrega</p>
              </div>
            </div>
            <div class="text-right">
              <div :class="['text-sm font-semibold', estimate.available_drivers > 0 ? 'text-green-400' : 'text-red-400']">
                {{ estimate.available_drivers > 0 ? '✓ ' + estimate.available_drivers + ' estafeta(s) disponível(is)' : '⚠ Sem estafetas próximos' }}
              </div>
              <p v-if="estimate.distance_km" class="text-bc-muted text-xs">📏 ~{{ estimate.distance_km }} km</p>
              <p class="text-bc-muted text-xs">⏱ ~{{ estimate.estimated_minutes }} minutos</p>
            </div>
          </div>

          <!-- Lista de estafetas próximos -->
          <div v-if="nearbyDrivers.length > 0">
            <p class="text-bc-muted text-xs font-semibold uppercase tracking-wide mb-2">📍 Estafetas num raio de 1km — GPS em tempo real</p>
            <div class="space-y-2">
              <div
                v-for="driver in nearbyDrivers"
                :key="driver.id"
                class="bg-bc-dark/50 rounded-lg px-3 py-2 border border-bc-gold/10"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="text-lg">{{ driver.vehicle_type === 'moto' ? '🏍️' : driver.vehicle_type === 'carro' ? '🚗' : '🚲' }}</span>
                    <div>
                      <p class="text-bc-light text-sm font-medium">{{ driver.name }}</p>
                      <p class="text-bc-muted text-xs">~{{ driver.distance_km }} km · {{ driver.vehicle_type }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <a
                      v-if="driver.latitude && driver.longitude"
                      :href="`https://www.google.com/maps?q=${driver.latitude},${driver.longitude}`"
                      target="_blank"
                      class="text-green-400 text-xs border border-green-500/30 rounded-lg px-2 py-1 hover:bg-green-900/20 transition"
                    >📡 GPS</a>
                    <a v-if="driver.phone" :href="`tel:${driver.phone}`" class="text-bc-gold text-xs border border-bc-gold/30 rounded-lg px-2 py-1 hover:bg-bc-gold/10 transition">
                      📞 {{ driver.phone }}
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Contactos: cliente e estafeta -->
          <div class="border-t border-bc-gold/10 pt-3 grid grid-cols-2 gap-3">
            <div>
              <p class="text-bc-muted text-xs mb-1">📱 O teu contacto (cliente)</p>
              <p class="text-bc-light text-sm font-medium">{{ authStore.user?.phone || authStore.user?.email }}</p>
            </div>
            <div v-if="nearbyDrivers.length > 0">
              <p class="text-bc-muted text-xs mb-1">🏍️ Estafeta atribuido apos confirmacao</p>
              <p class="text-bc-muted text-xs">Contacto visivel no detalhe do pedido</p>
            </div>
          </div>

          <!-- Info GPS e confirmação -->
          <div class="space-y-2">
            <div class="flex items-center gap-2 bg-green-900/20 border border-green-500/20 rounded-lg px-3 py-2">
              <span class="text-green-400 text-sm">📡</span>
              <p class="text-green-400 text-xs">Acompanhamento GPS em tempo real disponivel apos confirmacao do pedido — link enviado para o teu contacto</p>
            </div>
            <div class="flex items-center gap-2 bg-blue-900/20 border border-blue-500/20 rounded-lg px-3 py-2">
              <span class="text-blue-400 text-sm">⭐</span>
              <p class="text-blue-400 text-xs">Apos a entrega podes confirmar o recebimento e avaliar o estafeta para ganhar estrelas de fidelidade</p>
            </div>
          </div>

          <p v-if="estimate.available_drivers === 0" class="text-orange-400 text-xs bg-orange-900/20 rounded-lg px-3 py-2">
            Sem estafetas disponíveis no momento. Podes confirmar o pedido e será atribuído quando um estafeta ficar disponível.
          </p>
        </div>

        <p v-else-if="!form.city_id" class="text-bc-muted text-xs text-center py-2">
          Selecciona a cidade para ver a estimativa de entrega.
        </p>
      </div>

      <!-- ══════════════════════════════════════════
           3. RESUMO COMPACTO + PAGAMENTO
      ══════════════════════════════════════════ -->
      <div class="card-african p-5">
        <h2 class="text-bc-gold font-semibold mb-4 flex items-center gap-2">🧾 Resumo &amp; Pagamento</h2>

        <!-- Lojas e totais compactos -->
        <div class="space-y-1.5 text-sm mb-4">
          <div v-for="group in cartStore.itemsByStore" :key="group.store.id"
            class="flex justify-between text-bc-muted">
            <span class="flex items-center gap-1">🏪 {{ group.store.name }} <span class="text-xs">({{ storeTotalQty(group) }} item{{ storeTotalQty(group) !== 1 ? 's' : '' }})</span></span>
            <span>{{ formatMZN(group.store_subtotal) }}</span>
          </div>
          <div class="flex justify-between text-bc-muted pt-1 border-t border-bc-gold/10">
            <span>Entrega</span>
            <span :class="estimate ? 'text-bc-light' : 'italic text-xs'">
              {{ estimate ? formatMZN(estimate.fee) : 'A calcular acima' }}
            </span>
          </div>
          <div class="flex justify-between font-bold text-base border-t border-bc-gold/20 pt-2">
            <span class="text-bc-light">Total a pagar</span>
            <span class="text-bc-gold">{{ formatMZN(cartStore.subtotal + (estimate?.fee ?? 0)) }}</span>
          </div>
        </div>

        <!-- Método de pagamento -->
        <p class="text-bc-muted text-xs mb-2 font-medium">💳 Método de pagamento</p>
        <div class="flex gap-2 mb-3 flex-wrap">
          <button
            v-for="method in paymentMethods" :key="method.value"
            type="button"
            @click="form.payment_method = method.value"
            :class="[
              'flex-1 min-w-[90px] py-2.5 px-2 rounded-xl text-xs font-bold border transition text-center',
              form.payment_method === method.value
                ? 'bg-bc-gold text-bc-dark border-bc-gold'
                : 'border-bc-gold/30 text-bc-muted hover:border-bc-gold hover:text-bc-light'
            ]"
          >
            <div class="text-base mb-0.5">{{ method.icon }}</div>
            <div>{{ method.label }}</div>
          </button>
        </div>

        <div v-if="form.payment_method === 'emola' || form.payment_method === 'mpesa'">
          <label class="text-bc-muted text-xs mb-1 block">
            Número {{ form.payment_method === 'emola' ? 'eMola (Movitel)' : 'M-Pesa (Vodacom)' }} *
          </label>
          <input
            v-model="form.payment_phone"
            type="tel"
            placeholder="84/86 XXX XXXX ou 87/82 XXX XXXX"
            class="input-african"
            required
          />
        </div>
      </div>

      <!-- Erros -->
      <p v-if="error" class="text-red-400 text-sm bg-red-900/20 rounded-xl p-3">⚠ {{ error }}</p>

      <!-- Botão confirmar -->
      <button
        type="submit"
        :disabled="submitting || !canSubmit"
        class="btn-green w-full py-4 text-base font-semibold disabled:opacity-50"
      >
        <span v-if="submitting" class="flex items-center justify-center gap-2">
          <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          A processar pedido...
        </span>
        <span v-else>✅ Confirmar Pedido · {{ formatMZN(cartStore.subtotal + (estimate?.fee ?? 0)) }}</span>
      </button>

      <p class="text-bc-muted text-xs text-center">
        Ao confirmar, concordas com os nossos termos. Os estafetas serão notificados automaticamente.
      </p>
    </form>

    <!-- ══════════════════════════════════════════
         MODAL DE RECIBO
    ══════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showReceipt" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
        <div class="bg-bc-surface border border-bc-gold/30 rounded-2xl shadow-2xl w-full max-w-md">
          <!-- Cabeçalho -->
          <div class="p-6 text-center border-b border-bc-gold/20">
            <div class="text-5xl mb-2">✅</div>
            <h2 class="text-bc-gold font-bold text-xl mb-1">Pedido Confirmado!</h2>
            <p class="text-bc-muted text-sm">
              Pedido <span class="text-bc-light font-semibold">#{{ orderResult?.order_number }}</span> registado com sucesso.
            </p>
          </div>

          <!-- Info resumo -->
          <div class="px-6 py-4 space-y-2 text-sm">
            <div class="flex justify-between text-bc-muted">
              <span>Endereço</span>
              <span class="text-bc-light text-right max-w-[60%] truncate">{{ orderResult?.delivery_address }}</span>
            </div>
            <div class="flex justify-between text-bc-muted">
              <span>Pagamento</span>
              <span class="text-bc-light uppercase">{{ orderResult?.payment_method }}</span>
            </div>
            <div class="flex justify-between font-semibold">
              <span class="text-bc-muted">Total</span>
              <span class="text-bc-gold">{{ formatMZN(orderResult?.total) }}</span>
            </div>
          </div>

          <!-- Botões -->
          <div class="px-6 pb-6 space-y-3">
            <button
              @click="printReceipt"
              class="btn-gold w-full py-3 text-sm font-semibold flex items-center justify-center gap-2"
            >🖨 Imprimir / Guardar PDF</button>
            <button
              @click="router.push({ name: 'order-detail', params: { numero: orderResult?.order_number } })"
              class="w-full py-3 text-sm border border-bc-gold/30 rounded-xl text-bc-light hover:border-bc-gold transition"
            >📦 Ver Detalhes do Pedido</button>
            <button
              @click="router.push({ name: 'home' })"
              class="w-full py-2 text-xs text-bc-muted hover:text-bc-light transition"
            >Continuar a comprar</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Área de impressão (oculta no ecrã, visível só ao imprimir) -->
    <div id="receipt-print" class="hidden print:block print:p-8 print:text-black print:bg-white">
      <div style="max-width:600px;margin:0 auto;font-family:sans-serif">
        <div style="text-align:center;border-bottom:2px solid #d4a843;padding-bottom:16px;margin-bottom:16px">
          <h1 style="font-size:24px;font-weight:bold;color:#1a1a2e">BECONNECT</h1>
          <p style="color:#666;font-size:12px">Mercado Virtual de Moçambique</p>
          <h2 style="font-size:18px;margin-top:8px">RECIBO DE PEDIDO</h2>
          <p style="font-size:14px;font-weight:bold">#{{ orderResult?.order_number }}</p>
        </div>
        <table style="width:100%;font-size:13px;border-collapse:collapse">
          <tr>
            <td style="padding:4px 0;color:#666">Data:</td>
            <td style="padding:4px 0;text-align:right">{{ new Date().toLocaleDateString('pt-MZ') }}</td>
          </tr>
          <tr>
            <td style="padding:4px 0;color:#666">Método de pagamento:</td>
            <td style="padding:4px 0;text-align:right;text-transform:uppercase">{{ orderResult?.payment_method }}</td>
          </tr>
          <tr>
            <td style="padding:4px 0;color:#666">Endereço de entrega:</td>
            <td style="padding:4px 0;text-align:right">{{ orderResult?.delivery_address }}</td>
          </tr>
        </table>
        <table style="width:100%;font-size:13px;border-collapse:collapse;margin-top:16px">
          <thead>
            <tr style="border-bottom:1px solid #ccc">
              <th style="text-align:left;padding:6px 0">Produto</th>
              <th style="text-align:center;padding:6px 0">Qtd</th>
              <th style="text-align:right;padding:6px 0">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in orderResult?.items_by_store ?? []" :key="group.store_id">
              <tr><td colspan="3" style="font-weight:bold;padding-top:8px;color:#1a1a2e">🏪 {{ group.store_name }}</td></tr>
              <tr v-for="item in group.items" :key="item.id">
                <td style="padding:3px 0;color:#444">{{ item.product_name }}</td>
                <td style="padding:3px 0;text-align:center;color:#444">{{ item.quantity }}</td>
                <td style="padding:3px 0;text-align:right;color:#444">{{ formatMZN(item.subtotal) }}</td>
              </tr>
            </template>
          </tbody>
        </table>
        <div style="border-top:2px solid #1a1a2e;margin-top:16px;padding-top:12px">
          <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:bold">
            <span>Total Pago</span>
            <span>{{ formatMZN(orderResult?.total) }}</span>
          </div>
        </div>
        <p style="text-align:center;color:#999;font-size:11px;margin-top:24px">
          Obrigado pela tua compra! beconnect.co.mz
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useCartStore } from '../../stores/cart.js'
import { useAuthStore } from '../../stores/auth.js'

const router = useRouter()
const cartStore = useCartStore()
const authStore = useAuthStore()

const COMMISSION_PER_ITEM = 0.50

// ─── State ────────────────────────────────────────────────
const submitting     = ref(false)
const error          = ref('')
const showReceipt    = ref(false)
const orderResult    = ref(null)
const provinces   = ref([])
const cities      = ref([])
const estimate    = ref(null)
const estimateLoading = ref(false)
const nearbyDrivers = ref([])

const addressMode = ref('manual') // 'auto' | 'manual' — manual por defeito (GPS requer Google Maps API)
const gpsLoading  = ref(false)
const gpsError    = ref('')
const gpsLocation = ref(null)  // { lat, lng, address }
const mapsLoaded  = ref(false)
const mapsKeyAvailable = computed(() => !!import.meta.env.VITE_GOOGLE_MAPS_KEY)

const mapEl          = ref(null)
const addressInputEl = ref(null)
let   mapInstance    = null
let   markerInstance = null
let   acInstance     = null

const form = reactive({
  province_id:        '',
  city_id:            '',
  delivery_address:   '',
  delivery_latitude:  null,
  delivery_longitude: null,
  notes:              '',
  weight_kg:          1,
  payment_method:     'mpesa',
  payment_phone:      '',
})

const paymentMethods = [
  { value: 'mpesa',            label: 'M-Pesa',      icon: '📱' },
  { value: 'emola',            label: 'eMola',       icon: '📲' },
  { value: 'cash_on_delivery', label: 'Na Entrega',  icon: '💵' },
]

const canSubmit = computed(() => {
  const hasAddress = form.delivery_address || gpsLocation.value
  const hasLocation = (addressMode.value === 'auto' && gpsLocation.value)
    ? true
    : (form.province_id && form.city_id)
  const hasPayment = form.payment_method === 'cash_on_delivery' || form.payment_phone
  return hasAddress && hasLocation && hasPayment
})

// ─── Helpers ──────────────────────────────────────────────
function storeTotalQty(group) {
  return group.items.reduce((sum, i) => sum + i.quantity, 0)
}

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

// ─── Location ─────────────────────────────────────────────
async function detectLocation() {
  if (!navigator.geolocation) {
    gpsError.value = 'Geolocalização não suportada neste dispositivo.'
    return
  }
  gpsLoading.value = true
  gpsError.value = ''
  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      const lat = pos.coords.latitude
      const lng = pos.coords.longitude
      let address = `${lat.toFixed(5)}, ${lng.toFixed(5)}`

      // Reverse geocoding via Google Maps (se disponível)
      if (mapsKeyAvailable.value) {
        try {
          await loadGoogleMaps()
          const geocoder = new window.google.maps.Geocoder()
          const result = await new Promise((res, rej) =>
            geocoder.geocode({ location: { lat, lng } }, (r, s) =>
              s === 'OK' ? res(r) : rej(s)
            )
          )
          if (result[0]) address = result[0].formatted_address
        } catch {}
      }

      gpsLocation.value = { lat, lng, address }
      form.delivery_latitude  = lat
      form.delivery_longitude = lng
      if (!form.delivery_address) form.delivery_address = address
      gpsLoading.value = false

      await loadGoogleMaps().catch(() => {})
      await nextTick()
      initMap(lat, lng)
      if (form.city_id) fetchEstimate()
    },
    (err) => {
      gpsLoading.value = false
      gpsError.value = err.code === 1
        ? 'Permissão de localização negada. Activa nas definições do browser.'
        : 'Não foi possível obter a localização. Verifica a ligação.'
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

// ─── Google Maps ──────────────────────────────────────────
function loadGoogleMaps() {
  if (window.google?.maps) {
    mapsLoaded.value = true
    return Promise.resolve()
  }
  if (!mapsKeyAvailable.value) return Promise.reject('no key')
  return new Promise((resolve, reject) => {
    const existing = document.querySelector('script[data-gmaps]')
    if (existing) { existing.addEventListener('load', resolve); return }
    const s = document.createElement('script')
    s.setAttribute('data-gmaps', '1')
    s.src = `https://maps.googleapis.com/maps/api/js?key=${import.meta.env.VITE_GOOGLE_MAPS_KEY}&libraries=places`
    s.async = true
    s.onload = () => { mapsLoaded.value = true; resolve() }
    s.onerror = reject
    document.head.appendChild(s)
  })
}

function initMap(lat, lng) {
  if (!mapEl.value || !window.google?.maps) return
  if (!mapInstance) {
    mapInstance = new window.google.maps.Map(mapEl.value, {
      center: { lat, lng }, zoom: 17,
      mapTypeId: 'hybrid',
      disableDefaultUI: true,
      zoomControl: true,
      mapTypeControl: false,
    })
  } else {
    mapInstance.setCenter({ lat, lng })
  }
  if (markerInstance) markerInstance.setMap(null)
  markerInstance = new window.google.maps.Marker({
    position: { lat, lng }, map: mapInstance,
    title: 'Entrega aqui',
    icon: { url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png' },
  })
  // Arrastar marcador para ajustar endereço
  markerInstance.addListener('dragend', (e) => {
    form.delivery_latitude  = e.latLng.lat()
    form.delivery_longitude = e.latLng.lng()
    if (gpsLocation.value) {
      gpsLocation.value.lat = form.delivery_latitude
      gpsLocation.value.lng = form.delivery_longitude
    }
    fetchEstimate()
  })
  markerInstance.setDraggable(true)
}

async function initAutocomplete() {
  if (!mapsKeyAvailable.value || !addressInputEl.value) return
  if (acInstance) return
  try {
    await loadGoogleMaps()
    acInstance = new window.google.maps.places.Autocomplete(addressInputEl.value, {
      types: ['geocode'],
      componentRestrictions: { country: 'mz' },
      fields: ['formatted_address', 'geometry'],
    })
    acInstance.addListener('place_changed', async () => {
      const place = acInstance.getPlace()
      if (!place.geometry?.location) return
      form.delivery_address   = place.formatted_address
      form.delivery_latitude  = place.geometry.location.lat()
      form.delivery_longitude = place.geometry.location.lng()
      await nextTick()
      initMap(form.delivery_latitude, form.delivery_longitude)
      if (form.city_id) fetchEstimate()
    })
  } catch {}
}

// ─── Estimate ─────────────────────────────────────────────
async function fetchEstimate() {
  if (!form.city_id) return
  estimateLoading.value = true
  try {
    const { data } = await axios.post('/delivery/estimate', {
      city_id:   form.city_id,
      latitude:  form.delivery_latitude,
      longitude: form.delivery_longitude,
      weight_kg: form.weight_kg || 1,
    })
    estimate.value = data
    if (form.delivery_latitude && form.delivery_longitude) {
      fetchNearbyDrivers()
    }
  } catch {
    // silent
  } finally {
    estimateLoading.value = false
  }
}

async function fetchNearbyDrivers() {
  if (!form.delivery_latitude || !form.delivery_longitude) return
  try {
    const { data } = await axios.post('/delivery/nearby-drivers', {
      latitude:  form.delivery_latitude,
      longitude: form.delivery_longitude,
      radius_km: 1,
    })
    nearbyDrivers.value = data
  } catch {
    nearbyDrivers.value = []
  }
}

// ─── Cities ───────────────────────────────────────────────
async function loadCities() {
  form.city_id = ''
  estimate.value = null
  if (!form.province_id) return
  const { data } = await axios.get('/locations/cities', { params: { province_id: form.province_id } })
  cities.value = data
}

function onCityChange() {
  if (form.city_id) fetchEstimate()
}

// ─── Submit ───────────────────────────────────────────────
async function placeOrder() {
  if (!canSubmit.value) return
  submitting.value = true
  error.value = ''
  try {
    const payload = {
      payment_method:     form.payment_method,
      payment_phone:      form.payment_phone || undefined,
      delivery_address:   form.delivery_address || (gpsLocation.value?.address ?? ''),
      province_id:        form.province_id,
      city_id:            form.city_id,
      delivery_latitude:  form.delivery_latitude ?? gpsLocation.value?.lat,
      delivery_longitude: form.delivery_longitude ?? gpsLocation.value?.lng,
      notes:              form.notes || undefined,
    }

    // Checkout é assíncrono (queue). Guardamos a idempotency key e fazemos polling.
    const idempotencyKey = crypto.randomUUID()
    const { data } = await axios.post('/orders/checkout', payload, {
      headers: { 'Idempotency-Key': idempotencyKey },
    })

    // Polling até o job completar (máx. 30s)
    const order = await pollCheckout(idempotencyKey)
    await cartStore.clearCart()
    orderResult.value = order
    showReceipt.value = true
  } catch (e) {
    error.value = e.response?.data?.errors
      ? Object.values(e.response.data.errors).flat().join(' ')
      : e.response?.data?.message || 'Erro ao processar pedido. Tenta novamente.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    submitting.value = false
  }
}

// Polling ao endpoint de status do checkout até 'succeeded' ou timeout (30s)
async function pollCheckout(idempotencyKey, attempts = 15) {
  for (let i = 0; i < attempts; i++) {
    await new Promise(r => setTimeout(r, 2000))
    try {
      const { data } = await axios.get('/orders/checkout-status', {
        params: { idempotency_key: idempotencyKey },
      })
      if (data.status === 'succeeded' && data.order) return data.order
      if (data.status === 'failed') throw new Error(data.error || 'Falha ao processar pedido.')
    } catch (e) {
      if (e.response?.status !== 404) throw e
    }
  }
  throw new Error('O pedido está a demorar mais do que o esperado. Verifica os teus pedidos em breve.')
}

// ─── Receipt ──────────────────────────────────────────────
function printReceipt() {
  const el = document.getElementById('receipt-print')
  if (!el) return
  el.classList.remove('hidden')
  window.print()
  el.classList.add('hidden')
}

// ─── Init ─────────────────────────────────────────────────
watch(addressMode, () => {
  gpsLocation.value = null
  form.delivery_latitude  = null
  form.delivery_longitude = null
  mapInstance = null
  markerInstance = null
  acInstance = null
})

onMounted(async () => {
  const [provRes] = await Promise.all([
    axios.get('/locations/provinces'),
    cartStore.fetchCart(),
  ])
  provinces.value = provRes.data

  // Pré-seleccionar método de pagamento passado pelo Cart.vue via router state
  const stateMethod = window.history.state?.paymentMethod
  if (stateMethod) form.payment_method = stateMethod

  // Pré-carregar Google Maps se chave disponível
  if (mapsKeyAvailable.value) loadGoogleMaps().catch(() => {})
})
</script>

<style>
@media print {
  body > *:not(#receipt-print) { display: none !important; }
  #receipt-print { display: block !important; }
}
</style>
