<template>
  <div class="overflow-y-auto h-full p-4">
    <div class="max-w-2xl mx-auto space-y-4">

      <!-- Adicionar funcionário -->
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h2 class="font-bold text-base text-gray-800 mb-4">➕ Adicionar Funcionário</h2>
        <form @submit.prevent="addEmployee" class="space-y-4">
          <div>
            <label class="text-xs font-semibold text-gray-500">Email (deve ter conta no Beconnect)</label>
            <input v-model="form.email" type="email" placeholder="email@exemplo.com" required
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
          </div>

          <!-- Perfil base -->
          <div>
            <label class="text-xs font-semibold text-gray-500">Perfil base</label>
            <div class="grid grid-cols-2 gap-2 mt-1">
              <button v-for="r in roles" :key="r.value" type="button"
                @click="selectRole(r.value)"
                class="p-3 rounded-xl border-2 text-left transition"
                :class="form.role === r.value ? 'border-bc-gold bg-bc-gold/5' : 'border-gray-200 hover:border-gray-300'">
                <p class="font-bold text-sm" :class="form.role === r.value ? 'text-bc-gold' : 'text-gray-700'">{{ r.icon }} {{ r.label }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ r.desc }}</p>
              </button>
            </div>
          </div>

          <!-- Permissões granulares -->
          <div>
            <label class="text-xs font-semibold text-gray-500 mb-2 block">Permissões de acesso (personalizável)</label>
            <div class="space-y-2 border border-gray-100 rounded-xl p-3 bg-gray-50">
              <div v-for="p in availablePermissions" :key="p.key"
                class="flex items-start gap-3 cursor-pointer group"
                @click="togglePermission(p.key)">
                <div class="mt-0.5 w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0 transition"
                  :class="form.permissions.includes(p.key)
                    ? 'border-bc-gold bg-bc-gold'
                    : 'border-gray-300 group-hover:border-bc-gold'">
                  <svg v-if="form.permissions.includes(p.key)" class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="flex-1">
                  <p class="text-sm font-semibold text-gray-800">{{ p.icon }} {{ p.label }}</p>
                  <p class="text-xs text-gray-400">{{ p.desc }}</p>
                </div>
              </div>
            </div>
            <p class="text-xs text-amber-600 mt-1.5">
              ⚠️ Relatórios só disponíveis para o proprietário e funcionários explicitamente autorizados.
            </p>
          </div>

          <div v-if="addError" class="text-red-500 text-sm">{{ addError }}</div>
          <div v-if="addSuccess" class="text-green-600 text-sm">✅ {{ addSuccess }}</div>
          <button type="submit" :disabled="addLoading || !form.role"
            class="w-full py-3 rounded-xl text-white font-bold text-sm transition hover:opacity-90 disabled:opacity-50"
            style="background:#F07820;">
            {{ addLoading ? 'A adicionar...' : 'Adicionar Funcionário' }}
          </button>
        </form>
      </div>

      <!-- Lista de funcionários -->
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h2 class="font-bold text-base text-gray-800 mb-4">👥 Equipa Actual</h2>
        <div v-if="loading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="skeleton h-16 rounded-xl"></div>
        </div>
        <div v-else class="space-y-3">
          <div v-for="emp in employees" :key="emp.id"
            class="p-3 rounded-xl bg-gray-50 border border-gray-100 space-y-2">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-black text-base flex-shrink-0"
                style="background:#1C2B3C;">
                {{ (emp.user?.name ?? 'F')[0].toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm text-gray-800">{{ emp.user?.name ?? 'Utilizador' }}</p>
                <p class="text-xs text-gray-400">{{ emp.user?.email }}</p>
              </div>
              <span class="text-xs font-bold px-2.5 py-1 rounded-full" :class="roleBadge(emp.role)">
                {{ roleLabel(emp.role) }}
              </span>
              <span class="w-2 h-2 rounded-full flex-shrink-0" :class="emp.is_active ? 'bg-green-400' : 'bg-gray-300'"></span>
              <button @click="removeEmployee(emp)"
                class="text-red-400 hover:text-red-600 transition text-xs px-2 py-1 rounded-lg hover:bg-red-50 flex-shrink-0">
                Remover
              </button>
            </div>
            <!-- Permissões actuais -->
            <div class="flex flex-wrap gap-1 pl-1">
              <span v-for="perm in (emp.permissions ?? [])" :key="perm"
                class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                :class="permBadge(perm)">
                {{ permLabel(perm) }}
              </span>
              <span v-if="!(emp.permissions ?? []).length" class="text-xs text-gray-400 italic">Sem permissões definidas</span>
            </div>
          </div>
          <p v-if="!employees.length" class="text-center py-8 text-gray-400">Sem funcionários adicionados.</p>
        </div>
      </div>

      <!-- Tabela resumo de permissões -->
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h2 class="font-bold text-sm text-gray-700 mb-3">🔑 Resumo de Permissões por Perfil</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-100">
                <th class="pb-2 pr-4">Perfil</th>
                <th class="pb-2 text-center">🛒 Vendas</th>
                <th class="pb-2 text-center">📦 Stock</th>
                <th class="pb-2 text-center">📊 Relatórios</th>
                <th class="pb-2 text-center">➕ Produtos</th>
                <th class="pb-2 text-center">👥 Equipa</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="r in rolesMatrix" :key="r.role">
                <td class="py-2 pr-4 font-semibold text-gray-700">{{ r.label }}</td>
                <td v-for="perm in ['fazer_vendas','gerir_stock','ver_relatorios','adicionar_produtos','gerir_equipa']" :key="perm"
                  class="py-2 text-center">
                  <span v-if="r.perms.includes(perm)" class="text-green-500 font-bold">✓</span>
                  <span v-else class="text-gray-200">—</span>
                </td>
              </tr>
            </tbody>
          </table>
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
const addLoading = ref(false)
const addError   = ref('')
const addSuccess = ref('')

const form = reactive({ email: '', role: '', permissions: [] })

const roles = [
  { value: 'cashier',      icon: '🛒', label: 'Vendedor',       desc: 'Apenas vendas no terminal POS' },
  { value: 'stock_keeper', icon: '📦', label: 'Gest. de Stock', desc: 'Gere entradas e saídas de stock' },
  { value: 'manager',      icon: '👔', label: 'Gerente',        desc: 'Vendas + stock + produtos' },
  { value: 'viewer',       icon: '👁️', label: 'Visualizador',  desc: 'Acesso a relatórios apenas' },
]

const availablePermissions = [
  { key: 'fazer_vendas',       icon: '🛒', label: 'Fazer Vendas',       desc: 'Acesso ao terminal de vendas POS' },
  { key: 'gerir_stock',        icon: '📦', label: 'Gerir Stock',        desc: 'Entradas, saídas e ajustes de stock' },
  { key: 'adicionar_produtos', icon: '➕', label: 'Adicionar Produtos', desc: 'Criar produtos no POS (online e offline)' },
  { key: 'ver_relatorios',     icon: '📊', label: 'Ver Relatórios',     desc: 'Relatórios de vendas, lucro e IVA' },
]

const rolesMatrix = [
  { role: 'owner',        label: '👑 Proprietário', perms: ['fazer_vendas','gerir_stock','ver_relatorios','adicionar_produtos','gerir_equipa'] },
  { role: 'manager',      label: '👔 Gerente',      perms: ['fazer_vendas','gerir_stock','adicionar_produtos'] },
  { role: 'cashier',      label: '🛒 Vendedor',     perms: ['fazer_vendas'] },
  { role: 'stock_keeper', label: '📦 Gest. Stock',  perms: ['gerir_stock','adicionar_produtos'] },
  { role: 'viewer',       label: '👁️ Visualizador', perms: ['ver_relatorios'] },
]

const defaultPerms = {
  cashier:      ['fazer_vendas'],
  stock_keeper: ['gerir_stock', 'adicionar_produtos'],
  manager:      ['fazer_vendas', 'gerir_stock', 'adicionar_produtos'],
  viewer:       ['ver_relatorios'],
}

function selectRole(role) {
  form.role = role
  form.permissions = [...(defaultPerms[role] ?? [])]
}

function togglePermission(key) {
  const idx = form.permissions.indexOf(key)
  if (idx >= 0) form.permissions.splice(idx, 1)
  else form.permissions.push(key)
}

function roleLabel(role) {
  return {
    owner: 'Proprietário', manager: 'Gerente',
    cashier: 'Vendedor', stock_keeper: 'Gest. Stock', viewer: 'Visualizador'
  }[role] ?? role
}

function roleBadge(role) {
  return {
    owner:        'bg-bc-gold/10 text-bc-gold',
    manager:      'bg-purple-100 text-purple-700',
    cashier:      'bg-blue-100 text-blue-700',
    stock_keeper: 'bg-green-100 text-green-700',
    viewer:       'bg-gray-100 text-gray-600',
  }[role] ?? 'bg-gray-100 text-gray-500'
}

function permLabel(perm) {
  return {
    fazer_vendas: '🛒 Vendas', gerir_stock: '📦 Stock',
    ver_relatorios: '📊 Relatórios', gerir_equipa: '👥 Equipa',
    adicionar_produtos: '➕ Produtos',
  }[perm] ?? perm
}

function permBadge(perm) {
  return {
    fazer_vendas:        'bg-blue-100 text-blue-700',
    gerir_stock:         'bg-green-100 text-green-700',
    ver_relatorios:      'bg-amber-100 text-amber-700',
    gerir_equipa:        'bg-purple-100 text-purple-700',
    adicionar_produtos:  'bg-teal-100 text-teal-700',
  }[perm] ?? 'bg-gray-100 text-gray-600'
}

async function addEmployee() {
  addError.value = ''
  addSuccess.value = ''
  addLoading.value = true
  try {
    await axios.post('/api/pos/employees', {
      email: form.email,
      role:  form.role,
      permissions: form.permissions,
    })
    addSuccess.value = 'Funcionário adicionado com sucesso!'
    form.email = ''
    form.role = ''
    form.permissions = []
    await loadEmployees()
  } catch (e) {
    addError.value = e.response?.data?.message ?? 'Erro ao adicionar funcionário.'
  } finally {
    addLoading.value = false
  }
}

async function removeEmployee(emp) {
  if (!confirm(`Remover ${emp.user?.name} da equipa?`)) return
  await axios.delete(`/api/pos/employees/${emp.id}`)
  await loadEmployees()
}

async function loadEmployees() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/pos/employees')
    employees.value = data
  } finally {
    loading.value = false
  }
}

onMounted(loadEmployees)
</script>
