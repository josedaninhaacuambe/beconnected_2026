<template>
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-bc-light">Posicionamento & Visibilidade</h1>
        <p class="text-bc-muted text-xs mt-0.5">Gerir planos de destaque das lojas na plataforma</p>
      </div>
      <button @click="openActivateModal" class="btn-gold text-sm px-4 py-2">+ Activar Plano</button>
    </div>

    <!-- KPI cards -->
    <div v-if="kpiLoading" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div v-for="i in 3" :key="i" class="skeleton h-24 rounded-2xl"></div>
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="card-african p-4 text-center">
        <p class="text-bc-muted text-xs mb-1">Planos Activos</p>
        <p class="text-green-400 font-bold text-2xl">{{ kpi.active_count ?? 0 }}</p>
        <p class="text-bc-muted text-xs mt-1">lojas em destaque</p>
      </div>
      <div class="card-african p-4 text-center">
        <p class="text-bc-muted text-xs mb-1">A Expirar em 7 dias</p>
        <p :class="(kpi.expiring_in_7_days ?? 0) > 0 ? 'text-red-400' : 'text-bc-light'" class="font-bold text-2xl">
          {{ kpi.expiring_in_7_days ?? 0 }}
        </p>
        <p class="text-bc-muted text-xs mt-1">requerem renovação</p>
      </div>
      <div class="card-african p-4 text-center">
        <p class="text-bc-muted text-xs mb-1">Receita este Mês</p>
        <p class="text-bc-gold font-bold text-2xl">{{ formatMZN(kpi.revenue_this_month) }}</p>
        <p class="text-bc-muted text-xs mt-1">planos pagos</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-5">
      <input
        v-model="filters.search"
        type="text"
        placeholder="Pesquisar loja..."
        class="input-african flex-1 min-w-48"
        @input="debouncedLoad"
      />
      <select v-model="filters.status" @change="loadVisibility" class="select-african">
        <option value="">Todos os estados</option>
        <option value="active">Activo</option>
        <option value="expired">Expirado</option>
        <option value="pending_payment">Pagamento Pendente</option>
        <option value="cancelled">Cancelado</option>
      </select>
    </div>

    <!-- Feedback -->
    <div v-if="successMsg" class="bg-green-500/10 border border-green-500/30 text-green-400 text-sm rounded-xl px-4 py-2 mb-4">✓ {{ successMsg }}</div>
    <div v-if="errorMsg" class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-2 mb-4">✕ {{ errorMsg }}</div>

    <!-- Table -->
    <div class="card-african overflow-hidden">
      <div v-if="loading" class="p-4 space-y-2">
        <div v-for="i in 6" :key="i" class="skeleton h-12 rounded-xl"></div>
      </div>

      <div v-else-if="visibilityList.length === 0" class="py-16 text-center">
        <p class="text-4xl mb-3">📡</p>
        <p class="text-bc-muted">Nenhum plano encontrado.</p>
        <button @click="openActivateModal" class="mt-4 text-bc-gold text-sm border border-bc-gold/30 px-4 py-2 rounded-xl hover:bg-bc-gold/10">
          Activar primeiro plano
        </button>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-bc-gold/20">
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Loja</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Plano</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Início</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Fim</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase hidden md:table-cell">Próx. Renovação</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Estado</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase hidden lg:table-cell">Valor</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase hidden lg:table-cell">Factura</th>
            <th class="text-right py-3 px-4 text-bc-muted text-xs uppercase">Acções</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="item in visibilityList" :key="item.id">
            <tr class="border-b border-bc-gold/10 hover:bg-bc-gold/5 transition">
              <td class="py-3 px-4">
                <div class="text-bc-light font-medium">{{ item.store?.name ?? '—' }}</div>
                <div class="text-bc-muted text-xs">{{ item.store?.city?.name ?? '' }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="text-bc-light text-xs font-medium">{{ item.plan?.name ?? '—' }}</div>
                <div class="text-bc-muted text-xs">Boost: +{{ item.plan?.position_boost ?? 0 }}</div>
              </td>
              <td class="py-3 px-4 text-bc-muted text-xs">{{ formatDate(item.starts_at) }}</td>
              <td class="py-3 px-4 text-xs" :class="isExpiringSoon(item) ? 'text-red-400 font-medium' : 'text-bc-muted'">
                {{ formatDate(item.expires_at) }}
                <span v-if="isExpiringSoon(item)" class="block text-[10px]">⚠ Em breve</span>
              </td>
              <td class="py-3 px-4 text-bc-muted text-xs hidden md:table-cell">{{ formatDate(item.next_payment_at) }}</td>
              <td class="py-3 px-4">
                <select
                  :value="item.status"
                  @change="changeStatus(item, $event.target.value)"
                  :class="['text-xs rounded-lg px-2 py-1 border-0 outline-none cursor-pointer font-medium', statusBadge(item.status)]"
                >
                  <option value="active">Activo</option>
                  <option value="pending_payment">Pag. Pendente</option>
                  <option value="expired">Expirado</option>
                  <option value="cancelled">Cancelado</option>
                </select>
              </td>
              <td class="py-3 px-4 text-bc-muted text-xs hidden lg:table-cell">{{ formatMZN(item.amount_paid) }}</td>
              <td class="py-3 px-4 text-bc-muted text-xs hidden lg:table-cell">
                {{ item.invoice_number ?? '—' }}
              </td>
              <td class="py-3 px-4">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="sendReminder(item)"
                    :disabled="isReminderDisabled(item)"
                    :class="['text-xs px-2 py-1 border rounded-lg transition', isReminderDisabled(item) ? 'opacity-40 cursor-not-allowed border-bc-gold/20 text-bc-muted' : 'border-bc-gold/30 text-bc-gold hover:bg-bc-gold/10']"
                    :title="isReminderDisabled(item) ? 'Já notificado nos últimos 7 dias' : 'Enviar lembrete de renovação'"
                  >📧</button>
                  <button
                    @click="toggleHistory(item)"
                    class="text-xs px-2 py-1 border border-bc-gold/30 text-bc-muted rounded-lg hover:text-bc-light transition"
                    :title="expandedHistory === item.id ? 'Fechar histórico' : 'Ver histórico'"
                  >{{ expandedHistory === item.id ? '▲' : '📋' }}</button>
                  <button
                    @click="removePlan(item)"
                    class="text-xs px-2 py-1 border border-red-500/40 text-red-400 rounded-lg hover:bg-red-500/10 transition"
                    title="Remover visibilidade desta loja"
                  >🗑</button>
                </div>
              </td>
            </tr>

            <!-- Inline history -->
            <tr v-if="expandedHistory === item.id" class="bg-bc-surface-2/50">
              <td colspan="9" class="px-6 py-4">
                <p class="text-bc-muted text-xs font-semibold uppercase mb-2">Histórico de planos — {{ item.store?.name }}</p>
                <div v-if="historyLoading" class="skeleton h-8 w-64 rounded"></div>
                <div v-else-if="!historyMap[item.id]?.length" class="text-bc-muted text-xs">Sem histórico adicional.</div>
                <div v-else class="overflow-x-auto">
                  <table class="text-xs w-full">
                    <thead>
                      <tr class="text-bc-muted">
                        <th class="text-left py-1 pr-4">Plano</th>
                        <th class="text-left py-1 pr-4">Início</th>
                        <th class="text-left py-1 pr-4">Fim</th>
                        <th class="text-left py-1 pr-4">Valor</th>
                        <th class="text-left py-1 pr-4">Estado</th>
                        <th class="text-left py-1 pr-4">Factura</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="h in historyMap[item.id]" :key="h.id" class="border-t border-bc-gold/10">
                        <td class="py-1.5 pr-4 text-bc-light">{{ h.plan?.name ?? '—' }}</td>
                        <td class="py-1.5 pr-4 text-bc-muted">{{ formatDate(h.starts_at) }}</td>
                        <td class="py-1.5 pr-4 text-bc-muted">{{ formatDate(h.expires_at) }}</td>
                        <td class="py-1.5 pr-4 text-bc-gold">{{ formatMZN(h.amount_paid) }}</td>
                        <td class="py-1.5 pr-4">
                          <span :class="['px-1.5 py-0.5 rounded-full text-[10px] font-medium', statusBadge(h.status)]">
                            {{ statusLabel(h.status) }}
                          </span>
                        </td>
                        <td class="py-1.5 pr-4 text-bc-muted">{{ h.invoice_number ?? '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 p-4">
        <button
          v-for="page in meta.last_page"
          :key="page"
          @click="loadVisibility(page)"
          :class="['px-3 py-1 rounded-lg text-sm', page === currentPage ? 'bg-bc-gold text-bc-dark font-bold' : 'text-bc-muted hover:text-bc-gold border border-bc-gold/20']"
        >{{ page }}</button>
      </div>
    </div>
  </div>

  <!-- ─── Activate Plan Modal ─────────────────────────────────────── -->
  <div v-if="activateModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="activateModal = false">
    <div class="bg-bc-surface rounded-2xl w-full max-w-2xl shadow-2xl overflow-y-auto max-h-[95vh]">
      <div class="flex items-center justify-between p-6 border-b border-bc-gold/20">
        <h2 class="text-bc-light font-bold text-lg">Activar Plano de Visibilidade</h2>
        <button @click="activateModal = false" class="text-bc-muted hover:text-bc-light text-xl">✕</button>
      </div>

      <form @submit.prevent="submitActivate" class="p-6 space-y-5">
        <!-- Store search -->
        <div>
          <label class="text-bc-muted text-xs block mb-1.5">Loja *</label>
          <div class="relative">
            <input
              v-model="activateForm.storeSearch"
              type="text"
              placeholder="Escreve o nome da loja..."
              class="input-african w-full"
              @input="searchStores"
              autocomplete="off"
            />
            <div v-if="storeResults.length" class="absolute top-full left-0 right-0 mt-1 bg-bc-surface-2 rounded-xl border border-bc-gold/20 shadow-xl z-10 max-h-40 overflow-y-auto">
              <button
                v-for="s in storeResults"
                :key="s.id"
                type="button"
                @click="selectStore(s)"
                class="w-full text-left px-4 py-2.5 text-sm text-bc-light hover:bg-bc-gold/10 transition flex items-center gap-2"
              >
                <span class="text-bc-gold text-xs">🏪</span>
                <div>
                  <div>{{ s.name }}</div>
                  <div class="text-bc-muted text-xs">{{ s.city?.name ?? '' }}</div>
                </div>
              </button>
            </div>
          </div>
          <div v-if="activateForm.storeId" class="mt-2 flex items-center gap-2 text-green-400 text-xs">
            <span>✓</span> <span class="font-medium">{{ activateForm.storeName }}</span> seleccionada
          </div>
        </div>

        <!-- Plan select with description -->
        <div>
          <label class="text-bc-muted text-xs block mb-1.5">Plano *</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button
              v-for="p in plans"
              :key="p.id"
              type="button"
              @click="selectPlan(p)"
              :class="['relative p-4 rounded-xl border-2 text-left transition', activateForm.planId === p.id ? 'border-bc-gold bg-bc-gold/10' : 'border-bc-gold/20 hover:border-bc-gold/50 bg-bc-surface-2']"
            >
              <div class="flex items-start justify-between mb-1">
                <span class="text-bc-light font-bold text-sm">{{ p.name }}</span>
                <span v-if="activateForm.planId === p.id" class="text-bc-gold text-xs">✓</span>
              </div>
              <p class="text-bc-gold font-black text-lg">{{ formatMZN(p.price) }}</p>
              <p class="text-bc-muted text-xs mt-1">{{ p.duration_days }} dias</p>
              <p class="text-bc-muted text-xs mt-1">{{ p.description }}</p>
              <div class="mt-2 flex flex-wrap gap-1">
                <span class="bg-bc-gold/20 text-bc-gold text-[10px] px-1.5 py-0.5 rounded">+{{ p.position_boost }} posição</span>
                <span v-if="p.is_featured_badge" class="bg-yellow-500/20 text-yellow-300 text-[10px] px-1.5 py-0.5 rounded">⭐ Badge destaque</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Dates (auto-filled from plan) -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-bc-muted text-xs block mb-1.5">Data de Início *</label>
            <input v-model="activateForm.starts_at" type="date" required class="input-african w-full" @change="autoFillEndDate" />
          </div>
          <div>
            <label class="text-bc-muted text-xs block mb-1.5">Data de Fim *</label>
            <input v-model="activateForm.expires_at" type="date" required class="input-african w-full" />
          </div>
        </div>

        <!-- Amount (auto-filled from plan) -->
        <div>
          <label class="text-bc-muted text-xs block mb-1.5">Valor Pago (MZN) *</label>
          <input v-model.number="activateForm.amount_paid" type="number" min="0" step="0.01" required class="input-african w-full" placeholder="0.00" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-bc-muted text-xs block mb-1.5">Método de Pagamento *</label>
            <select v-model="activateForm.payment_method" required class="select-african w-full">
              <option value="">Seleccionar...</option>
              <option value="emola">eMola</option>
              <option value="mpesa">M-Pesa</option>
              <option value="cash">Numerário</option>
            </select>
          </div>
          <div>
            <label class="text-bc-muted text-xs block mb-1.5">Referência (opcional)</label>
            <input v-model="activateForm.payment_reference" type="text" class="input-african w-full" placeholder="Ref. da transacção" />
          </div>
        </div>

        <div>
          <label class="text-bc-muted text-xs block mb-1.5">Notas internas (opcional)</label>
          <textarea v-model="activateForm.notes" rows="2" class="input-african w-full resize-none" placeholder="Observações..."></textarea>
        </div>

        <!-- Summary -->
        <div v-if="activateForm.storeId && activateForm.planId" class="bg-bc-gold/5 border border-bc-gold/20 rounded-xl p-4 text-xs space-y-1">
          <p class="text-bc-gold font-semibold mb-2">Resumo da activação:</p>
          <p class="text-bc-muted">🏪 Loja: <span class="text-bc-light">{{ activateForm.storeName }}</span></p>
          <p class="text-bc-muted">📦 Plano: <span class="text-bc-light">{{ selectedPlan?.name }}</span></p>
          <p class="text-bc-muted">📅 Validade: <span class="text-bc-light">{{ activateForm.starts_at }} → {{ activateForm.expires_at }}</span></p>
          <p class="text-bc-muted">💰 Valor: <span class="text-bc-gold font-bold">{{ formatMZN(activateForm.amount_paid) }}</span></p>
          <p class="text-bc-muted">📡 Posição boost: <span class="text-green-400 font-bold">+{{ selectedPlan?.position_boost }}</span></p>
          <p v-if="selectedPlan?.is_featured_badge" class="text-yellow-300">⭐ Badge "DESTAQUE" activado na loja</p>
          <p class="text-bc-muted">📩 Notificação será enviada ao dono da loja</p>
        </div>

        <div v-if="modalError" class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-2">{{ modalError }}</div>

        <div class="flex gap-3 pt-1">
          <button
            type="submit"
            :disabled="activating || !activateForm.storeId || !activateForm.planId"
            class="btn-gold flex-1 py-2.5 text-sm disabled:opacity-50"
          >
            {{ activating ? 'A activar…' : '🚀 Activar Plano' }}
          </button>
          <button type="button" @click="activateModal = false" class="flex-1 py-2.5 text-sm border border-bc-gold/30 rounded-xl text-bc-muted hover:text-bc-light">
            Cancelar
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const visibilityList = ref([])
const loading = ref(true)
const kpiLoading = ref(true)
const kpi = ref({})
const plans = ref([])
const meta = ref({ last_page: 1 })
const currentPage = ref(1)
const filters = ref({ search: '', status: '' })
const successMsg = ref('')
const errorMsg = ref('')

const expandedHistory = ref(null)
const historyMap = ref({})
const historyLoading = ref(false)

const activateModal = ref(false)
const activating = ref(false)
const modalError = ref('')
const storeResults = ref([])
let storeSearchTimer = null
let searchTimer = null

const activateForm = ref({
  storeSearch: '', storeId: null, storeName: '',
  planId: null, starts_at: '', expires_at: '',
  amount_paid: '', payment_method: '', payment_reference: '', notes: '',
})

const selectedPlan = computed(() => plans.value.find(p => p.id === activateForm.value.planId) ?? null)

// ─── Formatting ──────────────────────────────────────────────────────────────

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('pt-MZ', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

function statusLabel(s) {
  return { active: 'Activo', expired: 'Expirado', pending_payment: 'Pag. Pendente', cancelled: 'Cancelado' }[s] ?? s
}

function statusBadge(s) {
  return {
    active: 'bg-green-500/20 text-green-300',
    expired: 'bg-red-500/20 text-red-300',
    pending_payment: 'bg-yellow-500/20 text-yellow-300',
    cancelled: 'bg-gray-500/20 text-gray-400',
  }[s] ?? 'bg-bc-surface text-bc-muted'
}

function isReminderDisabled(item) {
  if (!item.payment_notified_at) return false
  return Date.now() - new Date(item.payment_notified_at).getTime() < 7 * 24 * 60 * 60 * 1000
}

function isExpiringSoon(item) {
  if (item.status !== 'active' || !item.expires_at) return false
  return new Date(item.expires_at) - Date.now() < 7 * 24 * 60 * 60 * 1000
}

function flashSuccess(msg) {
  successMsg.value = msg; errorMsg.value = ''
  setTimeout(() => { successMsg.value = '' }, 4000)
}

function flashError(msg) {
  errorMsg.value = msg; successMsg.value = ''
  setTimeout(() => { errorMsg.value = '' }, 5000)
}

// ─── Data loading ─────────────────────────────────────────────────────────────

async function loadKpi() {
  kpiLoading.value = true
  try {
    const { data } = await axios.get('/admin/visibility/dashboard')
    kpi.value = data
  } catch {} finally {
    kpiLoading.value = false
  }
}

async function loadVisibility(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const { data } = await axios.get('/admin/visibility', { params: { ...filters.value, page } })
    visibilityList.value = data.data ?? data
    meta.value = data.meta ?? { last_page: 1 }
  } catch {
    flashError('Erro ao carregar dados de visibilidade.')
  } finally {
    loading.value = false
  }
}

function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadVisibility(1), 400)
}

async function loadPlans() {
  try {
    const { data } = await axios.get('/visibility-plans')
    plans.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch {}
}

// ─── Actions ─────────────────────────────────────────────────────────────────

async function sendReminder(item) {
  try {
    await axios.post(`/admin/visibility/${item.id}/remind`)
    item.payment_notified_at = new Date().toISOString()
    flashSuccess('Lembrete enviado ao dono da loja.')
  } catch (err) {
    flashError(err.response?.data?.message ?? 'Erro ao enviar lembrete.')
  }
}

async function removePlan(item) {
  const storeName = item.store?.name ?? 'esta loja'
  if (!confirm(`Remover a visibilidade de "${storeName}"?\n\nA loja perderá o destaque e voltará à posição normal na plataforma.`)) return
  try {
    await axios.delete(`/admin/visibility/${item.id}`)
    visibilityList.value = visibilityList.value.filter(v => v.id !== item.id)
    flashSuccess(`Visibilidade de "${storeName}" removida.`)
    loadKpi()
  } catch (err) {
    flashError(err.response?.data?.message ?? 'Erro ao remover visibilidade.')
  }
}

async function changeStatus(item, newStatus) {
  if (!confirm(`Alterar estado para "${statusLabel(newStatus)}"?`)) return
  try {
    const { data } = await axios.put(`/admin/visibility/${item.id}/status`, { status: newStatus })
    Object.assign(item, data)
    flashSuccess('Estado actualizado.')
    loadKpi()
  } catch (err) {
    flashError(err.response?.data?.message ?? 'Erro ao actualizar estado.')
  }
}

async function toggleHistory(item) {
  if (expandedHistory.value === item.id) {
    expandedHistory.value = null
    return
  }
  expandedHistory.value = item.id
  if (historyMap.value[item.id]) return
  historyLoading.value = true
  try {
    const { data } = await axios.get(`/admin/visibility/${item.id}/history`)
    historyMap.value[item.id] = Array.isArray(data) ? data : (data.data ?? [])
  } catch {
    historyMap.value[item.id] = []
  } finally {
    historyLoading.value = false
  }
}

// ─── Activate modal ───────────────────────────────────────────────────────────

function openActivateModal() {
  activateForm.value = {
    storeSearch: '', storeId: null, storeName: '',
    planId: null, starts_at: new Date().toISOString().split('T')[0],
    expires_at: '', amount_paid: '', payment_method: '', payment_reference: '', notes: '',
  }
  modalError.value = ''
  storeResults.value = []
  activateModal.value = true
}

function searchStores() {
  clearTimeout(storeSearchTimer)
  const q = activateForm.value.storeSearch.trim()
  if (!q) { storeResults.value = []; return }
  storeSearchTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get('/stores', { params: { search: q, per_page: 8 } })
      storeResults.value = data.data ?? data
    } catch {}
  }, 300)
}

