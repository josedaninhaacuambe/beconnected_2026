<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      style="background:rgba(0,0,0,0.7)">
      <div class="bg-white w-full sm:max-w-xl rounded-t-2xl sm:rounded-2xl shadow-2xl flex flex-col"
        style="max-height:95vh;">

        <!-- Cabeçalho -->
        <div class="flex items-center justify-between px-5 pt-4 pb-3 border-b border-gray-100 flex-shrink-0">
          <h3 class="font-black text-gray-800 text-base">➕ {{ isEditing ? 'Editar Produto' : 'Novo Produto' }}</h3>
          <button @click="$emit('update:modelValue', false)" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>

        <!-- Corpo com scroll -->
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

          <!-- Imagem -->
          <div>
            <label class="text-xs font-semibold text-gray-500 mb-1 block">Foto do produto</label>
            <div class="flex items-center gap-3">
              <div class="w-20 h-20 rounded-xl overflow-hidden bg-gray-100 flex-shrink-0 flex items-center justify-center border border-gray-200">
                <AppImg :src="imagePreview || ''" type="product" class="w-full h-full object-cover" />
              </div>
              <div class="flex-1">
                <input type="file" accept="image/*" @change="onImageChange" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100 w-full" />
                <p class="text-xs text-gray-400 mt-1">Máx. 2MB. Se não carregar, é usada imagem automática.</p>
              </div>
            </div>
          </div>

          <!-- Nome + SKU -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-gray-500">Nome do produto *</label>
              <input v-model="form.name" type="text" placeholder="Nome do produto"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400" required />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">SKU / Código</label>
              <input v-model="form.sku" type="text" placeholder="SKU"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400" />
            </div>
          </div>

          <!-- Descrição -->
          <div>
            <label class="text-xs font-semibold text-gray-500">Descrição</label>
            <textarea v-model="form.description" placeholder="Descreve o produto..." rows="2"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400 resize-none"></textarea>
          </div>

          <!-- Categoria -->
          <div>
            <label class="text-xs font-semibold text-gray-500">Categoria *</label>
            <select v-model="form.product_category_id"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400 bg-white">
              <option value="">Selecionar categoria</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <!-- Preços -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-gray-500">Preço de venda (MZN) *</label>
              <input v-model.number="form.price" type="number" step="0.01" min="0" placeholder="0.00"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400" required />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Preço de custo (MZN)</label>
              <input v-model.number="form.cost_price" type="number" step="0.01" min="0" placeholder="0.00"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-gray-500">Preço original (desconto)</label>
              <input v-model.number="form.compare_price" type="number" step="0.01" min="0" placeholder="0.00"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400" />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Código de barras</label>
              <input v-model="form.barcode" type="text" placeholder="EAN / Código"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400" />
            </div>
          </div>

          <!-- Stock -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-gray-500">Stock inicial *</label>
              <input v-model.number="form.initial_stock" type="number" min="0" placeholder="0"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400" />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Stock mínimo (alerta)</label>
              <input v-model.number="form.minimum_stock" type="number" min="0" placeholder="5"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-orange-400" />
            </div>
          </div>

          <!-- Produto por peso -->
          <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-200">
            <button type="button" @click="form.is_weighable = !form.is_weighable"
              class="w-10 h-6 rounded-full transition flex items-center px-1 flex-shrink-0"
              :class="form.is_weighable ? 'bg-orange-400 justify-end' : 'bg-gray-200 justify-start'">
              <span class="w-4 h-4 bg-white rounded-full shadow"></span>
            </button>
            <div>
              <p class="text-sm font-semibold text-gray-700">⚖️ Vendido por peso</p>
              <p class="text-xs text-gray-400">Cereais, legumes, frutas, queijo...</p>
            </div>
          </div>

          <div v-if="form.is_weighable">
            <label class="text-xs font-semibold text-gray-500">Unidade de medida</label>
            <div class="flex gap-2 mt-1">
              <button v-for="u in ['g','kg','l','ml']" :key="u" type="button"
                @click="form.weight_unit = u"
                class="flex-1 py-1.5 rounded-lg border-2 text-xs font-bold transition"
                :class="form.weight_unit === u ? 'border-orange-400 text-orange-500' : 'border-gray-200 text-gray-500'">
                {{ u }}
              </button>
            </div>
          </div>

          <!-- Modos de venda -->
          <div class="p-3 rounded-xl border border-gray-200">
            <p class="text-sm font-semibold text-gray-700 mb-2">📦 Modos de venda</p>
            <div class="flex gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.selling_modes" type="checkbox" value="unit" class="rounded accent-orange-400">
                <span class="text-sm text-gray-700">Por unidade</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.selling_modes" type="checkbox" value="weight" class="rounded accent-orange-400">
                <span class="text-sm text-gray-700">Por peso</span>
              </label>
            </div>
          </div>

          <!-- Disponibilidade -->
          <div class="p-3 rounded-xl border border-gray-200">
            <p class="text-sm font-semibold text-gray-700 mb-2">🏪 Disponibilidade</p>
            <select v-model="form.availability"
              class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm">
              <option value="both">Ambos (Loja Virtual e POS)</option>
              <option value="virtual_store">Apenas Loja Virtual</option>
              <option value="pos">Apenas POS</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Define onde o produto estará disponível.</p>
          </div>

          <!-- Erro -->
          <div v-if="errorMsg" class="text-red-500 text-sm bg-red-50 rounded-xl px-3 py-2">{{ errorMsg }}</div>

        </div>

        <!-- Botões -->
        <div class="flex gap-3 px-5 py-3 border-t border-gray-100 flex-shrink-0">
          <button type="button" @click="$emit('update:modelValue', false)"
            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
            Cancelar
          </button>
          <button type="button" @click="save"
            :disabled="saving || !form.name || !form.price || !form.product_category_id"
            class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm disabled:opacity-40 transition"
            style="background:#F07820;">
            {{ saving ? 'A guardar...' : (isEditing ? '💾 Atualizar' : '✅ Criar Produto') }}
          </button>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  modelValue: Boolean,          // v-model: visibilidade do modal
  product: { type: Object, default: null }, // produto a editar (null = criar)
})

