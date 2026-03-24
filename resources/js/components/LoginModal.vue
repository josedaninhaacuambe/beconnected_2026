<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="state.visible"
        class="fixed inset-0 z-[100] flex items-center justify-center px-4"
        @click.self="close"
      >
        <!-- Overlay -->
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

        <!-- Modal -->
        <div class="relative w-full max-w-sm bg-bc-dark border border-bc-gold/30 rounded-2xl shadow-2xl p-6 z-10">
          <!-- Fechar -->
          <button @click="close" class="absolute top-4 right-4 text-bc-muted hover:text-bc-gold transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>

          <!-- Logo -->
          <div class="text-center mb-5">
            <div class="w-12 h-12 bg-bc-gold rounded-full flex items-center justify-center mx-auto mb-2">
              <span class="text-bc-dark font-bold text-lg">BC</span>
            </div>
            <h2 class="text-bc-light font-bold text-lg">Entrar para continuar</h2>
            <p class="text-bc-muted text-xs mt-1">Inicia sessão para adicionar ao carrinho</p>
          </div>

          <!-- Google -->
          <button
            @click="loginWithGoogle"
            :disabled="googleLoading"
            class="w-full flex items-center justify-center gap-3 bg-white text-gray-700 font-semibold py-3 px-4 rounded-xl hover:bg-gray-50 transition mb-4 border border-gray-200 disabled:opacity-60"
          >
            <svg v-if="!googleLoading" class="w-5 h-5" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            <svg v-else class="w-5 h-5 animate-spin text-gray-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ googleLoading ? 'A abrir Google...' : 'Continuar com Google' }}
          </button>

          <div class="flex items-center gap-3 mb-4">
            <div class="flex-1 border-t border-bc-gold/20"></div>
            <span class="text-bc-muted text-xs">ou com email</span>
            <div class="flex-1 border-t border-bc-gold/20"></div>
          </div>

          <!-- Email/Password -->
          <form @submit.prevent="handleLogin" class="space-y-3">
            <input
              v-model="form.email"
              type="email"
              placeholder="Email"
              class="input-african"
              required
              autocomplete="email"
            />
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Senha"
                class="input-african pr-10"
                required
                autocomplete="current-password"
              />
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

            <p v-if="error" class="text-red-400 text-xs text-center bg-red-900/20 rounded-lg p-2">{{ error }}</p>

            <button type="submit" :disabled="loading" class="btn-gold w-full py-3 text-sm disabled:opacity-60">
              {{ loading ? 'A entrar...' : 'Entrar' }}
            </button>
          </form>

          <p class="text-center text-bc-muted text-xs mt-4">
            Não tens conta?
            <RouterLink to="/registar" @click="close" class="text-bc-gold hover:underline">Criar conta</RouterLink>
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useAuthStore } from '../stores/auth.js'
import { useLoginModal } from '../composables/useLoginModal.js'
import axios from 'axios'

const authStore = useAuthStore()
const { state, close, onSuccess } = useLoginModal()

const loading = ref(false)
const googleLoading = ref(false)
const error = ref('')
const showPassword = ref(false)
const form = reactive({ email: '', password: '' })

async function loginWithGoogle() {
  googleLoading.value = true
  error.value = ''
  try {
    const { data } = await axios.get('/auth/google/redirect-url')

    const popup = window.open(
      data.url,
      'google-auth',
      'width=520,height=620,top=100,left=200,scrollbars=yes'
    )

    if (!popup) {
      // Popup bloqueado — redirecionar a página inteira
      window.location.href = data.url
      return
    }

    // Ouvir mensagem do popup quando Google autenticar
    const onMessage = (event) => {
      if (event.data?.type === 'google-auth-success') {
        window.removeEventListener('message', onMessage)
        clearInterval(pollInterval)
        authStore.token = event.data.token
        authStore.user = event.data.user
        localStorage.setItem('bc_token', event.data.token)
        googleLoading.value = false
        onSuccess()
      }
    }
    window.addEventListener('message', onMessage)

    // Fallback: verificar se popup fechou e tentar ler token do localStorage
    const pollInterval = setInterval(() => {
      if (popup.closed) {
        clearInterval(pollInterval)
        window.removeEventListener('message', onMessage)
        googleLoading.value = false
        // Se entretanto ficou autenticado via localStorage
        const token = localStorage.getItem('bc_token')
        if (token && !authStore.isAuthenticated) {
          authStore.initAuth().then(() => {
            if (authStore.isAuthenticated) onSuccess()
          })
        }
      }
    }, 500)

  } catch {
    error.value = 'Erro ao conectar com Google.'
    googleLoading.value = false
  }
}

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    await authStore.login(form.email, form.password)
    onSuccess()
  } catch (e) {
    error.value = e.response?.data?.message || 'Credenciais inválidas.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.modal-enter-active, .modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-from, .modal-leave-to {
  opacity: 0;
}
.modal-enter-active .relative,
.modal-leave-active .relative {
  transition: transform 0.2s ease;
}
.modal-enter-from .relative {
  transform: scale(0.95) translateY(10px);
}
.modal-leave-to .relative {
  transform: scale(0.95) translateY(10px);
}
</style>
