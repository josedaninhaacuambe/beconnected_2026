<template>
  <div class="p-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-bc-light">Colaboradores</h1>
      <button @click="openCreate" class="btn-gold text-sm px-4 py-2">+ Novo Colaborador</button>
    </div>

    <!-- Stats bar -->
    <div class="flex gap-4 mb-5 text-sm text-bc-muted">
      <span>Total: <strong class="text-bc-light">{{ staff.length }}</strong></span>
      <span>Activos: <strong class="text-green-400">{{ staff.filter(s => s.is_active).length }}</strong></span>
    </div>

    <!-- Feedback messages -->
    <p v-if="successMsg" class="text-green-400 text-sm mb-4">{{ successMsg }}</p>
    <p v-if="errorMsg" class="text-red-400 text-sm mb-4">{{ errorMsg }}</p>

    <!-- Table -->
    <div class="card-african overflow-hidden">
      <div v-if="loading" class="p-4 space-y-2">
        <div v-for="i in 4" :key="i" class="skeleton h-12 rounded-xl"></div>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-bc-gold/20">
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Nome</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Email</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Permissões</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Estado</th>
            <th class="text-left py-3 px-4 text-bc-muted text-xs uppercase">Criado em</th>
            <th class="text-right py-3 px-4 text-bc-muted text-xs uppercase">Acções</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="staff.length === 0">
            <td colspan="6" class="py-8 text-center text-bc-muted text-sm">Nenhum colaborador encontrado.</td>
          </tr>
          <tr
            v-for="s in staff"
            :key="s.id"
            class="border-b border-bc-gold/10 hover:bg-bc-gold/5 transition"
          >
            <td class="py-3 px-4 text-bc-light font-medium">{{ s.name }}</td>
            <td class="py-3 px-4 text-bc-muted text-xs">{{ s.email }}</td>
            <td class="py-3 px-4">
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="p in (s.permissions ?? [])"
                  :key="p"
                  :class="['px-2 py-0.5 rounded-full text-xs font-medium', permColor(p)]"
                >{{ permLabel(p) }}</span>
                <span v-if="!s.permissions?.length" class="text-bc-muted text-xs">—</span>
              </div>
            </td>
            <td class="py-3 px-4">
              <span :class="s.is_active ? 'text-green-400' : 'text-red-400'">
                {{ s.is_active ? 'Activo' : 'Suspenso' }}
              </span>
            </td>
            <td class="py-3 px-4 text-bc-muted text-xs">{{ formatDate(s.created_at) }}</td>
            <td class="py-3 px-4">
              <div class="flex items-center justify-end gap-2">
                <button
                  @click="openEdit(s)"
                  class="text-xs px-2 py-1 bg-bc-gold/20 text-bc-gold rounded-lg hover:bg-bc-gold/30"
                >Editar</button>
                <button
                  @click="toggleStaff(s)"
                  :class="s.is_active
                    ? 'text-red-400 border-red-400/30'
                    : 'text-green-400 border-green-400/30'"
                  class="text-xs px-2 py-1 border rounded-lg"
                >{{ s.is_active ? 'Suspender' : 'Reactivar' }}</button>
                <button
                  @click="deleteStaff(s)"
                  class="text-xs px-2 py-1 text-red-400 border border-red-400/30 rounded-lg hover:bg-red-500/10"
                >Eliminar</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Slide-in modal -->
    <transition name="slide-in">
      <div v-if="modalOpen" class="fixed inset-0 bg-black/60 z-50 flex items-start justify-end" @click.self="modalOpen = false">
        <div class="bg-bc-surface w-full max-w-md h-full overflow-y-auto shadow-2xl p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-bc-light font-bold text-lg">{{ editTarget ? 'Editar Colaborador' : 'Novo Colaborador' }}</h2>
            <button @click="modalOpen = false" class="text-bc-muted hover:text-bc-light text-xl">✕</button>
          </div>

          <form @submit.prevent="saveStaff" class="space-y-4">
            <div>
              <label class="text-bc-muted text-xs block mb-1">Nome</label>
              <input v-model="form.name" type="text" required class="input-african w-full" placeholder="Nome completo" />
            </div>
            <div>
              <label class="text-bc-muted text-xs block mb-1">Email</label>
              <input v-model="form.email" type="email" required class="input-african w-full" placeholder="email@exemplo.com" />
            </div>
            <div v-if="!editTarget">
              <label class="text-bc-muted text-xs block mb-1">Palavra-passe</label>
              <input v-model="form.password" type="password" required class="input-african w-full" placeholder="Mínimo 8 caracteres" />
            </div>
            <div>
              <label class="text-bc-muted text-xs block mb-1">Telefone (opcional)</label>
              <input v-model="form.phone" type="tel" class="input-african w-full" placeholder="+258 8X XXX XXXX" />
            </div>

            <div>
              <label class="text-bc-muted text-xs block mb-2">Permissões</label>
              <div class="space-y-2">
                <label
                  v-for="p in PERMISSIONS"
                  :key="p.key"
                  class="flex items-center gap-3 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :value="p.key"
                    v-model="form.permissions"
                    class="w-4 h-4 accent-bc-gold"
                  />
                  <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', p.color]">{{ p.label }}</span>
                </label>
              </div>
            </div>

            <p v-if="modalError" class="text-red-400 text-sm">{{ modalError }}</p>

            <div class="flex gap-3 pt-2">
              <button type="submit" :disabled="saving" class="btn-gold flex-1 py-2 text-sm">
                {{ saving ? 'A guardar…' : 'Guardar' }}
              </button>
              <button type="button" @click="modalOpen = false" class="flex-1 py-2 text-sm border border-bc-gold/30 rounded-xl text-bc-muted hover:text-bc-light">
                Cancelar
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const PERMISSIONS = [
  { key: 'manage_deliveries', label: '🚚 Entregas', color: 'bg-blue-500/20 text-blue-300' },
  { key: 'manage_orders', label: '📦 Pedidos', color: 'bg-green-500/20 text-green-300' },
  { key: 'manage_visibility', label: '📡 Visibilidade', color: 'bg-purple-500/20 text-purple-300' },
  { key: 'manage_users', label: '👥 Utilizadores', color: 'bg-orange-500/20 text-orange-300' },
  { key: 'manage_stores', label: '🏪 Lojas', color: 'bg-yellow-500/20 text-yellow-300' },
  { key: 'manage_commissions', label: '💰 Comissões', color: 'bg-red-500/20 text-red-300' },
]

