<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-xl font-bold text-bc-light mb-6">Gestão de Lojas</h1>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-5">
      <input
        v-model="search"
        @input="load"
        type="text"
        placeholder="Pesquisar por nome..."
        class="input-african flex-1 min-w-48"
      />
      <select v-model="statusFilter" @change="load" class="select-african">
        <option value="">Todos os estados</option>
        <option value="pending">Pendentes</option>
        <option value="active">Activas</option>
        <option value="rejected">Rejeitadas</option>
        <option value="suspended">Suspensas</option>
      </select>
    </div>

    <div class="card-african overflow-hidden">
      <div v-if="loading" class="p-4 space-y-2">
        <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl"></div>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-bc-gold/20 text-bc-muted text-xs uppercase">
            <th class="text-left p-4">Loja</th>
            <th class="text-left p-4">Dono</th>
            <th class="text-left p-4">Estado</th>
            <th class="text-left p-4">Criada</th>
            <th class="text-right p-4">Acções</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="store in stores" :key="store.id" class="border-b border-bc-gold/10 hover:bg-bc-gold/5">
            <td class="p-4">
              <p class="text-bc-light font-medium">{{ store.name }}</p>
              <p class="text-bc-muted text-xs">{{ store.city?.name }}</p>
            </td>
            <td class="p-4 text-bc-muted text-sm">{{ store.owner?.name }}</td>
            <td class="p-4">
              <span :class="statusBadge(store.status)">{{ store.status }}</span>
            </td>
            <td class="p-4 text-bc-muted text-xs">{{ formatDate(store.created_at) }}</td>
            <td class="p-4">
              <div class="flex items-center justify-end gap-2">
                <button
                  v-if="store.status === 'pending'"
                  @click="approveStore(store)"
                  class="text-xs px-2 py-1 bg-green-500/20 text-green-300 rounded-lg"
                >Aprovar</button>
                <button
                  v-if="store.status === 'pending'"
                  @click="rejectStore(store)"
                  class="text-xs px-2 py-1 bg-red-500/20 text-red-300 rounded-lg"
                >Rejeitar</button>
                <button
                  v-if="store.status === 'active'"
                  @click="suspendStore(store)"
                  class="text-xs px-2 py-1 border border-red-400/30 text-red-400 rounded-lg"
                >Suspender</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const stores = ref([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')

function statusBadge(s) {
  return 'text-xs px-2 py-0.5 rounded-full ' + ({
    active: 'bg-green-500/20 text-green-300',
    pending: 'bg-orange-500/20 text-orange-300',
    rejected: 'bg-red-500/20 text-red-300',
    suspended: 'bg-bc-surface text-bc-muted',
  }[s] ?? 'bg-bc-surface text-bc-muted')
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('pt-MZ')
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin/stores', {
      params: { search: search.value, status: statusFilter.value },
    })
    stores.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function approveStore(store) {
  await axios.put(`/admin/stores/${store.id}/approve`)
  store.status = 'active'
}

async function rejectStore(store) {
  const reason = prompt('Motivo (opcional):') ?? ''
  await axios.put(`/admin/stores/${store.id}/reject`, { reason })
  store.status = 'rejected'
}

async function suspendStore(store) {
  if (!confirm(`Suspender loja "${store.name}"?`)) return
  await axios.put(`/admin/stores/${store.id}/suspend`)
  store.status = 'suspended'
}

onMounted(load)
</script>
