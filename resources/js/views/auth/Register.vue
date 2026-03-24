<template>
  <div class="min-h-screen flex items-center justify-center px-4 bg-bc-dark py-8">
    <div class="w-full max-w-lg">

      <!-- Logo -->
      <div class="text-center mb-6">
        <div class="w-14 h-14 bg-bc-gold rounded-full flex items-center justify-center mx-auto mb-2">
          <span class="text-bc-dark font-bold text-xl">BC</span>
        </div>
        <h1 class="text-bc-gold font-bold text-xl">Criar Conta</h1>
        <p class="text-bc-muted text-sm">Mercado Virtual de Moçambique</p>
      </div>

      <!-- Passo 1: Escolha o tipo de conta -->
      <div v-if="!accountType" class="grid grid-cols-2 gap-4">
        <!-- Cliente -->
        <button
          @click="accountType = 'customer'"
          class="card-african p-6 text-center hover:border-bc-gold/60 transition group flex flex-col items-center gap-3"
        >
          <span class="text-5xl">🛍️</span>
          <div>
            <p class="text-bc-light font-bold text-lg">Sou Cliente</p>
            <p class="text-bc-muted text-xs mt-1">Comprar produtos de lojas em Moçambique</p>
          </div>
          <span class="text-bc-gold text-sm group-hover:underline">Criar conta →</span>
        </button>

        <!-- Loja -->
        <button
          @click="accountType = 'store'"
          class="card-african p-6 text-center hover:border-bc-gold/60 transition group flex flex-col items-center gap-3"
        >
          <span class="text-5xl">🏪</span>
          <div>
            <p class="text-bc-light font-bold text-lg">Tenho uma Loja</p>
            <p class="text-bc-muted text-xs mt-1">Vender produtos na plataforma Beconnect</p>
          </div>
          <span class="text-bc-gold text-sm group-hover:underline">Registar loja →</span>
        </button>
      </div>

      <!-- ─── FLUXO CLIENTE ─────────────────────────────────── -->
      <div v-else-if="accountType === 'customer'" class="card-african p-6">
        <button @click="accountType = null" class="text-bc-muted text-xs hover:text-bc-gold mb-4 flex items-center gap-1">
          ← Voltar
        </button>
        <h2 class="text-bc-light font-semibold text-lg mb-5 text-center">Criar conta de cliente</h2>

        <!-- Google (recomendado) -->
        <button
          @click="registerWithGoogle('customer')"
          :disabled="googleLoading"
          class="w-full flex items-center justify-center gap-3 bg-white text-gray-700 font-semibold py-3 px-4 rounded-xl hover:bg-gray-100 transition mb-4 border border-gray-200"
        >
          <GoogleIcon />
          {{ googleLoading ? 'A redirecionar...' : 'Continuar com Google (Recomendado)' }}
        </button>

        <div class="flex items-center gap-3 mb-4">
          <div class="flex-1 border-t border-bc-gold/20"></div>
          <span class="text-bc-muted text-xs">ou com email</span>
          <div class="flex-1 border-t border-bc-gold/20"></div>
        </div>

        <form @submit.prevent="submitRegister('customer')" class="space-y-3">
          <input v-model="form.name" type="text" placeholder="Nome completo" class="input-african" required />
          <input v-model="form.email" type="email" placeholder="Email" class="input-african" required />
          <PasswordField v-model="form.password" placeholder="Senha (mín. 8 caracteres)" />
          <PasswordField v-model="form.password_confirmation" placeholder="Confirmar senha" />
          <p v-if="error" class="text-red-400 text-sm text-center bg-red-900/20 rounded-lg p-2">{{ error }}</p>
          <button type="submit" :disabled="loading" class="btn-gold w-full py-3 text-sm">
            {{ loading ? 'A criar conta...' : 'Criar Conta' }}
          </button>
        </form>

        <p class="text-center text-bc-muted text-sm mt-4">
          Já tens conta? <RouterLink to="/login" class="text-bc-gold hover:underline">Entrar</RouterLink>
        </p>
      </div>

      <!-- ─── FLUXO LOJA ─────────────────────────────────────── -->
      <div v-else-if="accountType === 'store'" class="card-african p-6">
        <button @click="accountType = null" class="text-bc-muted text-xs hover:text-bc-gold mb-4 flex items-center gap-1">
          ← Voltar
        </button>
        <h2 class="text-bc-light font-semibold text-lg mb-1 text-center">Registar a minha loja</h2>
        <p class="text-bc-muted text-xs text-center mb-5">Após o registo, a tua loja será avaliada pela equipa Beconnect.</p>

        <!-- Escolha do método de login -->
        <div class="mb-5">
          <p class="text-bc-muted text-xs mb-2 text-center">Como preferes entrar na tua loja?</p>
          <div class="grid grid-cols-2 gap-2">
            <button
              @click="storeLoginMethod = 'email'"
              :class="[
                'py-2 px-3 rounded-xl text-sm font-medium border transition',
                storeLoginMethod === 'email'
                  ? 'bg-bc-gold text-bc-dark border-bc-gold'
                  : 'border-bc-gold/30 text-bc-muted hover:border-bc-gold hover:text-bc-light'
              ]"
            >
              ✉️ Utilizador e Senha
            </button>
            <button
              @click="storeLoginMethod = 'google'"
              :class="[
                'py-2 px-3 rounded-xl text-sm font-medium border transition',
                storeLoginMethod === 'google'
                  ? 'bg-bc-gold text-bc-dark border-bc-gold'
                  : 'border-bc-gold/30 text-bc-muted hover:border-bc-gold hover:text-bc-light'
              ]"
            >
              🔵 Usar Google
            </button>
          </div>
        </div>

        <!-- Método: Email/Senha -->
        <div v-if="storeLoginMethod === 'email'">
          <form @submit.prevent="submitRegister('store_owner')" class="space-y-3">
            <input v-model="form.name" type="text" placeholder="Teu nome completo" class="input-african" required />
            <input v-model="form.email" type="email" placeholder="Email" class="input-african" required />
            <PasswordField v-model="form.password" placeholder="Senha (mín. 8 caracteres)" />
            <PasswordField v-model="form.password_confirmation" placeholder="Confirmar senha" />
            <p v-if="error" class="text-red-400 text-sm text-center bg-red-900/20 rounded-lg p-2">{{ error }}</p>
            <button type="submit" :disabled="loading" class="btn-gold w-full py-3 text-sm">
              {{ loading ? 'A criar conta...' : 'Registar como Dono de Loja' }}
            </button>
          </form>
        </div>

        <!-- Método: Google -->
        <div v-else-if="storeLoginMethod === 'google'">
          <div class="bg-bc-gold/5 border border-bc-gold/20 rounded-xl p-3 mb-4 text-xs text-bc-muted">
            <p class="text-bc-light font-medium mb-1">✅ Mais seguro e rápido</p>
            <p>A tua conta Google será usada para aceder ao painel da loja. Sem senha para memorizar.</p>
          </div>
          <button
            @click="registerWithGoogle('store_owner')"
            :disabled="googleLoading"
            class="w-full flex items-center justify-center gap-3 bg-white text-gray-700 font-semibold py-3 px-4 rounded-xl hover:bg-gray-100 transition border border-gray-200"
          >
            <GoogleIcon />
            {{ googleLoading ? 'A redirecionar...' : 'Registar Loja com Google' }}
          </button>
          <p v-if="error" class="text-red-400 text-sm text-center bg-red-900/20 rounded-lg p-2 mt-3">{{ error }}</p>
        </div>

        <!-- Nenhum método seleccionado ainda -->
        <div v-else class="text-center py-4 text-bc-muted text-sm">
          Selecciona como preferes entrar na tua loja ↑
        </div>

        <p class="text-center text-bc-muted text-sm mt-4">
          Já tens conta? <RouterLink to="/login" class="text-bc-gold hover:underline">Entrar</RouterLink>
        </p>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, defineComponent, h } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import axios from 'axios'