const emit = defineEmits(['update:modelValue', 'saved'])

const isEditing = computed(() => !!props.product)
const saving    = ref(false)
const errorMsg  = ref('')
const categories = ref([])
const imageFile  = ref(null)
const imagePreview = ref('')

const form = reactive({
  name: '', sku: '', description: '', barcode: '',
  price: '', cost_price: '', compare_price: '',
  initial_stock: 0, minimum_stock: 5,
  product_category_id: '',
  is_weighable: false, weight_unit: 'kg',
  selling_modes: ['unit'],
  availability: 'both',
})

// Carregar categorias uma vez
onMounted(async () => {
  try {
    const { data } = await axios.get('/product-categories')
    categories.value = data
  } catch {}
})

// Quando o produto muda (modo edição), preencher o formulário
watch(() => props.product, (p) => {
  if (p) {
    Object.assign(form, {
      name:                p.name ?? '',
      sku:                 p.sku ?? '',
      description:         p.description ?? '',
      barcode:             p.barcode ?? '',
      price:               p.price ?? '',
      cost_price:          p.cost_price ?? '',
      compare_price:       p.compare_price ?? '',
      initial_stock:       p.stock?.quantity ?? 0,
      minimum_stock:       p.stock?.minimum_stock ?? 5,
      product_category_id: p.product_category_id ?? '',
      is_weighable:        p.is_weighable ?? false,
      weight_unit:         p.weight_unit ?? 'kg',
      selling_modes:       p.selling_modes ?? ['unit'],
      availability:        p.availability ?? 'both',
    })
    if (p.image) {
      imagePreview.value = p.image.startsWith('http') ? p.image : `/storage/${p.image}`
    } else if (p.images?.length) {
      const img = p.images[0]
      imagePreview.value = img.startsWith('http') ? img : `/storage/${img}`
    }
  } else {
    resetForm()
  }
}, { immediate: true })

// Limpar quando fechar
watch(() => props.modelValue, (v) => {
  if (!v) resetForm()
})

function resetForm() {
  Object.assign(form, {
    name: '', sku: '', description: '', barcode: '',
    price: '', cost_price: '', compare_price: '',
    initial_stock: 0, minimum_stock: 5,
    product_category_id: '',
    is_weighable: false, weight_unit: 'kg',
    selling_modes: ['unit'],
    availability: 'both',
  })
  imageFile.value = null
  imagePreview.value = ''
  errorMsg.value = ''
}

function onImageChange(e) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) {
    errorMsg.value = 'Imagem muito grande. Máximo 2MB.'
    e.target.value = ''
    return
  }
  imageFile.value = file
  imagePreview.value = URL.createObjectURL(file)
}

async function save() {
  if (!form.name || !form.price || !form.product_category_id) {
    errorMsg.value = 'Nome, preço e categoria são obrigatórios.'
    return
  }
  if (!form.selling_modes.length) {
    errorMsg.value = 'Seleciona pelo menos um modo de venda.'
    return
  }

  saving.value = true
  errorMsg.value = ''

  try {
    const fd = new FormData()
    fd.append('name',                form.name)
    fd.append('price',               form.price)
    fd.append('product_category_id', form.product_category_id)
    fd.append('availability',        form.availability)
    fd.append('initial_stock',       form.initial_stock)
    fd.append('minimum_stock',       form.minimum_stock)
    fd.append('is_weighable',        form.is_weighable ? '1' : '0')
    fd.append('weight_unit',         form.weight_unit)
    form.selling_modes.forEach(m => fd.append('selling_modes[]', m))

    if (form.sku)          fd.append('sku',          form.sku)
    if (form.description)  fd.append('description',  form.description)
    if (form.barcode)      fd.append('barcode',      form.barcode)
    if (form.cost_price)   fd.append('cost_price',   form.cost_price)
    if (form.compare_price) fd.append('compare_price', form.compare_price)
    if (imageFile.value)   fd.append('images[]',     imageFile.value)

    let response
    if (isEditing.value && props.product?.id) {
      fd.append('_method', 'PUT')
      response = await axios.post(`/store/products/${props.product.id}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    } else {
      response = await axios.post('/store/products', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }

    emit('saved', response.data)
    emit('update:modelValue', false)
  } catch (e) {
    const errs = e.response?.data?.errors
    errorMsg.value = errs
      ? Object.values(errs).flat().join(' ')
      : (e.response?.data?.message || 'Erro ao guardar produto.')
  } finally {
    saving.value = false
  }
}
</script>
