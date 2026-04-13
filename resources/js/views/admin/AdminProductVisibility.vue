<template>
  <div class="space-y-6">
    <!-- Cabeçalho -->
    <div>
      <h1 class="text-2xl font-bold text-bc-light">Visibilidade de Produtos</h1>
      <p class="text-bc-muted text-sm mt-1">Aprove/rejeite produtos para exibição pública</p>
    </div>

    <!-- Tabs: Pendentes vs Histórico -->
    <div class="flex gap-2 border-b border-bc-gold/30">
      <button
        :class="activeTab === 'pending' ? 'border-b-2 border-bc-gold' : ''"
        @click="activeTab = 'pending'"
        class="px-4 py-2 text-bc-light font-medium"
      >
        📋 Pendentes ({{ pendingCount }})
      </button>
      <button
        :class="activeTab === 'approved' ? 'border-b-2 border-bc-gold' : ''"
        @click="activeTab = 'approved'"
        class="px-4 py-2 text-bc-light font-medium"
      >
        ✓ Aprovados
      </button>
      <button
        :class="activeTab === 'store' ? 'border-b-2 border-bc-gold' : ''"
        @click="activeTab = 'store'"
        class="px-4 py-2 text-bc-light font-medium"
      >
        🏪 Por Loja
      </button>
    </div>

    <!-- PENDENTES -->
    <div v-if="activeTab === 'pending'" class="space-y-4">
      <!-- Filtros -->
      <div class="card-african p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-sm text-bc-muted">Buscar Produto</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Nome do produto..."
              class="input-field mt-2"
            />
          </div>

          <div>
            <label class="text-sm text-bc-muted">Filtrar por Loja</label>
            <select v-model="filters.store_id" class="input-field mt-2">
              <option value="">Todas as Lojas</option>
              <option v-for="store in storeList" :key="store.id" :value="store.id">
                {{ store.name }}
              </option>
            </select>
          </div>

          <button @click="fetchPendingProducts" class="btn-gold mt-6">
            🔍 Buscar
          </button>
        </div>
      </div>

      <!-- Lista de Pendentes -->
      <div class="space-y-3">
        <div
          v-for="product in pendingProducts"
          :key="product.id"
          class="card-african p-4 flex justify-between items-start"
        >
          <div class="flex-1">
            <h3 class="text-bc-light font-semibold">{{ product.name }}</h3>
            <p class="text-bc-muted text-sm mt-1">
              Loja: <span class="text-bc-gold">{{ product.store.name }}</span>
            </p>
            <p class="text-bc-muted text-sm">
              Preço: <span class="text-bc-gold font-semibold">{{ formatCurrency(product.price) }}</span>
            </p>
            <p class="text-bc-muted text-xs mt-2">
              Adicionado: {{ formatDate(product.created_at) }}
            </p>
          </div>

          <div class="flex gap-2">
            <button
              @click="openApproveModal(product)"
              class="btn-gold px-4 py-2 text-sm"
            >
              ✓ Aprovar
            </button>
            <button
              @click="openRejectModal(product)"
              class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm"
            >
              ✗ Rejeitar
            </button>
          </div>
        </div>

        <p v-if="pendingProducts.length === 0" class="text-bc-muted text-center py-8">
          Nenhum produto pendente de aprovação
        </p>
      </div>

      <!-- Paginação -->
      <div v-if="pendingProducts.length > 0" class="flex justify-between items-center mt-4">
        <p class="text-bc-muted text-sm">
          Página {{ pendingPage }} de {{ pendingTotalPages }}
        </p>
        <div class="space-x-2">
          <button
            @click="pendingPage > 1 && fetchPendingProducts()"
            :disabled="pendingPage === 1"
            class="btn-secondary px-3 py-1 text-sm disabled:opacity-50"
          >
            ← Anterior
          </button>
          <button
            @click="pendingPage < pendingTotalPages && (pendingPage++, fetchPendingProducts())"
            :disabled="pendingPage === pendingTotalPages"
            class="btn-secondary px-3 py-1 text-sm disabled:opacity-50"
          >
            Próxima →
          </button>
        </div>
      </div>
    </div>

    <!-- POR LOJA -->
    <div v-if="activeTab === 'store'" class="space-y-4">
      <div class="card-african p-4">
        <label class="text-sm text-bc-muted">Selecionar Loja</label>
        <select v-model="selectedStoreId" class="input-field mt-2">
          <option value="">Selecione uma loja</option>
          <option v-for="store in storeList" :key="store.id" :value="store.id">
            {{ store.name }}
          </option>
        </select>

        <button
          v-if="selectedStoreId && storeProducts.length > 0"
          @click="openBulkApproveModal"
          class="btn-gold w-full mt-4"
        >
          ✓ Aprovar Todos os Produtos
        </button>
      </div>

      <!-- Produtos da Loja Selecionada -->
      <div v-if="selectedStoreId" class="space-y-3">
        <div v-for="product in storeProducts" :key="product.id" class="card-african p-4">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-bc-light font-semibold">{{ product.name }}</h3>
              <p class="text-bc-muted text-sm mt-1">
                Preço: {{ formatCurrency(product.price) }}
              </p>
              <p class="text-bc-muted text-xs mt-1">
                Status: 
                <span 
                  :class="product.is_visible_to_public ? 'text-green-400' : 'text-yellow-400'"
                  class="font-semibold"
                >
                  {{ product.is_visible_to_public ? 'Publicado' : 'Privado' }}
                </span>
              </p>
            </div>

            <button
              v-if="!product.is_visible_to_public"
              @click="openApproveModal(product)"
              class="btn-gold px-4 py-2 text-sm"
            >
              Aprovar
            </button>
            <button
              v-else
              @click="openRevokeModal(product)"
              class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm"
            >
              Revogar Acesso
            </button>
          </div>
        </div>

        <p v-if="storeProducts.length === 0" class="text-bc-muted text-center py-8">
          Nenhum produto para esta loja
        </p>
      </div>
    </div>

    <!-- Modal: Aprovar Produto -->
    <div v-if="productToApprove" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-bc-dark rounded-xl p-6 max-w-md w-full mx-4 space-y-4">
        <h2 class="text-xl font-bold text-bc-light">Aprovar Produto</h2>
        <p class="text-bc-muted">
          Deseja aprovar <span class="text-bc-gold font-semibold">{{ productToApprove.name }}</span> para exposição pública?
        </p>

        <textarea
          v-model="approveNotes"
          placeholder="Notas (opcional)..."
          class="input-field p-3"
          rows="2"
        ></textarea>

        <div class="flex gap-3">
          <button @click="confirmApprove" class="btn-gold flex-1">
            ✓ Aprovar
          </button>
          <button @click="productToApprove = null" class="btn-secondary flex-1">
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Rejeitar Produto -->
    <div v-if="productToReject" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-bc-dark rounded-xl p-6 max-w-md w-full mx-4 space-y-4">
        <h2 class="text-xl font-bold text-bc-light">Rejeitar Produto</h2>
        <p class="text-bc-muted">
          Deseja rejeitar <span class="text-bc-gold font-semibold">{{ productToReject.name }}</span>?
        </p>

        <textarea
          v-model="rejectReason"
          placeholder="Motivo da rejeição..."
          class="input-field p-3"
          rows="3"
        ></textarea>

        <div class="flex gap-3">
          <button @click="confirmReject" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded flex-1">
            ✗ Rejeitar
          </button>
          <button @click="productToReject = null" class="btn-secondary flex-1">
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Bulk Approve -->
    <div v-if="showBulkApproveModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-bc-dark rounded-xl p-6 max-w-md w-full mx-4 space-y-4">
        <h2 class="text-xl font-bold text-bc-light">Aprovar Todos os Produtos</h2>
        <p class="text-bc-muted">
          Deseja aprovar <span class="text-bc-gold font-semibold">{{ storeProducts.filter(p => !p.is_visible_to_public).length }}</span> produtos para exposição pública?
        </p>

        <div class="flex gap-3">
          <button @click="confirmBulkApprove" class="btn-gold flex-1">
            ✓ Confirmar
          </button>
          <button @click="showBulkApproveModal = false" class="btn-secondary flex-1">
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

