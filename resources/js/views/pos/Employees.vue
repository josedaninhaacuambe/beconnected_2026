<template>
  <div class="overflow-y-auto h-full p-4">
    <div class="max-w-2xl mx-auto space-y-4">

      <!-- Adicionar funcionário -->
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h2 class="font-bold text-base text-gray-800 mb-4">➕ Adicionar Funcionário</h2>
        <form @submit.prevent="addEmployee" class="space-y-3">
          <div>
            <label class="text-xs font-semibold text-gray-500">Email do utilizador (deve ter conta no Beconnect)</label>
            <input v-model="form.email" type="email" placeholder="email@exemplo.com" required
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-500">Perfil / Função</label>
            <div class="grid grid-cols-2 gap-2 mt-1">
              <button v-for="r in roles" :key="r.value" type="button" @click="form.role = r.value"
                class="p-3 rounded-xl border-2 text-left transition"
                :class="form.role === r.value ? 'border-bc-gold bg-bc-gold/5' : 'border-gray-200 hover:border-gray-300'">
                <p class="font-bold text-sm" :class="form.role === r.value ? 'text-bc-gold' : 'text-gray-700'">{{ r.icon }} {{ r.label }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ r.desc }}</p>
              </button>
            </div>
          </div>
          <div v-if="addError" class="text-red-500 text-sm">{{ addError }}</div>
          <div v-if="addSuccess" class="text-green-600 text-sm">✅ {{ addSuccess }}</div>
          <button type="submit" :disabled="addLoading"
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
        <div v-else class="space-y-2">
          <div v-for="emp in employees" :key="emp.id"
            class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
            <!-- Avatar -->
            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-black text-base flex-shrink-0"
              style="background:#1C2B3C;">
              {{ (emp.user?.name ?? 'F')[0].toUpperCase() }}
            </div>
            <!-- Info -->
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-sm text-gray-800">{{ emp.user?.name ?? 'Utilizador' }}</p>
              <p class="text-xs text-gray-400">{{ emp.user?.email }}</p>
            </div>
            <!-- Role badge -->
            <span class="text-xs font-bold px-2.5 py-1 rounded-full"
              :class="roleBadge(emp.role)">
              {{ roleLabel(emp.role) }}
            </span>
            <!-- Status -->
            <span class="w-2 h-2 rounded-full" :class="emp.is_active ? 'bg-green-400' : 'bg-gray-300'"></span>
            <!-- Remover -->
            <button @click="removeEmployee(emp)"
              class="text-red-400 hover:text-red-600 transition text-sm px-2 py-1 rounded-lg hover:bg-red-50">
              Remover
            </button>
          </div>
          <p v-if="!employees.length" class="text-center py-8 text-gray-400">Sem funcionários adicionados.</p>
        </div>
      </div>

      <!-- Legenda de permissões -->
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h2 class="font-bold text-sm text-gray-700 mb-3">🔑 O que cada perfil pode fazer</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-100">
                <th class="pb-2">Função</th>
                <th class="pb-2 text-center">Vendas POS</th>
                <th class="pb-2 text-center">Gerir Stock</th>
                <th class="pb-2 text-center">Relatórios</th>
                <th class="pb-2 text-center">Equipa</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="r in permTable" :key="r.role">
                <td class="py-2 font-semibold">{{ r.label }}</td>
                <td class="py-2 text-center">{{ r.sales ? '✅' : '—' }}</td>
                <td class="py-2 text-center">{{ r.stock ? '✅' : '—' }}</td>
                <td class="py-2 text-center">{{ r.reports ? '✅' : '—' }}</td>
                <td class="py-2 text-center">{{ r.team ? '✅' : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const employees  = ref([])
const loading    = ref(true)
const form       = ref({ email: '', role: 'cashier' })
const addLoading = ref(false)
const addError   = ref('')
const addSuccess = ref('')

const roles = [
  { value: 'manager',      icon: '🧑‍💼', label: 'Gerente',         desc: 'Vendas, stock e relatórios' },
  { value: 'cashier',      icon: '🛒', label: 'Caixa',            desc: 'Apenas terminal de vendas' },
  { value: 'stock_keeper', icon: '📦', label: 'Gestor de Stock',  desc: 'Entradas e saídas de stock' },
  { value: 'viewer',       icon: '👁️', label: 'Visualizador',     desc: 'Só leitura de relatórios' },
]

const permTable = [
  { role: 'owner',        label: '👑 Proprietário',    sales: true,  stock: true,  reports: true,  team: true  },
  { role: 'manager',      label: '🧑‍💼 Gerente',          sales: true,  stock: true,  reports: true,  team: false },
  { role: 'cashier',      label: '🛒 Caixa',            sales: true,  stock: false, reports: false, team: false },
  { role: 'stock_keeper', label: '📦 Gestor de Stock',  sales: false, stock: true,  reports: false, team: false },
  { role: 'viewer',       label: '👁️ Visualizador',      sales: false, stock: false, reports: true,  team: false },
]

function roleBadge(role) {
  return {
    manager:      'bg-blue-100 text-blue-700',
    cashier:      'bg-green-100 text-green-700',
    stock_keeper: 'bg-yellow-100 text-yellow-700',
    viewer:       'bg-gray-100 text-gray-600',
  }[role] ?? 'bg-gray-100 text-gray-600'
}
function roleLabel(role) {
  return roles.find(r => r.value === role)?.label ?? role
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/pos/employees')
    employees.value = data
  } finally {
    loading.value = false
  }
}

async function addEmployee() {
  addLoading.value = true
  addError.value   = ''
  addSuccess.value = ''
  try {
    const { data } = await axios.post('/api/pos/employees', form.value)
    addSuccess.value = data.message
    form.value = { email: '', role: 'cashier' }
    await load()
  } catch (e) {
    addError.value = e.response?.data?.message ?? 'Erro ao adicionar.'
  } finally {
    addLoading.value = false
  }
}

async function removeEmployee(emp) {
  if (!confirm(`Remover ${emp.user?.name} da equipa?`)) return
  await axios.delete(`/api/pos/employees/${emp.id}`)
  await load()
}

onMounted(load)
</script>
