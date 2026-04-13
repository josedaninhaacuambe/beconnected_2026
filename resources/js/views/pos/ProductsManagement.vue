<template>
  <div class="flex h-full flex-col bg-gray-50 overflow-hidden">
    <!-- Header com botão adicionar -->
    <div class="flex-shrink-0 px-4 py-3 bg-white border-b border-gray-200 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <h2 class="font-bold text-gray-800">📦 Produtos</h2>
        
        <!-- Filtro de categoria -->
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-600">Categoria:</label>
          <select v-model="selectedCategory" @change="currentPage = 1" 
                  class="px-3 py-1 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-bc-gold">
            <option value="all">📦 Todos</option>
            <option v-for="cat in categoriesWithProducts" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>
      </div>
      
      <button v-if="canManageProducts" @click="openAddForm"
        class="flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-bold transition"
        style="background:#F07820; color:white;">
        ➕ Novo Produto
      </button>
    </div>

    <!-- Grid de produtos com paginação -->
    <div class="flex-1 overflow-y-auto p-4 flex flex-col">
      <div v-if="loadingProducts" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div v-for="i in 6" :key="i" class="bg-white rounded-lg h-48 animate-pulse"></div>
      </div>

      <div v-else-if="!filteredProducts.length" class="flex flex-col items-center justify-center flex-1 text-gray-400">
        <span class="text-5xl mb-3">📦</span>
        <p class="text-sm font-semibold" v-if="selectedCategory === 'all'">Nenhum produto disponível em POS</p>
        <p class="text-sm font-semibold" v-else>Nenhum produto nesta categoria</p>
        <p class="text-xs text-gray-400 mt-1 mb-4" v-if="selectedCategory === 'all'">Produtos disponíveis apenas na Loja Virtual não aparecem aqui</p>
        <p class="text-xs text-gray-400 mt-1 mb-4" v-else>Tente selecionar "📦 Todos" para ver todos os produtos</p>
        <button v-if="canManageProducts" @click="openAddForm" class="px-4 py-2 rounded-lg text-sm font-bold transition" style="background:#F07820; color:white;">
          ➕ Adicionar Primeiro Produto
        </button>
      </div>

      <div v-else class="flex flex-col flex-1">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 flex-1">
          <div v-for="p in paginatedProducts" :key="p.id" class="bg-white rounded-lg overflow-hidden shadow hover:shadow-lg transition flex flex-col">
            <!-- Imagem -->
            <div class="w-full h-32 bg-gray-100 overflow-hidden flex items-center justify-center">
              <AppImg :src="p.images?.[0] ? (p.images[0].startsWith('http') ? p.images[0] : '/storage/' + p.images[0]) : ''" type="product" class="w-full h-full object-cover" />
            </div>

            <!-- Info -->
            <div class="p-3 space-y-2 flex-1 flex flex-col">
              <div>
                <p class="font-bold text-gray-800 text-sm line-clamp-1">{{ p.name }}</p>
                <p class="text-xs text-gray-500">SKU: {{ p.sku || 'N/A' }}</p>
              </div>

              <div class="flex justify-between items-center text-sm">
                <div>
                  <p class="text-xs text-gray-500">Preço</p>
                  <p class="font-bold text-gray-800">{{ fmt(p.price) }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Stock</p>
                  <div v-if="editingStock[p.id]" class="flex items-center gap-1">
                    <input v-model.number="stockEdits[p.id]" type="number" min="0" 
                           class="w-16 px-1 py-0.5 text-xs border border-gray-300 rounded" 
                           @keyup.enter="saveStock(p.id)" />
                    <button @click="saveStock(p.id)" class="text-green-600 text-xs hover:text-green-800">💾</button>
                    <button @click="cancelStockEdit(p.id)" class="text-gray-600 text-xs hover:text-gray-800">✕</button>
                  </div>
                  <div v-else class="flex items-center gap-1">
                    <p class="font-bold" :class="(p.stock?.quantity ?? 0) > 0 ? 'text-green-600' : 'text-red-600'">
                      {{ p.stock?.quantity ?? 0 }}
                    </p>
                    <button v-if="canManageStock" @click="startStockEdit(p)" 
                            class="text-blue-600 text-xs hover:text-blue-800 opacity-60 hover:opacity-100">
                      ✏️
                    </button>
                  </div>
                </div>
              </div>

              <!-- Ações -->
              <div v-if="canManageProducts" class="flex gap-2 text-xs pt-2 border-t border-gray-100 mt-auto">
                <button @click="editProduct(p)" class="flex-1 px-2 py-1 rounded bg-blue-100 text-blue-700 font-semibold hover:bg-blue-200 transition">
                  ✏️ Editar
                </button>
                <button @click="deleteProduct(p.id)" class="flex-1 px-2 py-1 rounded bg-red-100 text-red-700 font-semibold hover:bg-red-200 transition">
                  🗑️ Remover
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Paginação -->
        <div v-if="totalPages > 1" class="mt-4 flex items-center justify-center gap-2">
          <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
            class="px-3 py-1 rounded border border-gray-200 text-xs font-semibold disabled:opacity-40 hover:bg-gray-50">
            ← Anterior
          </button>
          <span class="text-xs text-gray-600 font-semibold">
            {{ currentPage }} / {{ totalPages }}
          </span>
          <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages"
            class="px-3 py-1 rounded border border-gray-200 text-xs font-semibold disabled:opacity-40 hover:bg-gray-50">
            Próxima →
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Adicionar/Editar Produto -->
    <Teleport to="body">
      <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 px-4 py-3 border-b border-gray-100 bg-white flex items-center justify-between">
            <h3 class="font-bold text-gray-800">{{ editingId ? '✏️ Editar Produto' : '➕ Novo Produto' }}</h3>
            <button @click="showForm = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
          </div>

          <div class="p-4 space-y-4">
            <!-- Upload Foto -->
            <div>
              <label class="text-xs font-bold text-gray-600">Foto do produto</label>
              <div class="mt-1 border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-bc-gold transition cursor-pointer">
                <input ref="imageInput" @change="onImageSelected" type="file" accept="image/*" class="hidden" />
                <button @click="$refs.imageInput.click()" class="text-xs text-gray-500">
                  <span v-if="!imagePreview" class="block">📷 Nenhuma imagem selecionada<br><span class="text-[10px] text-gray-400">Máx. 2MB</span></span>
                  <img v-else :src="imagePreview" class="w-20 h-20 object-cover mx-auto rounded" />
                </button>
              </div>
              <p class="text-[10px] text-gray-400 mt-1">Se não carregar, é usada imagem automática.</p>
            </div>

            <!-- Nome -->
            <div>
              <label class="text-xs font-bold text-gray-600">Nome do produto *</label>
              <input v-model="form.name" type="text" placeholder="Nome do produto" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-1" />
            </div>

            <!-- SKU e Código de Barras -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-bold text-gray-600">SKU / Código</label>
                <input v-model="form.sku" type="text" placeholder="SKU" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-1" />
              </div>
              <div>
                <label class="text-xs font-bold text-gray-600">Código de barras</label>
                <input v-model="form.barcode" type="text" placeholder="EAN / Código" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-1" />
              </div>
            </div>

            <!-- Descrição -->
            <div>
              <label class="text-xs font-bold text-gray-600">Descrição</label>
              <textarea v-model="form.description" placeholder="Descreve o produto..." rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-1"></textarea>
            </div>

            <!-- Categoria -->
            <div>
              <label class="text-xs font-bold text-gray-600">Categoria *</label>
              <select v-model.number="form.category_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-1">
                <option :value="null">-- Selecionar categoria --</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>

            <!-- Preços -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-bold text-gray-600">Preço de venda (MZN) *</label>
                <input v-model.number="form.price" type="number" min="0" step="0.01" placeholder="0.00" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-1" />
              </div>
              <div>
                <label class="text-xs font-bold text-gray-600">Preço de custo (MZN)</label>
                <input v-model.number="form.cost_price" type="number" min="0" step="0.01" placeholder="0.00" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-1" />
              </div>
            </div>

            <!-- Stock -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-bold text-gray-600">Stock inicial *</label>
                <input v-model.number="form.stock_quantity" type="number" min="0" placeholder="0" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-1" />
              </div>
              <div>
                <label class="text-xs font-bold text-gray-600">Stock mínimo (alerta)</label>
                <input v-model.number="form.stock_min" type="number" min="0" placeholder="5" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-1" />
              </div>
            </div>

            <!-- Vendido por peso -->
            <div class="border-t border-gray-100 pt-3">
              <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_weighable" type="checkbox" class="rounded" />
                <span class="font-semibold text-gray-800">⚖️ Vendido por peso</span>
              </label>
              <p class="text-[10px] text-gray-400 ml-6">Cereais, legumes, frutas, queijo...</p>
              <select v-if="form.is_weighable" v-model="form.weight_unit" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold mt-2">
                <option value="kg">kg</option>
                <option value="g">g</option>
                <option value="l">l</option>
                <option value="ml">ml</option>
              </select>
            </div>

            <!-- Modos de venda -->
            <div class="border-t border-gray-100 pt-3">
              <label class="text-xs font-bold text-gray-600 block mb-2">📦 Modos de venda</label>
              <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm">
                  <input type="checkbox" :checked="form.sales_modes?.includes('unit')" @change="toggleSaleMode('unit')" class="rounded" />
                  <span>Por unidade</span>
                </label>
                <label class="flex items-center gap-2 text-sm">
                  <input type="checkbox" :checked="form.sales_modes?.includes('weight')" @change="toggleSaleMode('weight')" class="rounded" />
                  <span>Por peso</span>
                </label>
              </div>
            </div>

            <!-- Disponibilidade -->
            <div class="border-t border-gray-100 pt-3">
              <label class="text-xs font-bold text-gray-600 block mb-2">🏪 Disponibilidade</label>
              <p class="text-[10px] text-gray-400 mb-2">Define onde o produto estará disponível.</p>
              <select v-model="form.availability" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-bc-gold">
                <option value="both">Ambos (Loja Virtual e POS)</option>
                <option value="online">Apenas Loja Virtual</option>
                <option value="pos">Apenas POS</option>
              </select>
            </div>
          </div>

          <div class="px-4 py-3 border-t border-gray-100 flex gap-2 bg-gray-50 sticky bottom-0">
            <button @click="showForm = false" class="flex-1 px-4 py-2 rounded-lg text-sm font-bold text-gray-700 border border-gray-200 hover:bg-gray-50 transition">
              Cancelar
            </button>
            <button @click="saveProduct" :disabled="saving || !form.name || !form.price" class="flex-1 px-4 py-2 rounded-lg text-sm font-bold text-white transition disabled:opacity-50"
              style="background:#F07820;">
              {{ saving ? '⏳ Salvando...' : '✅ Criar Produto' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import AppImg from '@/components/AppImg.vue'

const auth = useAuthStore()

const products = ref([])
const categories = ref([])
const loadingProducts = ref(true)
const showForm = ref(false)
const saving = ref(false)
const editingId = ref(null)
const currentPage = ref(1)
const perPage = 6
const imagePreview = ref(null)
const imageInput = ref(null)
const imageFile = ref(null)

// Edição de stock
const editingStock = ref({})
const stockEdits = ref({})

// Filtro de categoria
const selectedCategory = ref('all')

const form = ref({
  name: '',
  sku: '',
  barcode: '',
  description: '',
  price: 0,
  cost_price: 0,
  category_id: null,
  stock_quantity: 0,
  stock_min: 5,
  is_weighable: false,
  weight_unit: 'kg',
  sales_modes: ['unit'],
  availability: 'both',
})

const canManageProducts = computed(() => {
  const role = auth.posRole
  return role === 'owner' || role === 'manager'
})

const canManageStock = computed(() => {
  const role = auth.posRole
  return role === 'owner' || role === 'manager'
})

// Categorias que têm produtos
function flattenCategories(items) {
  const result = []
  for (const item of items) {
    result.push(item)
    if (item.children?.length) {
      result.push(...flattenCategories(item.children))
    }
  }
  return result
}

const allCategories = computed(() => flattenCategories(categories.value || []))

const categoriesWithProducts = computed(() => {
  const categoryIds = new Set(products.value
    .map(p => p.category_id ?? p.product_category_id)
    .filter(id => id))
  return allCategories.value.filter(cat => categoryIds.has(cat.id))
})

// Produtos filtrados por categoria
const filteredProducts = computed(() => {
  if (selectedCategory.value === 'all') {
    return products.value
  }
  return products.value.filter(p => {
    const productCategory = p.category_id ?? p.product_category_id
    return productCategory && productCategory == selectedCategory.value
  })
})

const totalPages = computed(() => Math.ceil(filteredProducts.value.length / perPage))

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * perPage
  const end = start + perPage
  return filteredProducts.value.slice(start, end)
})

const _fmt = new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' })
function fmt(v) { return _fmt.format(v ?? 0) }

function onImageSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  
  if (file.size > 2 * 1024 * 1024) {
    alert('Imagem muito grande (máx 2MB)')
    return
  }
  
  imageFile.value = file
  const reader = new FileReader()
  reader.onload = (evt) => {
    imagePreview.value = evt.target?.result
  }
  reader.readAsDataURL(file)
}

