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

      <!-- ─── PASSO OTP: Verificação de email ──────────────────── -->
      <div v-if="otpStep" class="card-african p-6">
        <div class="text-center mb-6">
          <span class="text-5xl">📧</span>
          <h2 class="text-bc-light font-bold text-lg mt-3">Verifica o teu email</h2>
          <p class="text-bc-muted text-sm mt-1">
            Enviámos um código de 6 dígitos para<br>
            <strong class="text-bc-gold">{{ pendingEmail }}</strong>
          </p>
        </div>

        <form @submit.prevent="submitOtp" class="space-y-4">
          <!-- Input OTP com 6 caixas -->
          <div class="flex justify-center gap-2">
            <input
              v-for="(_, i) in otpDigits"
              :key="i"
              :ref="el => otpRefs[i] = el"
              v-model="otpDigits[i]"
              @input="onOtpInput(i)"
              @keydown.backspace="onOtpBackspace(i)"
              @paste.prevent="onOtpPaste($event)"
              type="text"
              inputmode="numeric"
              maxlength="1"
              class="w-11 h-12 text-center text-xl font-black rounded-xl border-2 border-bc-gold/30 bg-bc-surface text-bc-light focus:outline-none focus:border-bc-gold transition"
            />
          </div>

          <p v-if="otpError" class="text-red-400 text-sm text-center bg-red-900/20 rounded-lg p-2">{{ otpError }}</p>

          <button type="submit" :disabled="otpLoading || otpCode.length < 6" class="btn-gold w-full py-3 text-sm">
            {{ otpLoading ? 'A verificar...' : '✓ Verificar Código' }}
          </button>
        </form>

        <!-- Reenviar -->
        <div class="text-center mt-4">
          <p class="text-bc-muted text-xs mb-2">Não recebeste o código?</p>
          <button
            @click="resendOtp"
            :disabled="resendCooldown > 0"
            class="text-bc-gold text-sm hover:underline disabled:opacity-40"
          >
            {{ resendCooldown > 0 ? `Reenviar em ${resendCooldown}s` : 'Reenviar código' }}
          </button>
        </div>

        <button @click="otpStep = false" class="text-bc-muted text-xs hover:text-bc-gold mt-4 flex items-center gap-1">
          ← Voltar ao registo
        </button>
      </div>

      <!-- ─── PASSO 1: Escolha tipo de conta ───────────────────── -->
      <div v-else-if="!accountType" class="grid grid-cols-2 gap-4">
        <button @click="accountType = 'customer'"
          class="card-african p-6 text-center hover:border-bc-gold/60 transition group flex flex-col items-center gap-3">
          <span class="text-5xl">🛍️</span>
          <div>
            <p class="text-bc-light font-bold text-lg">Sou Cliente</p>
            <p class="text-bc-muted text-xs mt-1">Comprar produtos de lojas em Moçambique</p>
          </div>
          <span class="text-bc-gold text-sm group-hover:underline">Criar conta →</span>
        </button>
        <button @click="accountType = 'store'"
          class="card-african p-6 text-center hover:border-bc-gold/60 transition group flex flex-col items-center gap-3">
          <span class="text-5xl">🏪</span>
          <div>
            <p class="text-bc-light font-bold text-lg">Tenho uma Loja</p>
            <p class="text-bc-muted text-xs mt-1">Vender produtos na plataforma Beconnect</p>
          </div>
          <span class="text-bc-gold text-sm group-hover:underline">Registar loja →</span>
        </button>
      </div>

      <!-- ─── FLUXO CLIENTE ─────────────────────────────────────── -->
      <div v-else-if="accountType === 'customer'" class="card-african p-6">
        <button @click="accountType = null" class="text-bc-muted text-xs hover:text-bc-gold mb-4 flex items-center gap-1">← Voltar</button>
        <h2 class="text-bc-light font-semibold text-lg mb-5 text-center">Criar conta de cliente</h2>

        <button @click="registerWithGoogle('customer')" :disabled="googleLoading"
          class="w-full flex items-center justify-center gap-3 bg-white text-gray-700 font-semibold py-3 px-4 rounded-xl hover:bg-gray-100 transition mb-4 border border-gray-200">
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

      <!-- ─── FLUXO LOJA ─────────────────────────────────────────── -->
      <div v-else-if="accountType === 'store'" class="card-african p-6">
        <button @click="accountType = null" class="text-bc-muted text-xs hover:text-bc-gold mb-4 flex items-center gap-1">← Voltar</button>
        <h2 class="text-bc-light font-semibold text-lg mb-1 text-center">Registar a minha loja</h2>
        <p class="text-bc-muted text-xs text-center mb-5">Após o registo, a tua loja será avaliada pela equipa Beconnect.</p>

        <!-- ─── PASSO 1: Escolha método de login ────────────────── -->
        <div v-if="!storeLoginMethod" class="mb-5">
          <p class="text-bc-muted text-xs mb-2 text-center">Como preferes entrar na tua loja?</p>
          <div class="grid grid-cols-2 gap-2">
            <button @click="storeLoginMethod = 'email'"
              class="py-2 px-3 rounded-xl text-sm font-medium border border-bc-gold/30 text-bc-muted hover:border-bc-gold hover:text-bc-light transition">
              ✉️ Utilizador e Senha
            </button>
            <button @click="storeLoginMethod = 'google'"
              class="py-2 px-3 rounded-xl text-sm font-medium border border-bc-gold/30 text-bc-muted hover:border-bc-gold hover:text-bc-light transition">
              🔵 Usar Google
            </button>
          </div>
        </div>

        <!-- ─── PASSO 2: Dados preliminares da loja ────────────── -->
        <div v-else-if="!storeDataCollected" class="space-y-4">
          <button @click="storeLoginMethod = null" class="text-bc-muted text-xs hover:text-bc-gold mb-4 flex items-center gap-1">← Voltar</button>

          <div class="text-center mb-4">
            <span class="text-4xl">🏪</span>
            <h3 class="text-bc-light font-semibold text-base mt-2">Dados da tua loja</h3>
            <p class="text-bc-muted text-xs">Estes dados podem ser alterados posteriormente no painel de configurações.</p>
          </div>

          <form @submit.prevent="collectStoreData" class="space-y-3">
            <input v-model="storeForm.name" type="text" placeholder="Nome da loja" class="input-african" required />
            <textarea v-model="storeForm.description" placeholder="Descrição da loja (ex: O que vendes, horário de funcionamento, etc.)" class="input-african resize-none" rows="3" required></textarea>
            <input v-model="storeForm.phone" type="tel" placeholder="Contacto (ex: +258 84 123 4567)" class="input-african" required />
            <input v-model="storeForm.whatsapp" type="tel" placeholder="WhatsApp (ex: +258 84 123 4567)" class="input-african" required />
            <input v-model="storeForm.address" type="text" placeholder="Endereço (opcional)" class="input-african" />

            <p v-if="error" class="text-red-400 text-sm text-center bg-red-900/20 rounded-lg p-2">{{ error }}</p>

            <button type="submit" :disabled="loading" class="btn-gold w-full py-3 text-sm">
              {{ loading ? 'A processar...' : 'Continuar →' }}
            </button>
          </form>
        </div>

        <!-- ─── PASSO 3: Registo do usuário ─────────────────────── -->
        <div v-else-if="storeDataCollected">
          <button @click="storeDataCollected = false" class="text-bc-muted text-xs hover:text-bc-gold mb-4 flex items-center gap-1">← Voltar</button>

          <div v-if="storeLoginMethod === 'email'">
            <h3 class="text-bc-light font-semibold text-base mb-3 text-center">Criar conta de dono da loja</h3>

            <form @submit.prevent="submitRegisterWithStore('store_owner')" class="space-y-3">
              <input v-model="form.name" type="text" placeholder="Teu nome completo" class="input-african" required />
              <input v-model="form.email" type="email" placeholder="Email" class="input-african" required />
              <PasswordField v-model="form.password" placeholder="Senha (mín. 8 caracteres)" />
              <PasswordField v-model="form.password_confirmation" placeholder="Confirmar senha" />
              <p v-if="error" class="text-red-400 text-sm text-center bg-red-900/20 rounded-lg p-2">{{ error }}</p>
              <button type="submit" :disabled="loading" class="btn-gold w-full py-3 text-sm">
                {{ loading ? 'A criar conta...' : 'Registar Loja' }}
              </button>
            </form>
          </div>

          <div v-else-if="storeLoginMethod === 'google'">
            <div class="bg-bc-gold/5 border border-bc-gold/20 rounded-xl p-3 mb-4 text-xs text-bc-muted">
              <p class="text-bc-light font-medium mb-1">✅ Mais seguro e rápido</p>
              <p>A tua conta Google será usada para aceder ao painel da loja.</p>
            </div>
            <button @click="registerWithGoogleAndStore('store_owner')" :disabled="googleLoading"
              class="w-full flex items-center justify-center gap-3 bg-white text-gray-700 font-semibold py-3 px-4 rounded-xl hover:bg-gray-100 transition border border-gray-200">
              <GoogleIcon />
              {{ googleLoading ? 'A redirecionar...' : 'Registar Loja com Google' }}
            </button>
            <p v-if="error" class="text-red-400 text-sm text-center bg-red-900/20 rounded-lg p-2 mt-3">{{ error }}</p>
          </div>
        </div>

        <p class="text-center text-bc-muted text-sm mt-4">
          Já tens conta? <RouterLink to="/login" class="text-bc-gold hover:underline">Entrar</RouterLink>
        </p>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, defineComponent, h } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import axios from 'axios'