const router = useRouter()
const authStore = useAuthStore()

const accountType = ref(null)       // null | 'customer' | 'store'
const storeLoginMethod = ref(null)  // null | 'email' | 'google'
const loading = ref(false)
const googleLoading = ref(false)
const error = ref('')

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

// ─── Componente inline: ícone Google ────────────────────────
const GoogleIcon = defineComponent({
  render: () => h('svg', { class: 'w-5 h-5 flex-shrink-0', viewBox: '0 0 24 24' }, [
    h('path', { fill: '#4285F4', d: 'M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z' }),
    h('path', { fill: '#34A853', d: 'M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z' }),
    h('path', { fill: '#FBBC05', d: 'M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z' }),
    h('path', { fill: '#EA4335', d: 'M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z' }),
  ])
})

// ─── Componente inline: campo de senha com toggle ───────────
const PasswordField = defineComponent({
  props: { modelValue: String, placeholder: String },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const show = ref(false)
    return () => h('div', { class: 'relative' }, [
      h('input', {
        type: show.value ? 'text' : 'password',
        value: props.modelValue,
        placeholder: props.placeholder,
        class: 'input-african pr-10',
        required: true,
        onInput: (e) => emit('update:modelValue', e.target.value),
      }),
      h('button', {
        type: 'button',
        class: 'absolute right-3 top-1/2 -translate-y-1/2 text-bc-muted hover:text-bc-gold',
        onClick: () => { show.value = !show.value },
      }, show.value ? '🙈' : '👁️'),
    ])
  }
})

// ─── Registo com email/senha ─────────────────────────────────
async function submitRegister(role) {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.post('/auth/register', {
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
      role,
    })
    localStorage.setItem('bc_token', data.token)
    authStore.user = data.user
    authStore.token = data.token
    axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`

    if (role === 'store_owner') {
      router.push('/loja')
    } else {
      router.push('/')
    }
  } catch (e) {
    const errs = e.response?.data?.errors
    if (errs) {
      error.value = Object.values(errs).flat().join(' ')
    } else {
      error.value = e.response?.data?.message || 'Erro ao criar conta. Tente novamente.'
    }
  } finally {
    loading.value = false
  }
}

// ─── Registo / login com Google ──────────────────────────────
async function registerWithGoogle(role) {
  googleLoading.value = true
  error.value = ''
  try {
    // Guardar intenção de role para usar após o callback do Google
    localStorage.setItem('bc_pending_role', role)
    const { data } = await axios.get('/auth/google/redirect-url')
    window.location.href = data.url
  } catch {
    error.value = 'Erro ao conectar com Google. Tente novamente.'
    googleLoading.value = false
  }
}
</script>