function toggleSaleMode(mode) {
  const modes = form.value.sales_modes || []
  if (modes.includes(mode)) {
    form.value.sales_modes = modes.filter(m => m !== mode)
  } else {
    form.value.sales_modes = [...modes, mode]
  }
}

async function loadProducts() {
  try {
    loadingProducts.value = true
    console.log('🔍 Loading products for POS...')
    console.log('👤 User:', auth.user)
    console.log('🏪 Store ID:', auth.user?.store?.id || auth.user?.pos_employee?.store?.id)
    console.log('🏪 Store data:', auth.user?.store || auth.user?.pos_employee?.store)
    
    // Carrega os mesmos produtos que aparecem no terminal de vendas
    const { data } = await axios.get('/pos/products')
    console.log('📦 Products response:', data)
    
    products.value = data || []
    currentPage.value = 1 // Reset para primeira página
  } catch (e) {
    console.error('❌ Erro ao carregar produtos:', e)
    console.error('🔍 Error details:', e.response?.data)
  } finally {
    loadingProducts.value = false
  }
}

async function loadCategories() {
  try {
    const { data } = await axios.get('/api/product-categories')
    categories.value = data?.data || data || []
  } catch (e) {
    console.error('Erro ao carregar categorias:', e)
  }
}

function openAddForm() {
  editingId.value = null
  imageFile.value = null
  imagePreview.value = null
  form.value = {
    name: '',
    sku: '',
    barcode: '',
    description: '',
    price: 0,
    cost_price: 0,
    category_id: null,
    stock_quantity: 0,
    stock_min: 5,
    is_weighable: false,
    weight_unit: 'kg',
    sales_modes: ['unit'],
    availability: 'both', // Por padrão, produto aparece em POS e Loja Virtual
  }
  showForm.value = true
}

