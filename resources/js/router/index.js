import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'

const routes = [
    // ─── Público ─────────────────────────────────────────────
    {
        path: '/',
        component: () => import('../components/layout/AppLayout.vue'),
        children: [
            { path: '', name: 'home', component: () => import('../views/Home.vue') },
            { path: 'pesquisa', name: 'search', component: () => import('../views/Search.vue') },
            { path: 'produtos', name: 'products', component: () => import('../views/Products.vue') },
            { path: 'lojas', name: 'stores', component: () => import('../views/Stores.vue') },
            { path: 'lojas/:slug', name: 'store', component: () => import('../views/StoreDetail.vue') },
            { path: 'lojas/:storeSlug/produtos/:productSlug', name: 'product', component: () => import('../views/ProductDetail.vue') },
            { path: 'comprar-na-loja/:slug', name: 'in-store', component: () => import('../views/InStoreShopping.vue') },
            { path: 'rastrear/:codigo', name: 'track', component: () => import('../views/TrackDelivery.vue') },
            { path: 'queima-de-stock', name: 'flash-sales', component: () => import('../views/FlashSalesPage.vue') },
        ],
    },

    // ─── Autenticação ────────────────────────────────────────
    { path: '/entrar', name: 'login', component: () => import('../views/auth/Login.vue') },
    { path: '/registar', name: 'register', component: () => import('../views/auth/Register.vue') },
    { path: '/auth/google/success', name: 'google-success', component: () => import('../views/auth/Login.vue') },
    { path: '/escolher-acesso', name: 'pos-access-choice', component: () => import('../views/auth/PosAccessChoice.vue'), meta: { requiresAuth: true } },

    // Redireccionamentos de URLs antigas
    { path: '/login', redirect: '/entrar' },

    // ─── Área do cliente (autenticado) ───────────────────────
    {
        path: '/conta',
        component: () => import('../components/layout/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'account', component: () => import('../views/customer/Account.vue') },
            { path: 'carrinho', name: 'cart', component: () => import('../views/customer/Cart.vue') },
            { path: 'finalizar-compra', name: 'checkout', component: () => import('../views/customer/Checkout.vue') },
            { path: 'pedidos', name: 'orders', component: () => import('../views/customer/Orders.vue') },
            { path: 'pedidos/:numero', name: 'order-detail', component: () => import('../views/customer/OrderDetail.vue') },
            { path: 'favoritos', name: 'wishlist', component: () => import('../views/customer/Wishlist.vue') },
        ],
    },

    // Redireccionamentos de URLs antigas do checkout
    { path: '/conta/checkout', redirect: '/conta/finalizar-compra' },

    // ─── Painel do dono de loja ──────────────────────────────
    {
        path: '/loja',
        component: () => import('../components/layout/StoreLayout.vue'),
        meta: { requiresAuth: true, role: 'store_owner' },
        children: [
            { path: '', name: 'store-dashboard', component: () => import('../views/store/Dashboard.vue') },
            { path: 'produtos', name: 'store-products', component: () => import('../views/store/Products.vue') },
            { path: 'produtos/novo', name: 'store-product-create', component: () => import('../views/store/ProductForm.vue') },
            // URL limpa: usa slug do produto em vez do ID numérico
            { path: 'produtos/:slug/editar', name: 'store-product-edit', component: () => import('../views/store/ProductForm.vue') },
            { path: 'pedidos', name: 'store-orders', component: () => import('../views/store/Orders.vue') },
            { path: 'stock', name: 'store-stock', component: () => import('../views/store/Stock.vue') },
            { path: 'stock/importar', name: 'store-stock-import', component: () => import('../views/store/StockImport.vue') },
            { path: 'funcionarios', name: 'store-employees', component: () => import('../views/store/Employees.vue') },
            { path: 'visibilidade', name: 'store-visibility', component: () => import('../views/store/Visibility.vue') },
            { path: 'categorias', name: 'store-categories', component: () => import('../views/store/Categories.vue') },
            { path: 'queima-de-stock', name: 'store-flash-sales', component: () => import('../views/store/FlashSales.vue') },
            { path: 'configuracoes', name: 'store-settings', component: () => import('../views/store/Settings.vue') },
        ],
    },

    // ─── Painel de administração ─────────────────────────────
    {
        path: '/admin',
        component: () => import('../components/layout/AdminLayout.vue'),
        meta: { requiresAuth: true, role: 'admin' },
        children: [
            { path: '', name: 'admin-dashboard', component: () => import('../views/admin/Dashboard.vue') },
            { path: 'utilizadores', name: 'admin-users', component: () => import('../views/admin/Users.vue') },
            { path: 'lojas', name: 'admin-stores', component: () => import('../views/admin/Stores.vue') },
            { path: 'comissoes', name: 'admin-commissions', component: () => import('../views/admin/CommissionDashboard.vue') },
            { path: 'feedbacks', name: 'admin-feedbacks', component: () => import('../views/admin/Feedbacks.vue') },
            { path: 'entregas', name: 'admin-deliveries', component: () => import('../views/admin/Deliveries.vue') },
            { path: 'colaboradores', name: 'admin-staff', component: () => import('../views/admin/Colaboradores.vue') },
            { path: 'visibilidade', name: 'admin-visibility', component: () => import('../views/admin/Visibilidade.vue') },
            { path: 'pedidos', name: 'admin-pedidos', component: () => import('../views/admin/Pedidos.vue') },
        ],
    },

    // ─── POS — Ponto de Venda (owners + funcionários) ────────
    {
        path: '/pos',
        component: () => import('../components/layout/PosLayout.vue'),
        meta: { requiresAuth: true, requiresPosAccess: true },
        redirect: () => {
            // Redireciona para o primeiro tab disponível (auth já importado no topo)
            const auth = useAuthStore()
            if (auth.hasPosPermission('fazer_vendas'))   return '/pos/terminal'
            if (auth.hasPosPermission('gerir_stock'))    return '/pos/stock'
            if (auth.hasPosPermission('ver_relatorios')) return '/pos/reports'
            if (auth.hasPosPermission('gerir_equipa'))   return '/pos/employees'
            return '/acesso-negado'
        },
        children: [
            { path: 'terminal',  name: 'pos-terminal',   component: () => import('../views/pos/Terminal.vue'),            meta: { posPermission: 'fazer_vendas'   } },
            { path: 'caixa',     name: 'pos-caixa',      component: () => import('../views/pos/DailyCash.vue'),           meta: { posPermission: 'fazer_vendas'   } },
            { path: 'products',  name: 'pos-products',   component: () => import('../views/pos/ProductsManagement.vue'),  meta: { posPermission: 'fazer_vendas'   } },
            { path: 'stock',     name: 'pos-stock',      component: () => import('../views/pos/StockManagement.vue'),     meta: { posPermission: 'gerir_stock'    } },
            { path: 'reports',   name: 'pos-reports',    component: () => import('../views/pos/Reports.vue'),         meta: { posPermission: 'ver_relatorios' } },
            { path: 'employees', name: 'pos-employees',  component: () => import('../views/pos/Employees.vue'),       meta: { posPermission: 'gerir_equipa'   } },
        ],
    },

    // ─── Acesso negado ───────────────────────────────────────
    { path: '/acesso-negado', name: 'forbidden', component: () => import('../views/Forbidden.vue') },

    // ─── 404 ─────────────────────────────────────────────────
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFound.vue') },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to, _from, next) => {
    const authStore = useAuthStore()

    // Rota requer autenticação mas utilizador não está autenticado
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } })
    }

    // Rota requer um role específico (ex: /loja → store_owner)
    if (to.meta.role) {
        const userRole = authStore.user?.role

        if (userRole !== to.meta.role) {
            if (userRole === 'admin') return next()
            return next({ name: 'forbidden', query: { role: to.meta.role } })
        }
    }

    // Rota requer acesso ao POS — utilizador deve pertencer a uma loja
    if (to.meta.requiresPosAccess) {
        const userRole = authStore.user?.role
        const hasPosAccess = userRole === 'store_owner' || userRole === 'admin' || !!authStore.user?.pos_employee
        if (!hasPosAccess) {
            return next({ name: 'forbidden' })
        }
    }

    // Rota POS requer permissão específica
    if (to.meta.posPermission) {
        const userRole = authStore.user?.role
        // Dono e admin têm sempre acesso
        if (userRole !== 'store_owner' && userRole !== 'admin') {
            if (!authStore.hasPosPermission(to.meta.posPermission)) {
                // Redireciona para o primeiro tab disponível
                if (authStore.hasPosPermission('fazer_vendas'))   return next('/pos/terminal')
                if (authStore.hasPosPermission('gerir_stock'))    return next('/pos/stock')
                if (authStore.hasPosPermission('ver_relatorios')) return next('/pos/reports')
                if (authStore.hasPosPermission('gerir_equipa'))   return next('/pos/employees')
                return next({ name: 'forbidden' })
            }
        }
    }

    next()
})

export default router
