import '../css/app.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router/index.js'
import App from './App.vue'
import axios from 'axios'
import './bootstrap'
import { trackEvent } from './utils/analytics.js'

// Expor utilitários de analytics para todo o app
if (typeof window !== 'undefined') {
  window.trackEvent = trackEvent
  window.addEventListener('analytics', (event) => {
    console.debug('[Analytics Event]', event.detail)
  })
}

// Configurar Axios
axios.defaults.baseURL = import.meta.env.VITE_API_URL || '/api'
axios.defaults.headers.common['Accept'] = 'application/json'

// Interceptor para token e loja activa (multi-loja)
axios.interceptors.request.use((config) => {
    const token = localStorage.getItem('bc_token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    // Envia a loja activa seleccionada pelo dono — lida no backend via X-Store-Id
    const storeId = localStorage.getItem('bc_active_store_id')
    if (storeId) {
        config.headers['X-Store-Id'] = storeId
    }
    return config
})

const AXIOS_RETRY_MAX = 3
const AXIOS_RETRY_BASE_DELAY_MS = 500

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error.response?.status

        if (status === 401) {
            localStorage.removeItem('bc_token')
            router.push('/login')
            return Promise.reject(error)
        }

        const config = error.config
        if (!config) {
            trackEvent('axios_error', { error: error.message })
            return Promise.reject(error)
        }

        config.__retryCount = config.__retryCount || 0

        if (config.__retryCount >= AXIOS_RETRY_MAX) {
            trackEvent('axios_retry_exhausted', {
                method: config.method,
                url: config.url,
                status,
                retries: config.__retryCount,
            })
            return Promise.reject(error)
        }

        const shouldRetry = !status || status >= 500 || status === 429
        if (!shouldRetry) {
            return Promise.reject(error)
        }

        config.__retryCount += 1
        const backoff = AXIOS_RETRY_BASE_DELAY_MS * Math.pow(2, config.__retryCount - 1)
        trackEvent('axios_retry', {
            method: config.method,
            url: config.url,
            status,
            retry: config.__retryCount,
            backoff,
        })

        await delay(backoff)
        return axios(config)
    }
)

import { useAuthStore } from './stores/auth.js'
import AppImg from './components/AppImg.vue'
import ProductFormModal from './components/ProductFormModal.vue'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)
app.component('AppImg', AppImg)
app.component('ProductFormModal', ProductFormModal)

// Montar a app IMEDIATAMENTE — utilizador vê a página sem esperar pela API
// O localStorage já tem os dados do utilizador para render instantâneo
// initAuth() corre em background e actualiza silenciosamente
app.mount('#app')

const authStore = useAuthStore()
authStore.initAuth() // background — não bloqueia
