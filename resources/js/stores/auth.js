import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null)
    const token = ref(localStorage.getItem('bc_token'))

    const isAuthenticated = computed(() => !!token.value)
    const isStoreOwner = computed(() => user.value?.role === 'store_owner')
    const isAdmin = computed(() => user.value?.role === 'admin')

    async function initAuth() {
        if (token.value) {
            try {
                const { data } = await axios.get('/auth/me')
                user.value = data
            } catch {
                logout()
            }
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
    }

    async function updateProfile(formData) {
        const { data } = await axios.post('/auth/profile', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        user.value = data
        return data
    }

    return { user, token, isAuthenticated, isStoreOwner, isAdmin, initAuth, login, register, logout, updateProfile }
})