const router    = useRouter()
const authStore = useAuthStore()

const accountType      = ref(null)
const storeLoginMethod = ref(null)
const storeDataCollected = ref(false)
const loading          = ref(false)
const googleLoading    = ref(false)
const error            = ref('')

// OTP state
const otpStep      = ref(false)
const pendingEmail = ref('')
const pendingRole  = ref('')
const otpDigits    = ref(['', '', '', '', '', ''])
const otpRefs      = ref([])
const otpLoading   = ref(false)
const otpError     = ref('')
const resendCooldown = ref(0)
let cooldownTimer = null

const otpCode = computed(() => otpDigits.value.join(''))

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const storeForm = reactive({ name: '', description: '', phone: '', whatsapp: '', address: '' })

// ─── OTP input handlers ──────────────────────────────────────
function onOtpInput(i) {
  const val = otpDigits.value[i]
  if (val && i < 5) otpRefs.value[i + 1]?.focus()
}
function onOtpBackspace(i) {
  if (!otpDigits.value[i] && i > 0) {
    otpDigits.value[i - 1] = ''
    otpRefs.value[i - 1]?.focus()
  }
}
function onOtpPaste(e) {
  const text = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6)
  for (let i = 0; i < 6; i++) otpDigits.value[i] = text[i] ?? ''
  otpRefs.value[Math.min(text.length, 5)]?.focus()
}

