<template>
  <!-- Botão flutuante -->
  <button
    @click="open = true"
    class="fixed bottom-20 right-4 z-40 w-12 h-12 rounded-full shadow-lg flex items-center justify-center transition hover:scale-110 active:scale-95"
    style="background-color:#1C2B3C; border: 2px solid #F07820;"
    title="Reclamações e Sugestões"
  >
    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
    </svg>
  </button>

  <!-- Modal -->
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
        <!-- Cabeçalho -->
        <div class="flex items-center justify-between px-5 py-4 rounded-t-2xl" style="background-color:#1C2B3C;">
          <div>
            <h3 class="text-white font-bold text-base">Reclamações &amp; Sugestões</h3>
            <p class="text-white/60 text-xs">A tua opinião melhora a plataforma</p>
          </div>
          <button @click="close" class="text-white/60 hover:text-white transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Formulário -->
        <form @submit.prevent="submit" class="p-5 space-y-3">
          <!-- Tipo -->
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="t in types" :key="t.value"
              type="button"
              @click="form.type = t.value"
              class="py-2 px-3 rounded-xl text-xs font-semibold border-2 transition"
              :class="form.type === t.value
                ? 'border-bc-gold bg-bc-gold/10 text-bc-gold'
                : 'border-gray-200 text-gray-500 hover:border-bc-gold/40'"
            >
              {{ t.emoji }} {{ t.label }}
            </button>
          </div>

          <!-- Assunto -->
          <input
            v-model="form.subject"
            type="text"
            placeholder="Assunto (ex: Produto errado entregue)"
            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-bc-gold"
            required
          />

          <!-- Mensagem -->
          <textarea
            v-model="form.message"
            rows="4"
            placeholder="Descreve a tua reclamação ou sugestão em detalhe..."
            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-bc-gold resize-none"
            required
          ></textarea>

          <!-- Nome e Email (só se não autenticado) -->
          <template v-if="!isAuth">
            <input v-model="form.name" type="text" placeholder="O teu nome" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-bc-gold" />
            <input v-model="form.email" type="email" placeholder="O teu email (opcional)" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-bc-gold" />
          </template>

          <!-- Mensagem de sucesso -->
          <div v-if="sent" class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 text-sm text-center">
            ✅ Mensagem enviada! Iremos analisar e dar a devida atenção.
          </div>

          <!-- Erro -->
          <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-4 py-3 text-sm">
            {{ error }}
          </div>

          <button
            v-if="!sent"
            type="submit"
            :disabled="loading"
            class="w-full py-3 rounded-xl font-bold text-white text-sm transition hover:opacity-90 active:scale-95 disabled:opacity-50"
            style="background-color:#F07820;"
          >
            {{ loading ? 'A enviar...' : 'Enviar Mensagem' }}
          </button>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const isAuth = computed(() => !!auth.user)

const open = ref(false)
const loading = ref(false)
const sent = ref(false)
const error = ref('')

const form = ref({ type: 'sugestao', subject: '', message: '', name: '', email: '' })

const types = [
  { value: 'reclamacao', label: 'Reclamação', emoji: '😠' },
  { value: 'sugestao',   label: 'Sugestão',   emoji: '💡' },
  { value: 'elogio',     label: 'Elogio',     emoji: '👏' },
  { value: 'outro',      label: 'Outro',      emoji: '📝' },
]

function close() {
  open.value = false
  if (sent.value) {
    sent.value = false
    form.value = { type: 'sugestao', subject: '', message: '', name: '', email: '' }
  }
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await axios.post('/api/feedback', form.value)
    sent.value = true
    setTimeout(() => close(), 3000)
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erro ao enviar. Tenta novamente.'
  } finally {
    loading.value = false
  }
}
</script>
