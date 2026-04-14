import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
    // Restaurar utilizador do localStorage imediatamente — sem esperar pela API
    const _cached = localStorage.getItem('bc_user')
    const user  = ref(_cached ? JSON.parse(_cached) : null)
    const token = ref(localStorage.getItem('bc_token'))

    // ── Multi-loja ───────────────────────────────────────────────────────────
    // Loja actualmente seleccionada pelo dono (persiste em localStorage)
    const _cachedStoreId = localStorage.getItem('bc_active_store_id')
    const activeStoreId  = ref(_cachedStoreId ? parseInt(_cachedStoreId) : null)

    // Todas as lojas do dono autenticado
    const allStores = computed(() => user.value?.stores ?? [])

    // Loja activa: a seleccionada, ou a única loja, ou a primeira
    const activeStore = computed(() => {
        const stores = allStores.value
        if (!stores.length) return user.value?.store ?? null
        if (activeStoreId.value) {
            return stores.find(s => s.id === activeStoreId.value) ?? stores[0]
        }
        return stores[0]
    })

    // Define a loja activa e envia o header X-Store-Id em todos os pedidos seguintes
    function setActiveStore(store) {
        activeStoreId.value = store.id
        localStorage.setItem('bc_active_store_id', store.id)
        // O interceptor axios lê directamente do localStorage — sem reinicialização
    }

    const isAuthenticated = computed(() => !!token.value)
    const isStoreOwner    = computed(() => user.value?.role === 'store_owner')
    const isAdmin         = computed(() => user.value?.role === 'admin')
    const isFullAdmin     = computed(() => user.value?.role === 'admin' && !user.value?.admin_role)
    const isPosEmployee   = computed(() => !!user.value?.pos_employee)
    const hasPermission   = (perm) => {
        if (isFullAdmin.value) return true
        return (user.value?.permissions ?? []).includes(perm)
    }

    // ── Permissões POS ───────────────────────────────────────────────────────
    // Dono tem sempre todas as permissões; funcionário usa as do pos_employee
    const posRole = computed(() => {
        if (user.value?.role === 'store_owner') return 'owner'
        if (user.value?.role === 'admin')       return 'admin'
        return user.value?.pos_employee?.role ?? 'cashier'
    })

    const posPermissions = computed(() => {
        if (user.value?.role === 'store_owner' || user.value?.role === 'admin') {
            return ['fazer_vendas', 'gerir_stock', 'ver_relatorios', 'gerir_equipa', 'adicionar_produtos']
        }
        return user.value?.pos_employee?.permissions ?? ['fazer_vendas']
    })

    const hasPosPermission = (perm) => posPermissions.value.includes(perm)

    const posAccessOptions = computed(() => user.value?.pos_access_options ?? [])

    // initAuth() é chamado em background — NÃO bloqueia o mount da app
    // Usa dados em cache do localStorage para render imediato,
    // depois actualiza silenciosamente a partir da API
    async function initAuth() {
        if (!token.value) return

        try {
            const { data } = await axios.get('/auth/me')
            user.value = data
            localStorage.setItem('bc_user', JSON.stringify(data))
            // Se o dono tem lojas mas ainda não seleccionou nenhuma, selecciona a primeira
            if (data.role === 'store_owner' && data.stores?.length && !activeStoreId.value) {
                setActiveStore(data.stores[0])
            }
        } catch {
            logout()
        }
    }

    // Retorna o path para onde o utilizador deve ir após login
    // Se o dono tem múltiplas lojas → página de selecção de loja
    function postLoginRedirect(userData) {
        const role = userData?.role
        if (role === 'store_owner') {
            if ((userData.stores?.length ?? 0) > 1) return '/escolher-loja'
            return '/loja'
        }
        if (role === 'admin')       return '/admin'
        if (userData?.pos_employee) return '/escolher-acesso'
        return '/'
    }

    async function login(email, password) {
        const { data } = await axios.post('/auth/login', { email, password })
        token.value = data.token
        user.value = data.user
        localStorage.setItem('bc_token', data.token)
        // Auto-seleccionar a única loja (se houver apenas uma)
        if (data.user.role === 'store_owner' && data.user.stores?.length) {
            setActiveStore(data.user.stores[0])
        }
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
        activeStoreId.value = null
        localStorage.removeItem('bc_token')
        localStorage.removeItem('bc_user')
        localStorage.removeItem('bc_active_store_id')
    }

    async function updateProfile(formData) {
        const { data } = await axios.post('/auth/profile', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        user.value = data
        localStorage.setItem('bc_user', JSON.stringify(data))
        return data
    }

    return {
        user, token, isAuthenticated, isStoreOwner, isAdmin, isFullAdmin, isPosEmployee,
        hasPermission, posRole, posPermissions, hasPosPermission, posAccessOptions,
        allStores, activeStore, activeStoreId, setActiveStore,
        postLoginRedirect, initAuth, login, register, logout, updateProfile,
    }
})