function startCooldown() {
  resendCooldown.value = 60
  clearInterval(cooldownTimer)
  cooldownTimer = setInterval(() => {
    resendCooldown.value--
    if (resendCooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}

// ─── Verificar OTP ───────────────────────────────────────────
async function submitOtp() {
  if (otpCode.value.length < 6) return
  otpLoading.value = true
  otpError.value = ''
  try {
    const { data } = await axios.post('/auth/verify-otp', { email: pendingEmail.value, otp: otpCode.value })
    localStorage.setItem('bc_token', data.token)
    authStore.user  = data.user
    authStore.token = data.token
    axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
    if (data.user.role === 'store_owner') router.push('/loja')
    else router.push('/')
  } catch (e) {
    otpError.value = e.response?.data?.message || 'Código inválido. Tenta novamente.'
    otpDigits.value = ['', '', '', '', '', '']
    otpRefs.value[0]?.focus()
  } finally {
    otpLoading.value = false
  }
}

// ─── Reenviar OTP ────────────────────────────────────────────
async function resendOtp() {
  try {
    await axios.post('/auth/resend-otp', { email: pendingEmail.value })
    otpError.value = ''
    startCooldown()
  } catch (e) {
    otpError.value = e.response?.data?.message || 'Erro ao reenviar.'
  }
}

// ─── Registo com email ───────────────────────────────────────
async function submitRegister(role) {
  error.value = ''

  if (form.password !== form.password_confirmation) {
    error.value = 'A senha e a confirmação não coincidem.'
    return
  }

  loading.value = true
  try {
    const { data } = await axios.post('/auth/register', {
      name: form.name, email: form.email,
      password: form.password, password_confirmation: form.password_confirmation,
      role,
    })
    if (data.requires_otp) {
      pendingEmail.value = data.email
      pendingRole.value  = role
      otpStep.value      = true
      startCooldown()
    }
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(' ') : (e.response?.data?.message || 'Erro ao criar conta.')
  } finally {
    loading.value = false
  }
}

// ─── Registo com Google ──────────────────────────────────────
async function registerWithGoogle(role) {
  googleLoading.value = true
  error.value = ''
  try {
    localStorage.setItem('bc_pending_role', role)
    const { data } = await axios.get('/auth/google/redirect-url')
    window.location.href = data.url
  } catch {
    error.value = 'Erro ao conectar com Google. Tente novamente.'
    googleLoading.value = false
  }
}

// ─── Componentes inline ──────────────────────────────────────
const GoogleIcon = defineComponent({
  render: () => h('svg', { class: 'w-5 h-5 flex-shrink-0', viewBox: '0 0 24 24' }, [
    h('path', { fill: '#4285F4', d: 'M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z' }),
    h('path', { fill: '#34A853', d: 'M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z' }),
    h('path', { fill: '#FBBC05', d: 'M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z' }),
    h('path', { fill: '#EA4335', d: 'M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z' }),
  ])
})

const collectStoreData = () => {
  if (!storeForm.name.trim()) {
    error.value = 'Nome da loja é obrigatório'
    return
  }
  if (!storeForm.phone.trim()) {
    error.value = 'Telefone é obrigatório'
    return
  }
  storeDataCollected.value = true
  error.value = ''
}

const submitRegisterWithStore = async () => {
  if (!form.name.trim() || !form.email.trim() || !form.password.trim()) {
    error.value = 'Todos os campos são obrigatórios'
    return
  }
  if (form.password !== form.password_confirmation) {
    error.value = 'As senhas não coincidem'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const response = await axios.post('/auth/register-with-store', {
      name:                  form.name,
      email:                 form.email,
      password:              form.password,
      password_confirmation: form.password_confirmation,
      store: {
        name:        storeForm.name,
        description: storeForm.description,
        phone:       storeForm.phone,
        whatsapp:    storeForm.whatsapp,
        address:     storeForm.address,
      },
    })

    if (response.data.requires_otp) {
      pendingEmail.value = response.data.email || form.email
      pendingRole.value  = 'store_owner'
      otpStep.value      = true
      startCooldown()
    } else if (response.data.token) {
      localStorage.setItem('bc_token', response.data.token)
      authStore.user  = response.data.user
      authStore.token = response.data.token
      axios.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`
      router.push('/loja')
    }
  } catch (err) {
    const errs = err.response?.data?.errors
    error.value = errs
      ? Object.values(errs).flat().join(' ')
      : (err.response?.data?.message || 'Erro ao registar loja. Tente novamente.')
  } finally {
    loading.value = false
  }
}

const registerWithGoogleAndStore = async () => {
  googleLoading.value = true
  error.value = ''

  try {
    // Redirect to Google OAuth with store data in session/state
    const response = await axios.post('auth/google/register-with-store', {
      store: storeForm
    })

    if (response.data.redirect_url) {
      window.location.href = response.data.redirect_url
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Erro ao iniciar registro com Google'
    googleLoading.value = false
  }
}

const PasswordField = defineComponent({
  props: { modelValue: String, placeholder: String },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const show = ref(false)
    return () => h('div', { class: 'relative' }, [
      h('input', { type: show.value ? 'text' : 'password', value: props.modelValue, placeholder: props.placeholder, class: 'input-african pr-10', required: true, onInput: (e) => emit('update:modelValue', e.target.value) }),
      h('button', { type: 'button', class: 'absolute right-3 top-1/2 -translate-y-1/2 text-bc-muted hover:text-bc-gold', onClick: () => { show.value = !show.value } }, show.value ? '🙈' : '👁️'),
    ])
  }
})
</script>
