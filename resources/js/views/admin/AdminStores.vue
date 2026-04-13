<template>
  <div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-bc-light">Gestão de Lojas</h1>
        <p class="text-bc-muted text-sm mt-1">Gerencie disponibilidade e visibilidade das lojas</p>
      </div>
    </div>

    <!-- Filtros e Busca -->
    <div class="card-african p-4 space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="text-sm text-bc-muted">Buscar Loja</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Nome ou dono..."
            class="input-field mt-2"
          />
        </div>

        <div>
          <label class="text-sm text-bc-muted">Status</label>
          <select v-model="filters.status" class="input-field mt-2">
            <option value="">Todos</option>
            <option value="pending">Pendente</option>
            <option value="active">Ativa</option>
            <option value="suspended">Suspensa</option>
            <option value="rejected">Rejeitada</option>
          </select>
        </div>

        <div>
          <label class="text-sm text-bc-muted">Disponibilidade</label>
          <select v-model="filters.availability" class="input-field mt-2">
            <option value="">Todas</option>
            <option value="pos_only">Apenas POS</option>
            <option value="virtual_only">Apenas Virtual</option>
            <option value="both">Ambas</option>
          </select>
        </div>

        <div>
          <label class="text-sm text-bc-muted">Visibilidade</label>
          <select v-model.number="filters.is_public" class="input-field mt-2">
            <option value="">Todas</option>
            <option :value="true">Pública</option>
            <option :value="false">Privada</option>
          </select>
        </div>
      </div>

      <button @click="fetchStores" class="btn-gold w-full">
        🔍 Buscar
      </button>
    </div>

    <!-- Tabela de Lojas -->
    <div class="card-african p-6 overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="border-b border-bc-gold/30">
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Nome</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Dono</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Status</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Disponibilidade</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Visível</th>
            <th class="text-left py-3 px-4 text-bc-gold font-semibold">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="store in stores" :key="store.id" class="border-b border-bc-surface">
            <td class="py-3 px-4 text-bc-light font-medium">{{ store.name }}</td>
            <td class="py-3 px-4 text-bc-muted text-sm">{{ store.user.name }}</td>
            <td class="py-3 px-4">
              <span :class="getStatusClass(store.status)">
                {{ translateStatus(store.status) }}
              </span>
            </td>
            <td class="py-3 px-4">
              <select
                :value="store.availability_type"
                @change="updateAvailability(store, $event)"
                class="input-field text-sm py-1"
              >
                <option value="pos_only">Apenas POS</option>
                <option value="virtual_only">Apenas Virtual</option>
                <option value="both">Ambas</option>
              </select>
            </td>
            <td class="py-3 px-4">
              <button
                @click="toggleVisibility(store)"
                :class="store.is_visible_to_public ? 'bg-bc-gold/20 text-bc-gold' : 'bg-bc-surface text-bc-muted'"
                class="px-3 py-1 rounded-lg text-sm transition"
              >
                {{ store.is_visible_to_public ? '✓ Pública' : '✗ Privada' }}
              </button>
            </td>
            <td class="py-3 px-4 space-x-2">
              <button
                @click="selectStore(store)"
                class="text-bc-gold hover:text-bc-gold/80 text-sm"
              >
                Ver Detalhes
              </button>
              <button
                v-if="store.status === 'active'"
                @click="openSuspendModal(store)"
                class="text-red-400 hover:text-red-300 text-sm"
              >
                Suspender
              </button>
              <button
                v-else-if="store.status === 'suspended'"
                @click="reactivateStore(store)"
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
          Mostrando {{ stores.length }} de {{ totalStores }} lojas
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
    <div v-if="selectedStore" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-bc-dark rounded-xl p-6 max-w-md w-full mx-4 space-y-4">
        <div class="flex justify-between items-center">
          <h2 class="text-xl font-bold text-bc-light">{{ selectedStore.name }}</h2>
          <button @click="selectedStore = null" class="text-bc-muted">✕</button>
        </div>

        <div class="space-y-2 text-sm">
          <p><span class="text-bc-gold">Dono:</span> {{ selectedStore.user.name }}</p>
          <p><span class="text-bc-gold">Email:</span> {{ selectedStore.user.email }}</p>
          <p><span class="text-bc-gold">Produtos:</span> {{ storeDetails.products_count }}</p>
          <p><span class="text-bc-gold">Públicos:</span> {{ storeDetails.public_products }}</p>
          <p><span class="text-bc-gold">Pedidos:</span> {{ storeDetails.orders_count }}</p>
        </div>

        <button @click="selectedStore = null" class="btn-secondary w-full">
          Fechar
        </button>
      </div>
    </div>

    <!-- Modal de Suspensão -->
    <div v-if="storeToSuspend" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-bc-dark rounded-xl p-6 max-w-md w-full mx-4 space-y-4">
        <h2 class="text-xl font-bold text-bc-light">Suspender Loja</h2>
        <p class="text-bc-muted">Tem certeza que deseja suspender {{ storeToSuspend.name }}?</p>

        <textarea
          v-model="suspendReason"
          placeholder="Motivo da suspensão..."
          class="input-field p-3"
          rows="3"
        ></textarea>

        <div class="flex gap-3">
          <button
            @click="confirmSuspend"
            class="btn-gold flex-1"
          >
            Confirmar
          </button>
          <button
            @click="storeToSuspend = null"
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