const staff = ref([])
const loading = ref(true)
const successMsg = ref('')
const errorMsg = ref('')

const modalOpen = ref(false)
const editTarget = ref(null)
const saving = ref(false)
const modalError = ref('')

const form = ref({ name: '', email: '', password: '', phone: '', permissions: [] })

function permLabel(key) {
  return PERMISSIONS.find(p => p.key === key)?.label ?? key
}

function permColor(key) {
  return PERMISSIONS.find(p => p.key === key)?.color ?? 'bg-bc-surface text-bc-muted'
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('pt-MZ')
}

function flashSuccess(msg) {
  successMsg.value = msg
  errorMsg.value = ''
  setTimeout(() => { successMsg.value = '' }, 3000)
}

function flashError(msg) {
  errorMsg.value = msg
  successMsg.value = ''
  setTimeout(() => { errorMsg.value = '' }, 4000)
}

async function loadStaff() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin/staff')
    staff.value = data.data ?? data
  } catch {
    flashError('Erro ao carregar colaboradores.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editTarget.value = null
  form.value = { name: '', email: '', password: '', phone: '', permissions: [] }
  modalError.value = ''
  modalOpen.value = true
}

function openEdit(s) {
  editTarget.value = s
  form.value = {
    name: s.name,
    email: s.email,
    password: '',
    phone: s.phone ?? '',
    permissions: [...(s.permissions ?? [])],
  }
  modalError.value = ''
  modalOpen.value = true
}

async function saveStaff() {
  saving.value = true
  modalError.value = ''
  try {
    if (editTarget.value) {
      const { data } = await axios.put(`/admin/staff/${editTarget.value.id}`, form.value)
      const idx = staff.value.findIndex(s => s.id === editTarget.value.id)
      if (idx !== -1) staff.value[idx] = data.staff ?? data
    } else {
      const { data } = await axios.post('/admin/staff', form.value)
      staff.value.unshift(data.staff ?? data)
    }
    modalOpen.value = false
    flashSuccess(editTarget.value ? 'Colaborador actualizado.' : 'Colaborador criado com sucesso.')
  } catch (err) {
    modalError.value = err.response?.data?.message ?? 'Erro ao guardar. Verifique os dados.'
  } finally {
    saving.value = false
  }
}

async function toggleStaff(s) {
  try {
    const { data } = await axios.put(`/admin/staff/${s.id}/toggle`)
    s.is_active = data.is_active ?? !s.is_active
    flashSuccess(`Colaborador ${s.is_active ? 'reactivado' : 'suspenso'}.`)
  } catch {
    flashError('Erro ao alterar estado.')
  }
}

async function deleteStaff(s) {
  if (!confirm(`Eliminar o colaborador "${s.name}"? Esta acção é irreversível.`)) return
  try {
    await axios.delete(`/admin/staff/${s.id}`)
    staff.value = staff.value.filter(x => x.id !== s.id)
    flashSuccess('Colaborador eliminado.')
  } catch {
    flashError('Erro ao eliminar colaborador.')
  }
}

onMounted(loadStaff)
</script>

<style scoped>
.slide-in-enter-active,
.slide-in-leave-active {
  transition: opacity 0.2s ease;
}
.slide-in-enter-active .bg-bc-surface,
.slide-in-leave-active .bg-bc-surface {
  transition: transform 0.25s ease;
}
.slide-in-enter-from {
  opacity: 0;
}
.slide-in-enter-from .bg-bc-surface {
  transform: translateX(100%);
}
.slide-in-leave-to {
  opacity: 0;
}
.slide-in-leave-to .bg-bc-surface {
  transform: translateX(100%);
}
</style>
