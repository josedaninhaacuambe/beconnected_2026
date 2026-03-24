<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-xl font-bold text-bc-light mb-6">Gestão de Utilizadores</h1>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-5">
      <input
        v-model="search"
        @input="loadUsers"
        type="text"
        placeholder="Pesquisar por nome ou email..."
        class="input-african flex-1 min-w-48"
      />
      <select v-model="roleFilter" @change="loadUsers" class="select-african">
        <option value="">Todas as funções</option>
        <option value="customer">Clientes</option>
        <option value="store_owner">Donos de Loja</option>
        <option value="admin">Admins</option>
      </select>
    </div>

    <!-- Tabela -->
    <div class="card-african overflow-hidden">
      <div v-if="loading" class="p-4 space-y-2">
        <div v-for="i in 5" :key="i" class="skeleton h-12 rounded-xl"></div>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-bc-gold/20 text-bc-muted text-xs uppercase">
            <th class="text-left p-4">Utilizador</th>
            <th class="text-left p-4">Função</th>
            <th class="text-left p-4">Estado</th>
            <th class="text-left p-4">Registado</th>
            <th class="text-right p-4">Acções</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in users"
            :key="user.id"
            class="border-b border-bc-gold/10 hover:bg-bc-gold/5 transition"
          >
            <td class="p-4">
              <div class="flex items-center gap-3">
                <img
                  v-if="user.avatar || user.google_avatar"
                  :src="user.avatar || user.google_avatar"
                  class="w-8 h-8 rounded-full object-cover"
                />
                <div v-else class="w-8 h-8 rounded-full bg-bc-gold/20 flex items-center justify-center text-bc-gold font-bold text-xs">
                  {{ user.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div>
                  <p class="text-bc-light font-medium">{{ user.name }}</p>
                  <p class="text-bc-muted text-xs">{{ user.email }}</p>
                </div>
              </div>
            </td>
            <td class="p-4">
              <span :class="roleBadge(user.role)">{{ roleLabel(user.role) }}</span>
            </td>
            <td class="p-4">
              <span :class="user.is_active ? 'text-green-400' : 'text-red-400'">
                {{ user.is_active ? 'Activo' : 'Suspenso' }}
              </span>
            </td>
            <td class="p-4 text-bc-muted text-xs">
              {{ formatDate(user.created_at) }}
            </td>
            <td class="p-4">
              <div class="flex items-center justify-end gap-2">
                <button
                  v-if="user.role === 'customer'"
                  @click="promote(user)"
                  class="text-xs px-2 py-1 bg-bc-gold/20 text-bc-gold rounded-lg hover:bg-bc-gold/30"
                >
                  → Dono de Loja
                </button>
                <button
                  v-if="user.role === 'store_owner'"
                  @click="demote(user)"
                  class="text-xs px-2 py-1 bg-orange-500/20 text-orange-300 rounded-lg hover:bg-orange-500/30"
                >
                  → Cliente
                </button>
                <button
                  @click="toggleStatus(user)"
                  :class="user.is_active ? 'text-red-400 border-red-400/30' : 'text-green-400 border-green-400/30'"
                  class="text-xs px-2 py-1 border rounded-lg"
                >
                  {{ user.is_active ? 'Suspender' : 'Reactivar' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginação -->
      <div v-if="meta" class="p-4 flex items-center justify-between text-xs text-bc-muted border-t border-bc-gold/10">
        <span>{{ meta.total }} utilizadores</span>
        <div class="flex gap-2">
          <button
            v-for="p in meta.last_page"
            :key="p"
            @click="page = p; loadUsers()"
            :class="['px-2 py-1 rounded', p === meta.current_page ? 'bg-bc-gold text-bc-dark font-bold' : 'hover:bg-bc-gold/10']"
          >{{ p }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const users = ref([])
const loading = ref(true)
const search = ref('')
const roleFilter = ref('')
const page = ref(1)
const meta = ref(null)

function roleLabel(role) {
  return { customer: 'Cliente', store_owner: 'Dono de Loja', admin: 'Admin' }[role] ?? role
}

function roleBadge(role) {
  const base = 'text-xs px-2 py-0.5 rounded-full font-medium '
  return base + ({
    customer: 'bg-bc-surface text-bc-muted',
    store_owner: 'bg-bc-gold/20 text-bc-gold',
    admin: 'bg-red-500/20 text-red-300',
  }[role] ?? 'bg-bc-surface text-bc-muted')
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('pt-MZ')
}

async function loadUsers() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin/users', {
      params: { search: search.value, role: roleFilter.value, page: page.value },
    })
    users.value = data.data
    meta.value = data.meta ?? { total: data.total, current_page: data.current_page, last_page: data.last_page }
  } finally {
    loading.value = false
  }
}

async function promote(user) {
  if (!confirm(`Promover ${user.name} a Dono de Loja?`)) return
  await axios.put(`/admin/users/${user.id}/promote-store-owner`)
  user.role = 'store_owner'
}

async function demote(user) {
  if (!confirm(`Reverter ${user.name} para Cliente?`)) return
  await axios.put(`/admin/users/${user.id}/demote-customer`)
  user.role = 'customer'
}

async function toggleStatus(user) {
  const action = user.is_active ? 'suspender' : 'reactivar'
  if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} ${user.name}?`)) return
  await axios.put(`/admin/users/${user.id}/toggle`)
  user.is_active = !user.is_active
}

onMounted(loadUsers)
</script>