function selectStore(s) {
  activateForm.value.storeId = s.id
  activateForm.value.storeName = s.name
  activateForm.value.storeSearch = s.name
  storeResults.value = []
}

function selectPlan(plan) {
  activateForm.value.planId = plan.id
  activateForm.value.amount_paid = plan.price
  autoFillEndDate()
}

function autoFillEndDate() {
  if (!activateForm.value.starts_at || !selectedPlan.value) return
  const start = new Date(activateForm.value.starts_at)
  start.setDate(start.getDate() + selectedPlan.value.duration_days)
  activateForm.value.expires_at = start.toISOString().split('T')[0]
}

async function submitActivate() {
  if (!activateForm.value.storeId) { modalError.value = 'Seleccione uma loja.'; return }
  if (!activateForm.value.planId) { modalError.value = 'Seleccione um plano.'; return }
  activating.value = true
  modalError.value = ''
  try {
    await axios.post(`/admin/visibility/stores/${activateForm.value.storeId}/activate`, {
      visibility_plan_id: activateForm.value.planId,
      starts_at:          activateForm.value.starts_at,
      expires_at:         activateForm.value.expires_at,
      amount_paid:        activateForm.value.amount_paid,
      payment_method:     activateForm.value.payment_method,
      payment_reference:  activateForm.value.payment_reference || null,
      notes:              activateForm.value.notes || null,
    })
    activateModal.value = false
    flashSuccess(`Plano activado para ${activateForm.value.storeName}! A loja já está em destaque.`)
    loadVisibility()
    loadKpi()
  } catch (err) {
    const errs = err.response?.data?.errors
    modalError.value = errs ? Object.values(errs).flat().join(' ') : (err.response?.data?.message ?? 'Erro ao activar plano.')
  } finally {
    activating.value = false
  }
}

onMounted(() => {
  loadKpi()
  loadVisibility()
  loadPlans()
})
</script>
