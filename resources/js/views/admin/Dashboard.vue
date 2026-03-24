<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-xl font-bold text-bc-light mb-6">Visão Geral — Admin</h1>

    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div v-for="i in 8" :key="i" class="skeleton h-24 rounded-2xl"></div>
    </div>

    <div v-else>
      <!-- KPIs principais -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="card-african p-4 text-center">
          <p class="text-bc-muted text-xs mb-1">Utilizadores</p>
          <p class="text-bc-gold font-bold text-2xl">{{ overview.total_users?.toLocaleString() ?? 0 }}</p>
        </div>
        <div class="card-african p-4 text-center">
          <p class="text-bc-muted text-xs mb-1">Lojas Activas</p>
          <p class="text-green-400 font-bold text-2xl">{{ overview.active_stores ?? 0 }}</p>
        </div>
        <div class="card-african p-4 text-center">
          <p class="text-bc-muted text-xs mb-1">Pedidos Hoje</p>
          <p class="text-bc-light font-bold text-2xl">{{ overview.orders_today ?? 0 }}</p>
        </div>
        <div class="card-african p-4 text-center">
          <p class="text-bc-muted text-xs mb-1">Comissões Pendentes</p>
          <p class="text-orange-400 font-bold text-2xl">{{ formatMZN(overview.pending_commissions) }}</p>
        </div>
      </div>

      <!-- Lojas pendentes de aprovação -->
      <div class="card-african p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-bc-gold font-semibold">Lojas Pendentes de Aprovação</h2>
          <RouterLink to="/admin/lojas" class="text-xs text-bc-muted hover:text-bc-gold">Ver todas →</RouterLink>
        </div>

        <div v-if="!overview.pending_stores?.length" class="text-center py-4 text-bc-muted text-sm">
          Nenhuma loja pendente.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="store in overview.pending_stores"
            :key="store.id"
            class="flex items-center justify-between bg-bc-surface-2 rounded-xl p-3"
          >
            <div>
              <p class="text-bc-light text-sm font-medium">{{ store.name }}</p>
              <p class="text-bc-muted text-xs">{{ store.owner?.name }} — {{ store.city?.name }}</p>
            </div>
            <div class="flex gap-2">
              <button @click="approveStore(store)" class="text-xs px-3 py-1 bg-green-500/20 text-green-300 rounded-lg hover:bg-green-500/30">
                Aprovar
              </button>
              <button @click="rejectStore(store)" class="text-xs px-3 py-1 bg-red-500/20 text-red-300 rounded-lg hover:bg-red-500/30">
                Rejeitar
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Atalhos -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <RouterLink to="/admin/utilizadores" class="card-african p-5 hover:border-bc-gold/40 transition block">
          <p class="text-2xl mb-2">👥</p>
          <p class="text-bc-light font-semibold">Utilizadores</p>
          <p class="text-bc-muted text-xs">Promover donos de loja, suspender contas</p>
        </RouterLink>
        <RouterLink to="/admin/lojas" class="card-african p-5 hover:border-bc-gold/40 transition block">
          <p class="text-2xl mb-2">🏪</p>
          <p class="text-bc-light font-semibold">Lojas</p>
          <p class="text-bc-muted text-xs">Aprovar, rejeitar ou suspender lojas</p>
        </RouterLink>
        <RouterLink to="/admin/comissoes" class="card-african p-5 hover:border-bc-gold/40 transition block">
          <p class="text-2xl mb-2">💰</p>
          <p class="text-bc-light font-semibold">Comissões</p>
          <p class="text-bc-muted text-xs">Receber 0,50 MZN por produto vendido</p>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const overview = ref({})
const loading = ref(true)

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin/overview')
    overview.value = data
  } finally {
    loading.value = false
  }
}

async function approveStore(store) {
  await axios.put(`/admin/stores/${store.id}/approve`)
  overview.value.pending_stores = overview.value.pending_stores.filter(s => s.id !== store.id)
}

async function rejectStore(store) {
  const reason = prompt('Motivo de rejeição (opcional):')
  await axios.put(`/admin/stores/${store.id}/reject`, { reason })
  overview.value.pending_stores = overview.value.pending_stores.filter(s => s.id !== store.id)
}

onMounted(load)
</script>