function editProduct(p) {
  editingId.value = p.id
  imageFile.value = null
  imagePreview.value = p.images?.[0] ? (p.images[0].startsWith('http') ? p.images[0] : '/storage/' + p.images[0]) : null
  form.value = {
    name: p.name,
    sku: p.sku || '',
    barcode: p.barcode || '',
    description: p.description || '',
    price: p.price,
    cost_price: p.cost_price || 0,
    category_id: p.category_id,
    stock_quantity: p.stock?.quantity ?? 0,
    stock_min: p.stock_min ?? 5,
    is_weighable: p.is_weighable || false,
    weight_unit: p.weight_unit ?? 'kg',
    sales_modes: p.sales_modes || ['unit'],
    availability: p.availability || 'both',
  }
  showForm.value = true
}

async function saveProduct() {
  if (!form.value.name || !form.value.price) return
  
  saving.value = true
  try {
    const formData = new FormData()
    formData.append('name', form.value.name)
    formData.append('sku', form.value.sku)
    formData.append('barcode', form.value.barcode)
    formData.append('description', form.value.description)
    formData.append('price', form.value.price)
    formData.append('cost_price', form.value.cost_price)
    formData.append('category_id', form.value.category_id)
    formData.append('stock_quantity', form.value.stock_quantity)
    formData.append('stock_min', form.value.stock_min)
    formData.append('is_weighable', form.value.is_weighable ? 1 : 0)
    formData.append('weight_unit', form.value.weight_unit)
    formData.append('sales_modes', JSON.stringify(form.value.sales_modes))
    formData.append('availability', form.value.availability)
    
    if (imageFile.value) {
      formData.append('image', imageFile.value)
    }
    
    if (editingId.value) {
      formData.append('_method', 'PUT')
      await axios.post(`/api/store/products/${editingId.value}`, formData)
    } else {
      await axios.post('/api/store/products', formData)
    }
    showForm.value = false
    await loadProducts()
  } catch (e) {
    alert('Erro ao salvar: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

async function deleteProduct(id) {
  if (!confirm('Tem certeza que deseja remover este produto?')) return
  
  try {
    await axios.delete(`/api/store/products/${id}`)
    await loadProducts()
  } catch (e) {
    alert('Erro ao remover: ' + (e.response?.data?.message || e.message))
  }
}

// Funções de edição de stock
function startStockEdit(product) {
  editingStock.value[product.id] = true
  stockEdits.value[product.id] = product.stock?.quantity ?? 0
}

function cancelStockEdit(productId) {
  editingStock.value[productId] = false
  delete stockEdits.value[productId]
}

async function saveStock(productId) {
  const newQuantity = stockEdits.value[productId]
  if (newQuantity === undefined || newQuantity < 0) return
  
  try {
    await axios.post('/pos/stock/movement', {
      product_id: productId,
      type: 'adjustment',
      quantity: newQuantity,
      reason: 'Ajuste manual via gestão de produtos'
    })
    
    // Atualizar o produto na lista local
    const product = products.value.find(p => p.id === productId)
    if (product) {
      if (!product.stock) product.stock = {}
      product.stock.quantity = newQuantity
    }
    
    editingStock.value[productId] = false
    delete stockEdits.value[productId]
    
    alert('Stock atualizado com sucesso!')
  } catch (e) {
    alert('Erro ao atualizar stock: ' + (e.response?.data?.message || e.message))
  }
}

onMounted(async () => {
  console.log('🚀 ProductsManagement mounted')
  console.log('👤 Auth user:', auth.user)
  console.log('🏪 User store:', auth.user?.store)
  console.log('🔑 Token exists:', !!localStorage.getItem('bc_token'))
  
  await loadCategories()
  await loadProducts()
})
</script>

<style scoped>
/* Responsividade */
@media (max-width: 640px) {
  .grid {
    grid-template-columns: 1fr !important;
  }
}
</style>