const activeTab = ref('pending')
const pendingProducts = ref([])
const storeProducts = ref([])
const storeList = ref([])

const pendingPage = ref(1)
const pendingTotal = ref(0)
const pendingTotalPages = computed(() => Math.ceil(pendingTotal.value / 20))

const productToApprove = ref(null)
const productToReject = ref(null)
const productToRevoke = ref(null)
const approveNotes = ref('')
const rejectReason = ref('')
const showBulkApproveModal = ref(false)

const selectedStoreId = ref('')
const pendingCount = ref(0)
const toast = ref({ message: '', type: '' })

const filters = ref({
  search: '',
  store_id: ''
})

const fetchPendingProducts = async () => {
  try {
    const params = new URLSearchParams()
    if (filters.value.search) params.append('search', filters.value.search)
    if (filters.value.store_id) params.append('store_id', filters.value.store_id)
    params.append('page', pendingPage.value)

    const response = await axios.get(`/api/admin/products/pending-approvals?${params}`)
    pendingProducts.value = response.data.data
    pendingTotal.value = response.data.total
    pendingCount.value = pendingTotal.value
  } catch (error) {
    showToast('Erro ao carregar produtos pendentes', 'error')
  }
}

const fetchStoreProducts = async () => {
  if (!selectedStoreId.value) {
    storeProducts.value = []
    return
  }

  try {
    const response = await axios.get(`/api/admin/stores/${selectedStoreId.value}/products-list`)
    storeProducts.value = response.data.data
  } catch (error) {
    showToast('Erro ao carregar produtos da loja', 'error')
  }
}

