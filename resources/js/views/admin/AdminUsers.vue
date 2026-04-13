<template>
  <div class="space-y-6">
    <!-- Cabeçalho com Estatísticas -->
    <div>
      <h1 class="text-2xl font-bold text-bc-light">Gestão de Utilizadores</h1>
      <p class="text-bc-muted text-sm mt-1">Gerencie usuários do sistema</p>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="card-african p-4 text-center">
        <p class="text-3xl font-bold text-bc-gold">{{ stats.total_users }}</p>
        <p class="text-bc-muted text-sm mt-1">Total de Usuários</p>
      </div>
      <div class="card-african p-4 text-center">
        <p class="text-3xl font-bold text-bc-gold">{{ stats.active_users }}</p>
        <p class="text-bc-muted text-sm mt-1">Ativos</p>
      </div>
      <div class="card-african p-4 text-center">
        <p class="text-3xl font-bold text-bc-gold">{{ stats.total_store_owners }}</p>
        <p class="text-bc-muted text-sm mt-1">Donos de Loja</p>
      </div>
      <div class="card-african p-4 text-center">
        <p class="text-3xl font-bold text-bc-gold">{{ stats.total_customers }}</p>
        <p class="text-bc-muted text-sm mt-1">Clientes</p>
      </div>
    </div>

    <!-- Filtros e Busca -->
    <div class="card-african p-4 space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="text-sm text-bc-muted">Buscar</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Nome, email ou telefone..."
            class="input-field mt-2"
          />
        </div>

        <div>
          <label class="text-sm text-bc-muted">Tipo</label>
          <select v-model="filters.role" class="input-field mt-2">
            <option value="">Todos</option>
            <option value="admin">Admin</option>
            <option value="store_owner">Dono de Loja</option>
            <option value="customer">Cliente</option>
          </select>
        </div>

        <div>
          <label class="text-sm text-bc-muted">Status</label>
          <select v-model.number="filters.is_active" class="input-field mt-2">
            <option value="">Todos</option>
            <option :value="true">Ativos</option>
            <option :value="false">Inativos</option>
          </select>
        </div>

        <div>
          <label class="text-sm text-bc-muted">Email</label>
          <select v-model.number="filters.email_verified" class="input-field mt-2">
            <option value="">Todos</option>
            <option :value="true">Verificado</option>
            <option :value="false">Não Verificado</option>
          </select>
        </div>
      </div>

      <button @click="fetchUsers" class="btn-gold w-full">
        🔍 Buscar
      </button>
    </div>

    <!-- Tabela de Usuários -->
    <div class="card-african p-6 overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="border-b border-bc-gold/30">
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Nome</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Email</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Tipo</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Status</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Email</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="border-b border-bc-surface">
            <td class="py-3 px-4 text-bc-light font-medium">{{ user.name }}</td>
            <td class="py-3 px-4 text-bc-muted text-sm">{{ user.email }}</td>
            <td class="py-3 px-4">
              <span :class="getRoleClass(user.role)">
                {{ translateRole(user.role) }}
              </span>
            </td>
            <td class="py-3 px-4">
              <span :class="user.is_active ? 'bg-green-900/20 text-green-400' : 'bg-red-900/20 text-red-400'" class="px-3 py-1 rounded-lg text-sm">
                {{ user.is_active ? '✓ Ativo' : '✗ Inativo' }}
              </span>
            </td>
            <td class="py-3 px-4">
              <span :class="user.email_verified_at ? 'bg-green-900/20 text-green-400' : 'bg-yellow-900/20 text-yellow-400'" class="px-3 py-1 rounded-lg text-sm text-xs">
                {{ user.email_verified_at ? '✓ Verificado' : '⏳ Pendente' }}
              </span>
            </td>
            <td class="py-3 px-4 space-x-2">
              <button
                @click="selectUser(user)"
                class="text-bc-gold hover:text-bc-gold/80 text-sm"
              >
                Ver Detalhes
              </button>
              <button
                v-if="user.is_active"
                @click="openDeactivateModal(user)"
                class="text-red-400 hover:text-red-300 text-sm"
              >
                Desativar
              </button>
              <button
                v-else
                @click="reactivateUser(user)"
                class="text-green-400 hover:text-green-300 text-sm"
              >
                Reativar
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginação -->
      <div class="mt-6 flex justify-between items-center">
        <p class="text-bc-muted text-sm">
          Mostrando {{ users.length }} de {{ totalUsers }} usuários
        </p>
        <div class="space-x-2">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="btn-secondary px-4 py-2 text-sm disabled:opacity-50"
          >
            Anterior
          </button>
          <span class="text-bc-muted">{{ currentPage }} / {{ totalPages }}</span>
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="btn-secondary px-4 py-2 text-sm disabled:opacity-50"
          >
            Próxima
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de Detalhes -->
    <div v-if="selectedUser" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-bc-dark rounded-xl p-6 max-w-md w-full mx-4 space-y-4 max-h-96 overflow-y-auto">
        <div class="flex justify-between items-center">
          <h2 class="text-xl font-bold text-bc-light">{{ selectedUser.name }}</h2>
          <button @click="selectedUser = null" class="text-bc-muted">✕</button>
        </div>

        <div class="space-y-2 text-sm">
          <p><span class="text-bc-gold">Email:</span> {{ selectedUser.email }}</p>
          <p><span class="text-bc-gold">Telefone:</span> {{ selectedUser.phone || 'N/A' }}</p>
          <p><span class="text-bc-gold">Tipo:</span> {{ translateRole(selectedUser.role) }}</p>
          <p><span class="text-bc-gold">Status:</span> {{ selectedUser.is_active ? 'Ativo' : 'Inativo' }}</p>
          <p><span class="text-bc-gold">Membro desde:</span> {{ formatDate(selectedUser.created_at) }}</p>
          
          <div v-if="selectedUser.role === 'store_owner'" class="border-t border-bc-gold/30 pt-3 mt-3">
            <p class="text-bc-gold font-semibold mb-2">Lojas Próprias:</p>
            <ul class="text-bc-muted text-xs space-y-1">
              <li v-for="store in userDetails.stores" :key="store.id">
                • {{ store.name }} ({{ store.status }})
              </li>
            </ul>
          </div>

          <div v-if="selectedUser.role === 'customer'" class="border-t border-bc-gold/30 pt-3 mt-3 space-y-1">
            <p><span class="text-bc-gold">Pedidos:</span> {{ userDetails.orders_placed }}</p>
            <p><span class="text-bc-gold">Total Gasto:</span> {{ formatCurrency(userDetails.total_spent) }}</p>
          </div>
        </div>

        <button @click="selectedUser = null" class="btn-secondary w-full">
          Fechar
        </button>
      </div>
    </div>

    <!-- Modal de Desativação -->
    <div v-if="userToDeactivate" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-bc-dark rounded-xl p-6 max-w-md w-full mx-4 space-y-4">
        <h2 class="text-xl font-bold text-bc-light">Desativar Usuário</h2>
        <p class="text-bc-muted">Tem certeza que deseja desativar {{ userToDeactivate.name }}?</p>

        <textarea
          v-model="deactivateReason"
          placeholder="Motivo da desativação (opcional)..."
          class="input-field p-3"
          rows="3"
        ></textarea>

        <div class="flex gap-3">
          <button
            @click="confirmDeactivate"
            class="btn-gold flex-1"
          >
            Confirmar
          </button>
          <button
            @click="userToDeactivate = null"
            class="btn-secondary flex-1"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <div v-if="toast.message" :class="toast.type === 'success' ? 'bg-green-900/20 border-green-500' : 'bg-red-900/20 border-red-500'" class="fixed bottom-6 right-6 border rounded-lg p-4 text-sm max-w-sm">
      {{ toast.message }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const users = ref([])
const selectedUser = ref(null)
const userDetails = ref({})
const userToDeactivate = ref(null)
const deactivateReason = ref('')
const stats = ref({})
const toast = ref({ message: '', type: '' })

const filters = ref({
  search: '',
  role: '',
  is_active: '',
  email_verified: ''
})

const currentPage = ref(1)
const totalUsers = ref(0)
const totalPages = computed(() => Math.ceil(totalUsers.value / 20))

const fetchUsers = async () => {
  try {
    const params = new URLSearchParams()
    if (filters.value.search) params.append('search', filters.value.search)
    if (filters.value.role) params.append('role', filters.value.role)
    if (filters.value.is_active !== '') params.append('is_active', filters.value.is_active)
    if (filters.value.email_verified !== '') params.append('email_verified', filters.value.email_verified)
    params.append('page', currentPage.value)

    const response = await axios.get(`/api/admin/users-list?${params}`)
    users.value = response.data.data
    totalUsers.value = response.data.total
  } catch (error) {
    showToast('Erro ao carregar usuários', 'error')
  }
}

const fetchStatistics = async () => {
  try {
    const response = await axios.get('/api/admin/users-list/statistics/overall')
    stats.value = response.data
  } catch (error) {
    console.error('Erro ao carregar estatísticas:', error)
  }
}

const selectUser = async (user) => {
  try {
    const response = await axios.get(`/api/admin/users-list/${user.id}`)
    userDetails.value = response.data
    selectedUser.value = user
  } catch (error) {
    showToast('Erro ao carregar detalhes', 'error')
  }
}

const openDeactivateModal = (user) => {
  userToDeactivate.value = user
  deactivateReason.value = ''
}

const confirmDeactivate = async () => {
  try {
    await axios.put(`/api/admin/users-list/${userToDeactivate.value.id}/deactivate`, {
      reason: deactivateReason.value
    })
    userToDeactivate.value.is_active = false
    userToDeactivate.value = null
    showToast('Usuário desativado', 'success')
  } catch (error) {
    showToast('Erro ao desativar', 'error')
  }
}

const reactivateUser = async (user) => {
  try {
    await axios.put(`/api/admin/users-list/${user.id}/reactivate`)
    user.is_active = true
    showToast('Usuário reativado', 'success')
  } catch (error) {
    showToast('Erro ao reativar', 'error')
  }
}

const getRoleClass = (role) => {
  const classes = {
    'admin': 'bg-purple-900/20 text-purple-400 px-3 py-1 rounded-lg text-sm',
    'store_owner': 'bg-blue-900/20 text-blue-400 px-3 py-1 rounded-lg text-sm',
    'customer': 'bg-green-900/20 text-green-400 px-3 py-1 rounded-lg text-sm'
  }
  return classes[role] || 'px-3 py-1 rounded-lg text-sm'
}

const translateRole = (role) => {
  const map = {
    'admin': 'Admin',
    'store_owner': 'Dono de Loja',
    'customer': 'Cliente'
  }
  return map[role] || role
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('pt-PT')
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('pt-PT', {
    style: 'currency',
    currency: 'MZN'
  }).format(value)
}

const previousPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
    fetchUsers()
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
    fetchUsers()
  }
}

const showToast = (message, type = 'success') => {
  toast.value = { message, type }
  setTimeout(() => {
    toast.value = { message: '', type: '' }
  }, 3000)
}

onMounted(() => {
  fetchUsers()
  fetchStatistics()
})
</script>

<style scoped>
.card-african {
  background: linear-gradient(135deg, #2a3f5f 0%, #1c2b3c 100%);
  border: 1px solid #f07820;
  border-radius: 12px;
}

.input-field {
  background: #1c2b3c;
  border: 1px solid #f07820/30;
  border-radius: 8px;
  padding: 8px 12px;
  color: #e0e0e0;
  font-size: 14px;
}

.input-field:focus {
  outline: none;
  border-color: #f07820;
}

.btn-gold {
  background: #f07820;
  color: #1c2b3c;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-gold:hover {
  background: #d96a1a;
}

.btn-secondary {
  background: #2a3f5f;
  color: #e0e0e0;
  border: 1px solid #f07820/30;
  padding: 10px 20px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-secondary:hover:not(:disabled) {
  border-color: #f07820;
}
</style>
