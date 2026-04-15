<template>
  <div class="min-h-screen flex items-center justify-center px-4 bg-bc-dark">
    <div class="w-full max-w-sm">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="w-16 h-16 bg-bc-gold rounded-full flex items-center justify-center mx-auto mb-3">
          <span class="text-bc-dark font-bold text-2xl">BC</span>
        </div>
        <h1 class="text-bc-gold font-bold text-2xl">BECONNECT</h1>
        <p class="text-bc-muted text-sm">Mercado Virtual de Moçambique</p>
      </div>

      <!-- Aviso offline: sem sessão guardada, não é possível entrar -->
      <div v-if="isOffline" class="mb-4 rounded-xl p-4 text-center" style="background:#1a2a1a; border:1px solid #4ade80;">
        <p class="text-green-400 font-semibold text-sm mb-1">📵 Sem ligação à internet</p>
        <p class="text-green-300/70 text-xs">Para usar o POS offline, abre primeiro com internet e instala a app no dispositivo.</p>
      </div>

      <div class="card-african p-6">
        <h2 class="text-bc-light font-semibold text-lg mb-5 text-center">Entrar na conta</h2>

        <!-- Botão Google (principal) -->
        <button
          @click="loginWithGoogle"
          :disabled="googleLoading"
          class="w-full flex items-center justify-center gap-3 bg-white text-gray-700 font-semibold py-3 px-4 rounded-xl hover:bg-gray-100 transition mb-4 border border-gray-200"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          {{ googleLoading ? 'A redirecionar...' : 'Continuar com Google' }}
        </button>

        <div class="flex items-center gap-3 mb-4">
          <div class="flex-1 border-t border-bc-gold/20"></div>
          <span class="text-bc-muted text-xs">ou com email</span>
          <div class="flex-1 border-t border-bc-gold/20"></div>
        </div>

        <!-- Login email (secundário, para admin) -->
        <form @submit.prevent="handleLogin" class="space-y-3">
          <input v-model="form.email" type="email" placeholder="Email" class="input-african" required />
          <div class="relative">
            <input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Senha" class="input-african pr-10" required />
            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-bc-muted hover:text-bc-gold transition">
              <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>

          <p v-if="error" class="text-red-400 text-sm text-center bg-red-900/20 rounded-lg p-2">{{ error }}</p>

          <button type="submit" :disabled="loading" class="btn-ghost w-full py-3 text-sm">
            {{ loading ? 'A entrar...' : 'Entrar com Email' }}
          </button>
        </form>

        <p class="text-center text-bc-muted text-sm mt-4">
          Não tens conta?
          <RouterLink to="/registar" class="text-bc-gold hover:underline">Criar conta</RouterLink>
        </p>
      </div>

      <!-- Nota informativa -->
      <p class="text-center text-bc-muted text-xs mt-4 px-4">
        Ao entrar, concordas com os nossos Termos de Uso e Política de Privacidade.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import axios from 'axios'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()

const loading       = ref(false)
const googleLoading = ref(false)
const error         = ref('')
const showPassword  = ref(false)
const isOffline     = ref(!navigator.onLine)
const form          = reactive({ email: '', password: '' })

window.addEventListener('online',  () => { isOffline.value = false })
window.addEventListener('offline', () => { isOffline.value = true  })


// Se offline mas com sessão guardada → redirecionar sem passar pelo login
onMounted(() => {
  if (!navigator.onLine && authStore.isAuthenticated) {
    const dest = authStore.postLoginRedirect(authStore.user)
    router.replace(route.query.redirect || dest)
  }
})

// Apanhar token do Google OAuth callback
onMounted(async () => {
  const token = route.query.token
  const userJson = route.query.user

  if (token && userJson) {
    try {
      let user = JSON.parse(decodeURIComponent(userJson))
      localStorage.setItem('bc_token', token)
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

      // Aplicar role pendente (ex: registo de loja via Google)
      const pendingRole = localStorage.getItem('bc_pending_role')
      if (pendingRole && pendingRole !== user.role) {
        try {
          const { data } = await axios.post('/auth/claim-role', { role: pendingRole })
          user = data.user
        } catch {}
        localStorage.removeItem('bc_pending_role')
      }

      authStore.user = user
      authStore.token = token

      // Se está numa janela popup (aberta pelo LoginModal), envia mensagem ao pai e fecha
      if (window.opener && !window.opener.closed) {
        window.opener.postMessage({ type: 'google-auth-success', token, user }, window.location.origin)
        window.close()
        return
      }

      router.push(route.query.redirect || authStore.postLoginRedirect(user))
    } catch {}
  }
})

async function loginWithGoogle() {
  googleLoading.value = true
  try {
    const { data } = await axios.get('/auth/google/redirect-url')
    window.location.href = data.url
  } catch {
    error.value = 'Erro ao conectar com Google. Tente novamente.'
    googleLoading.value = false
  }
}

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.post('/auth/login', form)
    if (data.requires_otp) {
      pendingEmail.value = data.email
      otpStep.value = true
      startCooldown()
      return
    }
    localStorage.setItem('bc_token', data.token)
    authStore.user = data.user; authStore.token = data.token
    axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
    router.push(route.query.redirect || authStore.postLoginRedirect(data.user))
  } catch (e) {
    error.value = e.response?.data?.message || 'Credenciais inválidas.'
  } finally {
    loading.value = false
  }
}
</script>
