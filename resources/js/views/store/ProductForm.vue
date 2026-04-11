<template>
  <div class="p-6 max-w-2xl mx-auto">
    <h1 class="text-xl font-bold text-bc-light mb-6">{{ isEditing ? 'Editar Produto' : 'Novo Produto' }}</h1>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Imagens do Produto -->
      <div class="card-african p-5">
        <h2 class="text-bc-gold font-semibold mb-3">Imagens do Produto</h2>

        <div v-if="imagePreviews.length > 0" class="flex flex-wrap gap-2 mb-3">
          <div v-for="(img, i) in imagePreviews" :key="i" class="relative w-20 h-20">
            <AppImg :src="img" class="w-full h-full object-cover rounded-xl border border-bc-gold/20" />
            <button type="button" @click="removeImage(i)"
              class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">✕</button>
          </div>
        </div>
        <div v-else-if="autoImageUrl" class="mb-3 flex items-start gap-3">
          <div class="relative w-20 h-20 flex-shrink-0">
            <AppImg :src="autoImageUrl" class="w-full h-full object-cover rounded-xl border-2 border-bc-gold/40" />
            <span class="absolute bottom-0 left-0 right-0 bg-bc-gold/90 text-bc-dark text-[9px] text-center py-0.5 rounded-b-xl font-bold">AUTO</span>
          </div>
          <p class="text-bc-muted text-xs mt-1">Imagem automática encontrada. Adiciona a tua própria para substituir.</p>
        </div>

        <div
          class="border-2 border-dashed border-bc-gold/30 hover:border-bc-gold/60 rounded-xl p-6 text-center cursor-pointer transition"
          @click="$refs.imgInput.click()"
          @dragover.prevent
          @drop.prevent="onDrop"
        >
          <span class="text-3xl mb-1 block">📷</span>
          <p class="text-bc-muted text-sm">Clica ou arrasta imagens aqui</p>
          <p class="text-bc-muted text-xs mt-1">JPG, PNG — máx. 2MB por imagem</p>
        </div>
        <input ref="imgInput" type="file" accept="image/*" multiple class="hidden" @change="onImagesChange" />

        <div class="flex items-center gap-3 mt-3">
          <button
            type="button"
            @click="fetchAutoImage"
            :disabled="!form.name || fetchingImage"
            class="flex items-center gap-2 px-4 py-2 rounded-xl border border-bc-gold/40 text-bc-gold text-sm hover:bg-bc-gold/10 transition disabled:opacity-40"
          >
            <svg v-if="fetchingImage" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span v-else>🔍</span>
            {{ fetchingImage ? 'A procurar...' : 'Buscar Imagem Automaticamente' }}
          </button>
          <p class="text-bc-muted text-xs">Pesquisa por nome e marca do produto</p>
        </div>
      </div>

      <!-- Informações Básicas -->
      <div class="card-african p-5 space-y-4">
        <h2 class="text-bc-gold font-semibold">Informações do Produto</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input v-model="form.name" type="text" placeholder="Nome do produto *" class="input-african" required />
          <input v-model="form.sku" type="text" placeholder="SKU / Referência" class="input-african" />
        </div>
        <textarea v-model="form.description" placeholder="Descrição" rows="3" class="input-african resize-none"></textarea>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-bc-muted text-xs mb-1 block">Preço (MZN) *</label>
            <input v-model="form.price" type="number" step="0.01" min="0" placeholder="0.00" class="input-african" required />
          </div>
          <div>
            <label class="text-bc-muted text-xs mb-1 block">Preço original (para mostrar desconto)</label>
            <input v-model="form.compare_price" type="number" step="0.01" min="0" placeholder="0.00" class="input-african" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-bc-muted text-xs mb-1 block">Stock inicial *</label>
            <input v-model="form.initial_stock" type="number" min="0" placeholder="0" class="input-african" required />
          </div>
          <div>
            <label class="text-bc-muted text-xs mb-1 block">Stock mínimo (alerta)</label>
            <input v-model="form.minimum_stock" type="number" min="0" placeholder="5" class="input-african" />
          </div>
        </div>
      </div>

      <!-- Categorização -->
      <div class="card-african p-5 space-y-3">
        <h2 class="text-bc-gold font-semibold">Categorização</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="text-bc-muted text-xs mb-1 block">Categoria global *</label>
            <select v-model="form.product_category_id" class="select-african" required>
              <option value="">Seleccionar categoria</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-bc-muted text-xs mb-1 block">
              Secção da Loja
              <RouterLink to="/loja/categorias" class="text-bc-gold text-xs hover:underline ml-1">+ Gerir secções</RouterLink>
            </label>
            <select v-model="form.store_section_id" class="select-african">
              <option value="">Sem secção</option>
              <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.icon }} {{ s.name }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="text-bc-muted text-xs mb-1 block">Marca</label>
            <select v-model="form.brand_id" class="select-african">
              <option value="">Sem marca</option>
              <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-bc-muted text-xs mb-1 block">Modelo</label>
            <input v-model="form.model" type="text" placeholder="Ex: Galaxy A55" class="input-african" />
          </div>
        </div>
        <input v-model="form.barcode" type="text" placeholder="Código de barras / EAN" class="input-african" />
      </div>

      <!-- Estado e visibilidade -->
      <div class="card-african p-4 space-y-3">
        <!-- Activo/Inactivo -->
        <label class="flex items-center gap-3 cursor-pointer">
          <div class="relative" @click="form.is_active = !form.is_active">
            <div :class="['w-10 h-6 rounded-full transition', form.is_active ? 'bg-bc-gold' : 'bg-bc-surface-2']"></div>
            <div :class="['absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform', form.is_active ? 'translate-x-5' : 'translate-x-1']"></div>
          </div>
          <div>
            <span class="text-bc-light text-sm font-medium">{{ form.is_active ? 'Produto activo' : 'Produto inactivo' }}</span>
            <p class="text-bc-muted text-xs">{{ form.is_active ? 'Visível na loja' : 'Oculto aos clientes' }}</p>
          </div>
        </label>

        <!-- Availability -->
        <label class="border-t border-bc-gold/10 pt-3">
          <span class="text-bc-light text-sm font-medium flex items-center gap-1.5 mb-2">
            🏪 Disponibilidade do Produto
          </span>
          <select v-model="form.availability" class="w-full bg-bc-surface-2 border border-bc-gold/20 rounded-lg px-3 py-2 text-bc-light text-sm">
            <option value="both">Ambos (Loja Virtual e POS)</option>
            <option value="virtual_store">Apenas Loja Virtual</option>
            <option value="pos">Apenas POS</option>
          </select>
          <p class="text-bc-muted text-xs mt-1">
            Define onde o produto estará disponível para venda.
          </p>
        </label>
      </div>

      <p v-if="error" class="text-red-400 text-sm bg-red-900/20 rounded-xl p-3">{{ error }}</p>
      <p v-if="success" class="text-green-400 text-sm bg-green-900/20 rounded-xl p-3">✓ {{ success }}</p>

      <div class="flex gap-3">
        <RouterLink to="/loja/produtos" class="btn-ghost flex-1 py-3 text-center text-sm">Cancelar</RouterLink>
        <button type="submit" :disabled="saving" class="btn-gold flex-1 py-3 text-sm">
          {{ saving ? 'A guardar...' : (isEditing ? 'Actualizar Produto' : 'Criar Produto') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const isEditing = computed(() => !!route.params.slug)
let editingProductId = null // ID real para o endpoint PUT

const saving = ref(false)
const error = ref('')
const success = ref('')
const categories = ref([])
const brands = ref([])
const sections = ref([])
const imageFiles = ref([])
const imagePreviews = ref([])
const existingImages = ref([]) // URLs already stored (edit mode)
const autoImageUrl = ref('')
const fetchingImage = ref(false)

const form = reactive({
  name: '', sku: '', description: '',
  price: '', compare_price: '',
  initial_stock: 0, minimum_stock: 5,
  product_category_id: '', store_section_id: '',
  brand_id: '', model: '', barcode: '',
  is_active: true,
  availability: 'both',
  selling_modes: ['unit'],
})

function onImagesChange(e) {
  Array.from(e.target.files).forEach(addImageFile)
}

function onDrop(e) {
  Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/')).forEach(addImageFile)
}

function addImageFile(file) {
  imageFiles.value.push(file)
  imagePreviews.value.push(URL.createObjectURL(file))
}

function removeImage(i) {
  // Remove from new files or existing
  if (i < existingImages.value.length) {
    existingImages.value.splice(i, 1)
  } else {
    const fileIdx = i - existingImages.value.length
    imageFiles.value.splice(fileIdx, 1)
  }
  imagePreviews.value.splice(i, 1)
}

async function fetchAutoImage() {
  if (!form.name) return
  fetchingImage.value = true
  try {
    const { data } = await axios.post('/store/products/fetch-image', {
      name: form.name,
      brand_id: form.brand_id || null,
    })
    autoImageUrl.value = data.url
  } catch {
    // silently fail
  } finally {
    fetchingImage.value = false
  }
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    const fd = new FormData()
    const fields = ['name', 'sku', 'description', 'price', 'compare_price', 'initial_stock', 'minimum_stock',
      'product_category_id', 'store_section_id', 'brand_id', 'model', 'barcode']
    fields.forEach(k => {
      if (form[k] !== null && form[k] !== undefined && form[k] !== '') fd.append(k, form[k])
    })
    fd.append('is_active', form.is_active ? '1' : '0')
    fd.append('availability', form.availability)
    form.selling_modes.forEach(mode => fd.append('selling_modes[]', mode))
    imageFiles.value.forEach(f => fd.append('images[]', f))

    if (isEditing.value) {
      fd.append('_method', 'PUT')
      await axios.post(`/store/products/${editingProductId}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      success.value = 'Produto actualizado com sucesso!'
    } else {
      await axios.post('/store/products', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      router.push('/loja/produtos')
    }
  } catch (e) {
    error.value = e.response?.data?.errors
      ? Object.values(e.response.data.errors).flat().join(' ')
      : e.response?.data?.message || 'Erro ao guardar produto.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  const [catsRes, brandsRes, sectionsRes] = await Promise.all([
    axios.get('/product-categories'),
    axios.get('/brands'),
    axios.get('/store/sections').catch(() => ({ data: [] })),
  ])
  categories.value = catsRes.data
  brands.value = brandsRes.data
  sections.value = sectionsRes.data

  if (isEditing.value) {
    const { data } = await axios.get('/store/products', { params: { per_page: 200 } })
    const p = (data.data ?? data).find(x => x.slug === route.params.slug)
    if (p) {
      editingProductId = p.id
      Object.assign(form, {
        name: p.name ?? '', sku: p.sku ?? '', description: p.description ?? '',
        price: p.price ?? '', compare_price: p.compare_price ?? '',
        initial_stock: p.stock?.quantity ?? 0, minimum_stock: p.stock?.minimum_stock ?? 5,
        product_category_id: p.product_category_id ?? '',
        store_section_id: p.store_section_id ?? '',
        brand_id: p.brand_id ?? '', model: p.model ?? '', barcode: p.barcode ?? '',
        is_active: p.is_active ?? true,
        availability:  p.availability  ?? 'both',
        selling_modes: p.selling_modes ?? ['unit'],
      })
      if (p.images?.length) {
        existingImages.value = p.images
        imagePreviews.value = p.images.map(img => img.startsWith('http') ? img : `/storage/${img}`)
      }
    }
  }
})
</script>
