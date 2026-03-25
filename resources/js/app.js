import '../css/app.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router/index.js'
import App from './App.vue'
import axios from 'axios'
import './bootstrap'

// Configurar Axios
axios.defaults.baseURL = import.meta.env.VITE_API_URL || '/api'
axios.defaults.headers.common['Accept'] = 'application/json'

// Interceptor para token
axios.interceptors.request.use((config) => {
    const token = localStorage.getItem('bc_token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('bc_token')
            router.push('/login')
        }
        return Promise.reject(error)
    }
)

import { useAuthStore } from './stores/auth.js'
import AppImg from './components/AppImg.vue'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)
app.component('AppImg', AppImg)

// Montar a app IMEDIATAMENTE — utilizador vê a página sem esperar pela API
// O localStorage já tem os dados do utilizador para render instantâneo
// initAuth() corre em background e actualiza silenciosamente
app.mount('#app')

const authStore = useAuthStore()
authStore.initAuth() // background — não bloqueia
