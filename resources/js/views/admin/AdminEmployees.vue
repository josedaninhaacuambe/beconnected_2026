<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-bc-light">Funcionários de Loja</h1>
      <p class="text-bc-muted text-sm mt-1">Histórico completo de todos os funcionários — activos e removidos.</p>
    </div>

    <!-- Filtros -->
    <div class="card-african p-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="text-sm text-bc-muted">Pesquisar</label>
          <input v-model="filters.search" type="text" placeholder="Nome, email ou contacto..."
            class="input-field mt-2" @input="debouncedLoad" />
        </div>
        <div>
          <label class="text-sm text-bc-muted">Estado</label>
          <select v-model="filters.active" class="input-field mt-2" @change="loadEmployees">
            <option value="">Todos</option>
            <option value="1">Activos</option>
            <option value="0">Removidos</option>
          </select>
        </div>
        <div class="flex items-end">
          <button @click="resetFilters" class="btn-ghost text-sm px-4 py-2 w-full">Limpar filtros</button>
        </div>
      </div>
    </div>

    <!-- Stats rápidas -->
    <div class="flex gap-4 text-sm text-bc-muted flex-wrap">
      <span>Total: <strong class="text-bc-light">{{ pagination.total ?? '—' }}</strong></span>
      <span>Activos: <strong class="text-green-400">{{ activeCount }}</strong></span>
      <span>Removidos: <strong class="text-red-400">{{ removedCount }}</strong></span>
    </div>

    <!-- Tabela -->
    <div class="card-african overflow-hidden">
      <div v-if="loading" class="p-4 space-y-2">
        <div v-for="i in 6" :key="i" class="skeleton h-14 rounded-xl"></div>
      </div>
      <div v-else>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-bc-gold/20">
              <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Funcionário</th>
              <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Contacto</th>
              <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Loja</th>
              <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Perfil</th>
              <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Estado</th>
              <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Adicionado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="employees.length === 0">
              <td colspan="6" class="py-10 text-center text-bc-muted text-sm">Nenhum funcionário encontrado.</td>
            </tr>
            <tr v-for="emp in employees" :key="emp.id"
              class="border-b border-bc-gold/10 hover:bg-bc-gold/5 transition">
              <!-- Nome + Email -->
              <td class="py-3 px-4">
                <p class="font-semibold text-bc-light">{{ emp.user?.name ?? '—' }}</p>
                <p class="text-xs text-bc-muted">{{ emp.user?.email }}</p>
              </td>
              <!-- Contacto -->
              <td class="py-3 px-4">
                <span v-if="emp.user?.phone" class="text-bc-light font-medium">{{ emp.user.phone }}</span>
                <span v-else class="text-bc-muted text-xs italic">Sem contacto</span>
              </td>
              <!-- Loja -->
              <td class="py-3 px-4">
                <p class="text-bc-light">{{ emp.store?.name ?? '—' }}</p>
                <p class="text-xs text-bc-muted">{{ emp.store?.slug }}</p>
              </td>
              <!-- Perfil -->
              <td class="py-3 px-4">
                <span class="text-xs font-bold px-2 py-1 rounded-full" :class="roleBadge(emp.role)">
                  {{ roleLabel(emp.role) }}
                </span>
              </td>
              <!-- Estado -->
              <td class="py-3 px-4">
                <span class="text-xs font-bold px-2 py-1 rounded-full"
                  :class="emp.is_active ? 'bg-green-900/30 text-green-400' : 'bg-red-900/20 text-red-400'">
                  {{ emp.is_active ? 'Activo' : 'Removido' }}
                </span>
              </td>
              <!-- Data -->
              <td class="py-3 px-4 text-xs text-bc-muted">
                {{ formatDate(emp.created_at) }}
                <div v-if="emp.added_by" class="text-[10px] text-bc-muted opacity-60">por {{ emp.added_by?.name }}</div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Paginação -->
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-bc-gold/10">
          <button :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)"
            class="btn-ghost text-sm px-4 py-1.5 disabled:opacity-40">← Anterior</button>
          <span class="text-sm text-bc-muted">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
          <button :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)"
            class="btn-ghost text-sm px-4 py-1.5 disabled:opacity-40">Seguinte →</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'

const loading   = ref(false)
const employees = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })

const filters = reactive({ search: '', active: '' })

let searchTimer = null
function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadEmployees, 350)
}

function resetFilters() {
  filters.search = ''
  filters.active = ''
  loadEmployees()
}

const activeCount  = computed(() => employees.value.filter(e => e.is_active).length)
const removedCount = computed(() => employees.value.filter(e => !e.is_active).length)

async function loadEmployees(page = 1) {
  loading.value = true
  try {
    const params = { page }
    if (filters.search) params.search = filters.search
    if (filters.active !== '') params.active = filters.active
    const { data } = await axios.get('/admin/store-employees', { params })
    employees.value = data.data
    pagination.value = {
      current_page: data.current_page,
      last_page:    data.last_page,
      total:        data.total,
    }
  } finally {
    loading.value = false
  }
}

function goPage(page) {
  loadEmployees(page)
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('pt-MZ', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function roleLabel(role) {
  return { manager: 'Gerente', cashier: 'Vendedor', stock_keeper: 'Gest. Stock', viewer: 'Visualizador' }[role] ?? role
}
function roleBadge(role) {
  return {
    manager:      'bg-purple-900/30 text-purple-400',
    cashier:      'bg-blue-900/30 text-blue-400',
    stock_keeper: 'bg-green-900/30 text-green-400',
    viewer:       'bg-gray-700 text-gray-300',
  }[role] ?? 'bg-gray-700 text-gray-300'
}

onMounted(loadEmployees)
</script>