const fetchStoreList = async () => {
  try {
    const response = await axios.get('/api/admin/stores-list')
    storeList.value = response.data.data
  } catch (error) {
    console.error('Erro ao carregar lojas:', error)
  }
}

const openApproveModal = (product) => {
  productToApprove.value = product
  approveNotes.value = ''
}

const confirmApprove = async () => {
  try {
    await axios.post(`/api/admin/products/${productToApprove.value.id}/approve`, {
      notes: approveNotes.value
    })
    showToast(`Produto '${productToApprove.value.name}' aprovado!`, 'success')
    productToApprove.value = null
    fetchPendingProducts()
    if (selectedStoreId.value) fetchStoreProducts()
  } catch (error) {
    showToast('Erro ao aprovar produto', 'error')
  }
}

const openRejectModal = (product) => {
  productToReject.value = product
  rejectReason.value = ''
}

const confirmReject = async () => {
  try {
    await axios.post(`/api/admin/products/${productToReject.value.id}/reject`, {
      reason: rejectReason.value
    })
    showToast(`Produto '${productToReject.value.name}' rejeitado`, 'success')
    productToReject.value = null
    fetchPendingProducts()
  } catch (error) {
    showToast('Erro ao rejeitar produto', 'error')
  }
}

const openBulkApproveModal = () => {
  showBulkApproveModal.value = true
}

const confirmBulkApprove = async () => {
  try {
    await axios.post(`/api/admin/stores/${selectedStoreId.value}/products/approve-all`, {
      approve_all: true
    })
    showToast('Todos os produtos foram aprovados!', 'success')
    showBulkApproveModal.value = false
    fetchStoreProducts()
    fetchPendingProducts()
  } catch (error) {
    showToast('Erro ao aprovar produtos em lote', 'error')
  }
}

const openRevokeModal = (product) => {
  productToRevoke.value = product
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('pt-PT', {
    style: 'currency',
    currency: 'MZN'
  }).format(value)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('pt-PT')
}

const showToast = (message, type = 'success') => {
  toast.value = { message, type }
  setTimeout(() => {
    toast.value = { message: '', type: '' }
  }, 3000)
}

const onSelectedStoreChange = () => {
  fetchStoreProducts()
}

// Watch selectedStoreId
import { watch } from 'vue'
watch(selectedStoreId, onSelectedStoreChange)

onMounted(() => {
  fetchPendingProducts()
  fetchStoreList()
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

.btn-secondary:hover {
  border-color: #f07820;
}
</style>
