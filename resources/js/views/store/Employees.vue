<template>
  <div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-xl font-bold text-bc-light mb-6">Gestão de Funcionários</h1>

    <!-- Adicionar funcionário -->
    <div class="card-african p-5 mb-6">
      <h2 class="text-bc-gold font-semibold mb-4">Adicionar Funcionário</h2>
      <form @submit.prevent="addEmployee" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input
            v-model="form.name"
            type="text"
            placeholder="Nome completo *"
            class="input-african"
            required
          />
          <input
            v-model="form.email"
            type="email"
            placeholder="Email *"
            class="input-african"
            required
          />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <select v-model="form.role" class="select-african" required>
            <option value="">Seleccionar função *</option>
            <option value="manager">Gestor</option>
            <option value="cashier">Caixa</option>
            <option value="stock_keeper">Responsável de Stock</option>
            <option value="viewer">Visualizador</option>
          </select>
          <button type="submit" :disabled="adding" class="btn-gold py-2 px-4 text-sm">
            {{ adding ? 'A adicionar...' : '+ Adicionar Funcionário' }}
          </button>
        </div>
        <p v-if="addError" class="text-red-400 text-sm bg-red-900/20 rounded-lg p-3">{{ addError }}</p>
      </form>

      <!-- Senha temporária (novo utilizador criado) -->
      <div v-if="newUserInfo" class="mt-4 bg-bc-gold/10 border border-bc-gold/40 rounded-xl p-4">
        <p class="text-bc-gold font-semibold text-sm mb-2">✓ {{ newUserInfo.message }}</p>
        <div v-if="newUserInfo.temp_password" class="bg-bc-dark rounded-lg p-3 flex items-center justify-between gap-3">
          <div>
            <p class="text-bc-muted text-xs mb-1">Senha temporária (partilha com o funcionário)</p>
            <p class="text-bc-light font-mono font-bold text-lg tracking-widest">{{ newUserInfo.temp_password }}</p>
          </div>
          <button
            type="button"
            @click="copyPassword(newUserInfo.temp_password)"
            class="flex-shrink-0 px-3 py-2 rounded-lg border border-bc-gold/30 text-bc-gold text-xs hover:bg-bc-gold/10 transition"
          >{{ copied ? '✓ Copiado' : 'Copiar' }}</button>
        </div>
        <p class="text-bc-muted text-xs mt-2">O funcionário pode alterar a senha após o primeiro login.</p>
        <button @click="newUserInfo = null" class="text-bc-muted text-xs mt-2 hover:text-bc-gold">Fechar</button>
      </div>

      <!-- Info de permissões por role -->
      <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
        <div v-for="r in roleDescriptions" :key="r.role" class="bg-bc-gold/5 rounded-lg p-2 border border-bc-gold/10">
          <p class="text-bc-gold font-semibold mb-1">{{ r.label }}</p>
          <p class="text-bc-muted">{{ r.description }}</p>
        </div>
      </div>
    </div>

    <!-- Lista de funcionários -->
    <div class="card-african p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-bc-gold font-semibold">Equipa Actual</h2>
        <span class="text-bc-muted text-xs">{{ employees.length }} funcionário{{ employees.length !== 1 ? 's' : '' }}</span>
      </div>

      <div v-if="loading" class="space-y-2">
        <div v-for="i in 3" :key="i" class="skeleton h-14 rounded-xl"></div>
      </div>

      <div v-else-if="employees.length === 0" class="text-center py-8 text-bc-muted">
        <p class="text-3xl mb-2">👥</p>
        <p>Nenhum funcionário adicionado ainda.</p>
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="emp in employees"
          :key="emp.id"
          class="flex items-center justify-between bg-bc-surface-2 rounded-xl p-3"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-bc-gold/20 flex items-center justify-center text-bc-gold font-bold text-sm flex-shrink-0">
              {{ emp.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
            </div>
            <div>
              <p class="text-bc-light text-sm font-medium">{{ emp.user?.name ?? 'Sem nome' }}</p>
              <p class="text-bc-muted text-xs">{{ emp.user?.email }}</p>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <span :class="roleBadge(emp.role)">{{ roleLabel(emp.role) }}</span>
            <button
              @click="removeEmployee(emp)"
              class="text-red-400 hover:text-red-300 text-xs px-2 py-1 border border-red-400/30 rounded-lg hover:bg-red-900/20 transition"
            >
              Remover
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

const employees  = ref([])
const loading    = ref(true)
const adding     = ref(false)
const addError   = ref('')
const newUserInfo = ref(null)
const copied     = ref(false)

const form = reactive({ name: '', email: '', role: '' })

const roleDescriptions = [
  { role: 'manager',     label: 'Gestor',       description: 'Acesso total: produtos, stock, pedidos e definições.' },
  { role: 'cashier',     label: 'Caixa',        description: 'Gere pedidos e vendas. Sem acesso a definições.' },
  { role: 'stock_keeper',label: 'Stock',        description: 'Gere produtos e stock. Sem acesso a pedidos.' },
  { role: 'viewer',      label: 'Visualizador', description: 'Apenas leitura. Não pode alterar nada.' },
]

function roleLabel(role) {
  return { manager: 'Gestor', cashier: 'Caixa', stock_keeper: 'Stock', viewer: 'Visualizador' }[role] ?? role
}

function roleBadge(role) {
  const base = 'text-xs px-2 py-0.5 rounded-full font-medium '
  return base + ({
    manager:     'bg-purple-500/20 text-purple-300',
    cashier:     'bg-blue-500/20 text-blue-300',
    stock_keeper:'bg-green-500/20 text-green-300',
    viewer:      'bg-bc-gold/20 text-bc-gold',
  }[role] ?? 'bg-bc-surface text-bc-muted')
}

async function copyPassword(pwd) {
  await navigator.clipboard.writeText(pwd).catch(() => {})
  copied.value = true
  setTimeout(() => copied.value = false, 2000)
}

async function loadEmployees() {
  loading.value = true
  try {
    const { data } = await axios.get('/store/employees')
    employees.value = data
  } finally {
    loading.value = false
  }
}

async function addEmployee() {
  adding.value  = true
  addError.value = ''
  newUserInfo.value = null
  try {
    const { data } = await axios.post('/store/employees', form)
    newUserInfo.value = data
    form.name  = ''
    form.email = ''
    form.role  = ''
    await loadEmployees()
  } catch (e) {
    addError.value = e.response?.data?.errors
      ? Object.values(e.response.data.errors).flat().join(' ')
      : e.response?.data?.message || 'Erro ao adicionar funcionário.'
  } finally {
    adding.value = false
  }
}

async function removeEmployee(emp) {
  if (!confirm(`Remover ${emp.user?.name} da equipa?`)) return
  try {
    await axios.delete(`/store/employees/${emp.id}`)
    employees.value = employees.value.filter(e => e.id !== emp.id)
  } catch {
    alert('Erro ao remover funcionário.')
  }
}

onMounted(loadEmployees)
</script>
