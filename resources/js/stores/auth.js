import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
    // Restaurar utilizador do localStorage imediatamente — sem esperar pela API
    const _cached = localStorage.getItem('bc_user')
    const user  = ref(_cached ? JSON.parse(_cached) : null)
    const token = ref(localStorage.getItem('bc_token'))

    const isAuthenticated = computed(() => !!token.value)
    const isStoreOwner    = computed(() => user.value?.role === 'store_owner')
    const isAdmin         = computed(() => user.value?.role === 'admin')

    // initAuth() é chamado em background — NÃO bloqueia o mount da app
    // Usa dados em cache do localStorage para render imediato,
    // depois actualiza silenciosamente a partir da API
    async function initAuth() {
        if (!token.value) return

        try {
            const { data } = await axios.get('/auth/me')
            user.value = data
            localStorage.setItem('bc_user', JSON.stringify(data))
        } catch {
            logout()
        }
    }

    async function login(email, password) {
        const { data } = await axios.post('/auth/login', { email, password })
        token.value = data.token
        user.value = data.user
        localStorage.setItem('bc_token', data.token)
        return data
    }

    async function register(payload) {
        const { data } = await axios.post('/auth/register', payload)
        token.value = data.token
        user.value = data.user
        localStorage.setItem('bc_token', data.token)
        return data
    }

    async function logout() {
        try { await axios.post('/auth/logout') } catch {}
        token.value = null
        user.value = null
        localStorage.removeItem('bc_token')
        localStorage.removeItem('bc_user')
    }

    async function updateProfile(formData) {
        const { data } = await axios.post('/auth/profile', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        user.value = data
        localStorage.setItem('bc_user', JSON.stringify(data))
        return data
    }

    return { user, token, isAuthenticated, isStoreOwner, isAdmin, initAuth, login, register, logout, updateProfile }
})