const stores = ref([])
const selectedStore = ref(null)
const storeDetails = ref({})
const storeToSuspend = ref(null)
const suspendReason = ref('')
const toast = ref({ message: '', type: '' })

const filters = ref({
  search: '',
  status: '',
  availability: '',
  is_public: ''
})

const currentPage = ref(1)
const totalStores = ref(0)
const totalPages = computed(() => Math.ceil(totalStores.value / 15))

const fetchStores = async () => {
  try {
    const params = new URLSearchParams()
    if (filters.value.search) params.append('search', filters.value.search)
    if (filters.value.status) params.append('status', filters.value.status)
    if (filters.value.availability) params.append('availability', filters.value.availability)
    if (filters.value.is_public !== '') params.append('is_public', filters.value.is_public)
    params.append('page', currentPage.value)

    const response = await axios.get(`/api/admin/stores-list?${params}`)
    stores.value = response.data.data
    totalStores.value = response.data.total
  } catch (error) {
    showToast('Erro ao carregar lojas', 'error')
  }
}

const selectStore = async (store) => {
  try {
    const response = await axios.get(`/api/admin/stores-list/${store.id}`)
    storeDetails.value = response.data
    selectedStore.value = store
  } catch (error) {
    showToast('Erro ao carregar detalhes', 'error')
  }
}

const updateAvailability = async (store, event) => {
  try {
    const availability_type = event.target.value
    await axios.put(`/api/admin/stores-list/${store.id}/availability`, {
      availability_type
    })
    store.availability_type = availability_type
    showToast('Disponibilidade atualizada', 'success')
  } catch (error) {
    showToast('Erro ao atualizar', 'error')
  }
}

const toggleVisibility = async (store) => {
  try {
    await axios.put(`/api/admin/stores-list/${store.id}/visibility`, {
      is_visible: !store.is_visible_to_public
    })
    store.is_visible_to_public = !store.is_visible_to_public
    showToast(`Visibilidade ${store.is_visible_to_public ? 'ativada' : 'desativada'}`, 'success')
  } catch (error) {
    showToast('Erro ao atualizar visibilidade', 'error')
  }
}

const openSuspendModal = (store) => {
  storeToSuspend.value = store
  suspendReason.value = ''
}

const confirmSuspend = async () => {
  try {
    await axios.put(`/api/admin/stores-list/${storeToSuspend.value.id}/suspend`, {
      reason: suspendReason.value
    })
    storeToSuspend.value.status = 'suspended'
    storeToSuspend.value = null
    showToast('Loja suspensa', 'success')
  } catch (error) {
    showToast('Erro ao suspender', 'error')
  }
}

const reactivateStore = async (store) => {
  try {
    await axios.put(`/api/admin/stores-list/${store.id}/reactivate`)
    store.status = 'active'
    showToast('Loja reativada', 'success')
  } catch (error) {
    showToast('Erro ao reativar', 'error')
  }
}

const getStatusClass = (status) => {
  const classes = {
    'active': 'bg-green-900/20 text-green-400 px-3 py-1 rounded-lg text-sm',
    'pending': 'bg-yellow-900/20 text-yellow-400 px-3 py-1 rounded-lg text-sm',
    'suspended': 'bg-red-900/20 text-red-400 px-3 py-1 rounded-lg text-sm',
    'rejected': 'bg-red-900/20 text-red-400 px-3 py-1 rounded-lg text-sm'
  }
  return classes[status] || 'px-3 py-1 rounded-lg text-sm'
}

const translateStatus = (status) => {
  const map = {
    'active': 'Ativa',
    'pending': 'Pendente',
    'suspended': 'Suspensa',
    'rejected': 'Rejeitada'
  }
  return map[status] || status
}

const previousPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
    fetchStores()
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
    fetchStores()
  }
}

const showToast = (message, type = 'success') => {
  toast.value = { message, type }
  setTimeout(() => {
    toast.value = { message: '', type: '' }
  }, 3000)
}

onMounted(() => {
  fetchStores()
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
