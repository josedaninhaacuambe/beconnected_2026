<template>
  <div class="p-6 pb-mobile">
    <h1 class="text-2xl font-bold text-bc-gold mb-2">Importar Stock</h1>
    <p class="text-bc-muted mb-6">Importa produtos e stock do teu sistema actual — Excel, CSV, API ou JSON.</p>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 flex-wrap">
      <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
        :class="['px-4 py-2 rounded-xl text-sm font-medium transition', activeTab === tab.id ? 'bg-bc-gold text-bc-dark' : 'border border-bc-gold/30 text-bc-muted hover:text-bc-light']">
        {{ tab.icon }} {{ tab.label }}
      </button>
    </div>

    <!-- ─── TAB: Excel/CSV ──────────────────────────────────────────── -->
    <div v-if="activeTab === 'file'" class="card-african p-6">
      <h2 class="text-bc-light font-semibold mb-4">Importar de Excel / CSV</h2>

      <!-- Dropzone -->
      <div
        class="border-2 border-dashed border-bc-gold/30 rounded-xl p-8 text-center cursor-pointer hover:border-bc-gold/60 transition mb-4"
        @click="$refs.fileInput.click()"
        @dragover.prevent
        @drop.prevent="handleFileDrop"
      >
        <span class="text-4xl block mb-2">📂</span>
        <p class="text-bc-light font-medium">Clica ou arrasta o ficheiro aqui</p>
        <p class="text-bc-muted text-sm mt-1">Suporta: .xlsx, .xls, .csv (máx. 10MB)</p>
        <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleFileSelect" />
      </div>

      <!-- Pré-visualização e mapeamento de colunas -->
      <div v-if="preview" class="mb-4">
        <div class="bg-bc-gold/5 border border-bc-gold/20 rounded-xl p-4 mb-4">
          <h3 class="text-bc-light font-medium mb-3">Mapeamento de colunas</h3>
          <p class="text-bc-muted text-xs mb-3">Confirma quais colunas correspondem a cada campo do Beconnect:</p>
          <div class="grid grid-cols-2 gap-2">
            <div v-for="(suggestion, col) in preview.suggested_mapping" :key="col" class="flex items-center gap-2 text-sm">
              <span class="text-bc-muted text-xs bg-bc-surface-2 px-2 py-1 rounded">{{ col }}</span>
              <span class="text-bc-gold">→</span>
              <select v-model="columnMapping[col]" class="select-african text-xs py-1 flex-1">
                <option value="">Ignorar</option>
                <option v-for="f in availableFields" :key="f.value" :value="f.value">{{ f.label }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Amostra dos dados -->
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-bc-gold/20">
                <th v-for="h in preview.headers" :key="h" class="text-bc-muted text-left py-2 px-2">{{ h }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in preview.sample_rows" :key="i" class="border-b border-bc-gold/10">
                <td v-for="cell in row" :key="cell" class="text-bc-light py-2 px-2">{{ cell }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <button @click="importFile" :disabled="!selectedFile || importing" class="btn-green w-full py-3">
        {{ importing ? `A importar... (${importProgress}%)` : '📤 Importar Ficheiro' }}
      </button>
    </div>

    <!-- ─── TAB: API Externa ──────────────────────────────────────────── -->
    <div v-if="activeTab === 'api'" class="card-african p-6">
      <h2 class="text-bc-light font-semibold mb-4">Conectar API Externa</h2>
      <p class="text-bc-muted text-sm mb-4">
        Conecta o teu sistema de gestão (seja PHP, Python, Java, .NET ou qualquer outra tecnologia) ao Beconnect.
      </p>

      <div class="space-y-3">
        <input v-model="apiConfig.name" type="text" placeholder="Nome da integração (ex: ERP Principal)" class="input-african" />
        <input v-model="apiConfig.endpoint_url" type="url" placeholder="URL da API (ex: https://meuapp.co.mz/api/produtos)" class="input-african" />

        <div class="grid grid-cols-2 gap-3">
          <select v-model="apiConfig.method" class="select-african">
            <option value="GET">GET</option>
            <option value="POST">POST</option>
          </select>
          <input v-model="apiConfig.data_path" type="text" placeholder="Caminho dos dados (ex: data.products)" class="input-african" />
        </div>

        <!-- Headers de autenticação -->
        <div>
          <label class="text-bc-muted text-sm mb-2 block">Headers de autenticação (opcional)</label>
          <div v-for="(header, i) in apiHeaders" :key="i" class="flex gap-2 mb-2">
            <input v-model="header.key" type="text" placeholder="Header (ex: Authorization)" class="input-african flex-1 text-sm" />
            <input v-model="header.value" type="text" placeholder="Valor (ex: Bearer token123)" class="input-african flex-1 text-sm" />
            <button @click="apiHeaders.splice(i, 1)" class="text-red-400 hover:text-red-300 px-2">✕</button>
          </div>
          <button @click="apiHeaders.push({key:'', value:''})" class="text-bc-gold text-sm hover:underline">+ Adicionar header</button>
        </div>

        <div class="flex items-center gap-3">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="apiConfig.auto_sync" class="accent-bc-gold" />
            <span class="text-bc-light text-sm">Sincronização automática</span>
          </label>
          <select v-if="apiConfig.auto_sync" v-model="apiConfig.sync_interval_minutes" class="select-african text-sm w-40">
            <option :value="15">Cada 15 min</option>
            <option :value="30">Cada 30 min</option>
            <option :value="60">Cada hora</option>
            <option :value="360">Cada 6 horas</option>
            <option :value="1440">Cada dia</option>
          </select>
        </div>

        <div class="flex gap-3">
          <button @click="testApi" :disabled="!apiConfig.endpoint_url || testingApi" class="btn-ghost flex-1">
            {{ testingApi ? 'A testar...' : '🔍 Testar Conexão' }}
          </button>
          <button @click="saveApi" :disabled="!apiConfig.name || !apiConfig.endpoint_url" class="btn-gold flex-1">
            💾 Guardar API
          </button>
        </div>

        <!-- Resultado do teste -->
        <div v-if="testResult" :class="['rounded-xl p-3 text-sm', testResult.success ? 'bg-green-900/30 text-green-400' : 'bg-red-900/30 text-red-400']">
          {{ testResult.message }}
          <div v-if="testResult.success && testResult.detected_fields?.length" class="mt-2">
            <p class="text-bc-muted text-xs">Campos detectados: {{ testResult.detected_fields.join(', ') }}</p>
            <p class="text-bc-muted text-xs">Produtos encontrados: {{ testResult.products_found }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── TAB: Webhook ──────────────────────────────────────────── -->
    <div v-if="activeTab === 'webhook'" class="card-african p-6">
      <h2 class="text-bc-light font-semibold mb-4">Webhook — Receber Stock em Tempo Real</h2>
      <p class="text-bc-muted text-sm mb-4">
        O teu sistema faz um POST para este endpoint sempre que tiver mudanças de stock. Funciona com qualquer linguagem.
      </p>

      <div class="bg-bc-surface-2 rounded-xl p-4 mb-4 font-mono text-sm">
        <p class="text-bc-muted text-xs mb-1">Endpoint (POST):</p>
        <p class="text-bc-gold break-all">{{ webhookUrl }}</p>
      </div>

      <div class="space-y-4">
        <div>
          <p class="text-bc-light text-sm font-medium mb-2">Exemplo de payload JSON:</p>
          <pre class="bg-bc-surface-2 rounded-xl p-4 text-xs text-bc-light overflow-x-auto">{{ webhookExample }}</pre>
        </div>

        <div>
          <p class="text-bc-light text-sm font-medium mb-2">Exemplo cURL:</p>
          <pre class="bg-bc-surface-2 rounded-xl p-4 text-xs text-bc-gold overflow-x-auto">{{ curlExample }}</pre>
        </div>

        <div class="bg-bc-gold/5 border border-bc-gold/20 rounded-xl p-3 text-xs text-bc-muted">
          ℹ O Beconnect aceita automaticamente qualquer formato JSON com produtos. Campos suportados:
          <strong class="text-bc-light">nome/name, preco/price, quantidade/stock, sku, codigo_barras/barcode, marca/brand, categoria/category</strong>
        </div>
      </div>
    </div>

    <!-- ─── TAB: APIs guardadas ──────────────────────────────────────────── -->
    <div v-if="activeTab === 'saved'" class="space-y-4">
      <div v-if="savedApis.length === 0" class="card-african p-6 text-center text-bc-muted">
        Nenhuma API configurada ainda.
      </div>
      <div v-for="api in savedApis" :key="api.id" class="card-african p-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-bc-light font-medium">{{ api.name }}</h3>
            <p class="text-bc-muted text-xs">{{ api.endpoint_url }}</p>
            <p class="text-bc-muted text-xs">Última sincronização: {{ api.last_synced_at ? formatDate(api.last_synced_at) : 'Nunca' }}</p>
          </div>
          <div class="flex gap-2">
            <button @click="syncApi(api)" class="btn-ghost text-xs px-3 py-1.5">🔄 Sincronizar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── TAB: Histórico ──────────────────────────────────────────── -->
    <div v-if="activeTab === 'history'">
      <div v-for="imp in importHistory" :key="imp.id" class="card-african p-4 mb-3">
        <div class="flex items-center justify-between mb-1">
          <span class="text-bc-light text-sm font-medium">{{ sourceLabel(imp.source) }}</span>
          <span :class="statusClass(imp.status)">{{ imp.status }}</span>
        </div>
        <div class="text-bc-muted text-xs space-x-3">
          <span>✓ {{ imp.imported_rows }} criados</span>
          <span>↑ {{ imp.updated_rows }} actualizados</span>
          <span v-if="imp.failed_rows">⚠ {{ imp.failed_rows }} falhas</span>
          <span>{{ formatDate(imp.created_at) }}</span>
        </div>
      </div>
    </div>

    <!-- Resultado da importação -->
    <div v-if="importResult" class="mt-4 card-african p-4">
      <h3 class="text-bc-light font-semibold mb-2">Resultado da Importação</h3>
      <div class="text-sm space-y-1">
        <p class="text-green-400">✓ {{ importResult.imported_rows }} produtos criados</p>
        <p class="text-blue-400">↑ {{ importResult.updated_rows }} produtos actualizados</p>
        <p v-if="importResult.failed_rows" class="text-red-400">⚠ {{ importResult.failed_rows }} falhas</p>
        <div v-if="importResult.errors?.length" class="mt-2">
          <p class="text-bc-muted text-xs" v-for="err in importResult.errors.slice(0,5)" :key="err">{{ err }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// Para admin: passa store_id como query param (usa a loja que está a gerir)
// Para store_owner: o backend resolve automaticamente pela sua conta
function storeParam() {
  if (auth.isAdmin && auth.user?.store?.id) return { store_id: auth.user.store.id }
  return {}
}

const activeTab = ref('file')
const tabs = [
  { id: 'file', icon: '📂', label: 'Excel / CSV' },
  { id: 'api', icon: '🔌', label: 'API Externa' },
  { id: 'webhook', icon: '🪝', label: 'Webhook' },
  { id: 'saved', icon: '💾', label: 'APIs Guardadas' },
  { id: 'history', icon: '📋', label: 'Histórico' },
]

const availableFields = [
  { value: 'name', label: 'Nome do produto' },
  { value: 'price', label: 'Preço' },
  { value: 'compare_price', label: 'Preço original (riscado)' },
  { value: 'stock_quantity', label: 'Quantidade em stock' },
  { value: 'sku', label: 'SKU / Código' },
  { value: 'barcode', label: 'Código de barras' },
  { value: 'brand', label: 'Marca' },
  { value: 'model', label: 'Modelo' },
  { value: 'category', label: 'Categoria' },
  { value: 'description', label: 'Descrição' },
  { value: 'unit', label: 'Unidade' },
]

// File import
const fileInput = ref(null)
const selectedFile = ref(null)
const preview = ref(null)
const columnMapping = reactive({})
const importing = ref(false)
const importProgress = ref(0)
const importResult = ref(null)
const tempFilePath = ref('')

// API config
const apiConfig = reactive({
  name: '', endpoint_url: '', method: 'GET', data_path: '',
  auto_sync: false, sync_interval_minutes: 60,
})
const apiHeaders = ref([])
const testingApi = ref(false)
const testResult = ref(null)
const savedApis = ref([])
const importHistory = ref([])

const webhookUrl = computed(() => {
  const storeId = 'ID_DA_LOJA' // seria carregado dinamicamente
  return `${window.location.origin}/api/stores/${storeId}/stock/webhook`
})

const webhookExample = `{
  "products": [
    {
      "nome": "Arroz Branco 5kg",
      "preco": 350.00,
      "quantidade": 100,
      "sku": "ARR-5KG",
      "marca": "Golden"
    },
    {
      "nome": "Óleo de Soja 1L",
      "preco": 180.00,
      "quantidade": 50,
      "categoria": "Alimentação"
    }
  ]
}`

const curlExample = `curl -X POST \\
  "${window.location.origin}/api/stores/SEU_SLUG/stock/webhook" \\
  -H "Content-Type: application/json" \\
  -H "X-Beconnect-Key: SUA_CHAVE" \\
  -d '{"products":[{"nome":"Produto","preco":100,"quantidade":50}]}'`

async function handleFileSelect(e) {
  selectedFile.value = e.target.files[0]
  await previewFile()
}

function handleFileDrop(e) {
  selectedFile.value = e.dataTransfer.files[0]
  previewFile()
}

async function previewFile() {
  if (!selectedFile.value) return
  const formData = new FormData()
  formData.append('file', selectedFile.value)
  Object.entries(storeParam()).forEach(([k, v]) => formData.append(k, v))
  const { data } = await axios.post('/store/stock/preview', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
  preview.value = data
  tempFilePath.value = data.file_path
  Object.assign(columnMapping, data.suggested_mapping)
}

async function importFile() {
  importing.value = true
  importResult.value = null
  try {
    const { data } = await axios.post('/store/stock/import-file', {
      file_path: tempFilePath.value,
      column_mapping: columnMapping,
      ...storeParam(),
    })
    importResult.value = data.import
    loadHistory()
  } catch (e) {
    alert(e.response?.data?.message || 'Erro na importação.')
  } finally {
    importing.value = false
  }
}

async function testApi() {
  testingApi.value = true
  testResult.value = null
  const headers = {}
  apiHeaders.value.forEach(h => { if (h.key) headers[h.key] = h.value })
  const { data } = await axios.post('/store/stock/external-apis/test', {
    ...apiConfig, headers, ...storeParam(),
  })
  testResult.value = data
  testingApi.value = false
}

async function saveApi() {
  const headers = {}
  apiHeaders.value.forEach(h => { if (h.key) headers[h.key] = h.value })
  await axios.post('/store/stock/external-apis', { ...apiConfig, headers, ...storeParam() })
  alert('API guardada com sucesso!')
  loadSavedApis()
}

async function syncApi(api) {
  await axios.post(`/store/stock/external-apis/${api.id}/sync`, storeParam())
  alert('Sincronização concluída!')
  loadHistory()
}

async function loadSavedApis() {
  const { data } = await axios.get('/store/stock/external-apis', { params: storeParam() })
  savedApis.value = data
}

async function loadHistory() {
  const { data } = await axios.get('/store/stock/history', { params: storeParam() })
  importHistory.value = data.data
}

function formatDate(d) {
  return new Date(d).toLocaleString('pt-MZ')
}

function sourceLabel(s) {
  const labels = { excel: 'Excel', csv: 'CSV', json: 'JSON', api_webhook: 'Webhook', api_pull: 'API Externa' }
  return labels[s] || s
}

function statusClass(s) {
  const map = {
    completed: 'text-green-400 text-xs',
    failed: 'text-red-400 text-xs',
    processing: 'text-yellow-400 text-xs',
  }
  return map[s] || 'text-bc-muted text-xs'
}

onMounted(() => {
  loadSavedApis()
  loadHistory()
})
</script>
