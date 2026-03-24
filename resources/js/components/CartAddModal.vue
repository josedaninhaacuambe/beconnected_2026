<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="state.visible" class="fixed inset-0 z-[110] flex items-end sm:items-center justify-center" @click.self="close">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

        <div class="relative w-full sm:max-w-md bg-bc-dark border border-bc-gold/30 rounded-t-3xl sm:rounded-2xl shadow-2xl z-10 overflow-hidden">
          <!-- Handle bar (mobile) -->
          <div class="sm:hidden flex justify-center pt-3 pb-1">
            <div class="w-10 h-1 bg-bc-gold/30 rounded-full"></div>
          </div>

          <!-- Close -->
          <button @click="close" class="absolute top-4 right-4 text-bc-muted hover:text-bc-gold transition z-10">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>

          <div class="p-5">
            <!-- Product info -->
            <div class="flex gap-4 mb-5">
              <div class="w-20 h-20 rounded-xl overflow-hidden bg-bc-surface-2 flex-shrink-0 flex items-center justify-center">
                <img
                  v-if="productImage"
                  :src="productImage"
                  class="w-full h-full object-cover"
                  @error="imgError = true"
                />
                <span v-else class="text-3xl">📦</span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-bc-muted text-xs mb-0.5">{{ state.product?.store?.name }}</p>
                <h3 class="text-bc-light font-semibold leading-tight line-clamp-2 mb-1">{{ state.product?.name }}</h3>
                <p class="text-bc-gold font-bold text-lg">{{ formatMZN(state.product?.price) }}</p>
                <p v-if="stockQty > 0" class="text-green-400 text-xs">✓ {{ stockQty }} em stock</p>
                <p v-else class="text-red-400 text-xs">✗ Sem stock</p>
              </div>
            </div>

            <!-- Quantity selector -->
            <div class="mb-5">
              <p class="text-bc-muted text-xs mb-2">Quantidade</p>
              <div class="flex items-center justify-between bg-bc-surface rounded-xl p-3">
                <button
                  @click="qty = Math.max(1, qty - 1)"
                  class="w-10 h-10 rounded-full border border-bc-gold/40 text-bc-gold font-bold text-xl flex items-center justify-center hover:bg-bc-gold/10 transition"
                >−</button>
                <div class="text-center">
                  <span class="text-bc-light font-bold text-2xl tabular-nums">{{ qty }}</span>
                </div>
                <button
                  @click="qty = Math.min(stockQty, qty + 1)"
                  :disabled="qty >= stockQty"
                  class="w-10 h-10 rounded-full border border-bc-gold/40 text-bc-gold font-bold text-xl flex items-center justify-center hover:bg-bc-gold/10 transition disabled:opacity-40"
                >+</button>
              </div>
            </div>

            <!-- Total + CTA -->
            <div class="flex items-center justify-between mb-4">
              <div>
                <p class="text-bc-muted text-xs">Subtotal</p>
                <p class="text-bc-gold font-bold text-xl">{{ formatMZN((state.product?.price || 0) * qty) }}</p>
              </div>
              <button
                @click="addToCart"
                :disabled="!stockQty || adding"
                class="btn-gold px-6 py-3 text-base disabled:opacity-50 min-w-[160px]"
              >
                <span v-if="adding" class="flex items-center gap-2 justify-center">
                  <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                  </svg>
                  A adicionar...
                </span>
                <span v-else-if="added">✓ Adicionado!</span>
                <span v-else>🛒 Adicionar</span>
              </button>
            </div>

            <p v-if="errorMsg" class="text-red-400 text-xs text-center">{{ errorMsg }}</p>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useCartStore } from '../stores/cart.js'
import { useCartModal } from '../composables/useCartModal.js'

const cartStore = useCartStore()
const { state, close } = useCartModal()

const qty = ref(1)
const adding = ref(false)
const added = ref(false)
const errorMsg = ref('')
const imgError = ref(false)

const stockQty = computed(() => {
  const p = state.product
  return p?.stock?.quantity ?? p?.stock_quantity ?? 0
})

const productImage = computed(() => {
  if (imgError.value) return null
  const img = state.product?.images?.[0]
  if (!img) return null
  return img.startsWith('http') ? img : `/storage/${img}`
})

watch(() => state.product, () => {
  qty.value = 1
  adding.value = false
  added.value = false
  errorMsg.value = ''
  imgError.value = false
})

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

async function addToCart() {
  adding.value = true
  errorMsg.value = ''
  try {
    await cartStore.addItem(state.product.id, qty.value)
    added.value = true
    setTimeout(() => {
      close()
      added.value = false
    }, 1200)
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Erro ao adicionar. Tenta novamente.'
  } finally {
    adding.value = false
  }
}
</script>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-active .relative { transition: transform 0.25s ease; }
.modal-fade-enter-from .relative { transform: translateY(40px); }
</style>
