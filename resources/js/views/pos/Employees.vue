<template>
  <div class="overflow-y-auto h-full p-4">
    <div class="max-w-2xl mx-auto space-y-4">

      <!-- Banner offline -->
      <div v-if="!isOnline" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-xl">
        <span>📵</span>
        <span>Modo offline — as alterações serão sincronizadas quando houver ligação
          <span v-if="pendingEmployeeCount > 0">({{ pendingEmployeeCount }} pendente{{ pendingEmployeeCount > 1 ? 's' : '' }})</span>
        </span>
      </div>

      <!-- Selector de loja (apenas para donos com múltiplas lojas) -->
      <div v-if="auth.allStores.length > 1" class="bg-white rounded-xl border border-gray-100 p-4">
        <label class="text-xs font-semibold text-gray-500 block mb-2">🏪 Loja activa</label>
        <div class="flex gap-2 flex-wrap">
          <button v-for="store in auth.allStores" :key="store.id"
            type="button"
            @click="switchStore(store)"
            class="px-3 py-2 rounded-xl border-2 text-sm font-semibold transition"
            :class="auth.activeStoreId === store.id
              ? 'border-bc-gold bg-bc-gold/5 text-bc-gold'
              : 'border-gray-200 text-gray-600 hover:border-gray-300'">
            {{ store.name }}
          </button>
        </div>
        <p class="text-[10px] text-gray-400 mt-1">Os funcionários abaixo pertencem à loja seleccionada.</p>
      </div>

      <!-- Adicionar / Criar funcionário -->
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h2 class="font-bold text-base text-gray-800 mb-4">➕ Funcionário</h2>

        <!-- Abas -->
        <div class="flex gap-1 mb-4 bg-gray-100 rounded-xl p-1">
          <button @click="addTab = 'create'"
            class="flex-1 py-2 rounded-lg text-sm font-semibold transition"
            :class="addTab === 'create' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700'">
            🆕 Criar conta
          </button>
          <button @click="addTab = 'existing'"
            class="flex-1 py-2 rounded-lg text-sm font-semibold transition"
            :class="addTab === 'existing' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700'">
            📧 Adicionar existente
          </button>
        </div>

        <!-- ─── Tab: Criar nova conta ─────────────────────────────────── -->
        <form v-if="addTab === 'create'" @submit.prevent="createAccount" class="space-y-4">
          <p class="text-xs text-gray-400">
            Cria uma conta directamente para o funcionário. Ele entra com o email e senha que definires — sem verificação por OTP.
          </p>
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2 sm:col-span-1">
              <label class="text-xs font-semibold text-gray-500">Nome completo *</label>
              <input v-model="createForm.name" type="text" placeholder="João Silva" required
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
            </div>
            <div class="col-span-2 sm:col-span-1">
              <label class="text-xs font-semibold text-gray-500">Contacto / Telefone *</label>
              <input v-model="createForm.phone" type="tel" placeholder="+258 84 000 0000" required
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
              <p class="text-[10px] text-gray-400 mt-0.5">Guardado mesmo após remoção da equipa.</p>
            </div>
            <div class="col-span-2 sm:col-span-1">
              <label class="text-xs font-semibold text-gray-500">Email *</label>
              <input v-model="createForm.email" type="email" placeholder="joao.silva@exemplo.com" required
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
              <p class="text-[10px] text-gray-400 mt-0.5">Pode ser qualquer email, mesmo que não seja real.</p>
            </div>
            <div class="col-span-2">
              <label class="text-xs font-semibold text-gray-500">Senha inicial *</label>
              <div class="relative mt-1">
                <input v-model="createForm.password" :type="showCreatePass ? 'text' : 'password'"
                  placeholder="Mínimo 6 caracteres" required minlength="6"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-bc-gold pr-10" />
                <button type="button" @click="showCreatePass = !showCreatePass"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                  {{ showCreatePass ? '🙈' : '👁️' }}
                </button>
              </div>
              <p class="text-[10px] text-gray-400 mt-0.5">O funcionário pode alterar a senha mais tarde.</p>
            </div>
          </div>

          <!-- Role -->
          <div>
            <label class="text-xs font-semibold text-gray-500">Perfil base</label>
            <div class="grid grid-cols-2 gap-2 mt-1">
              <button v-for="r in roles" :key="r.value" type="button"
                @click="selectCreateRole(r.value)"
                class="p-3 rounded-xl border-2 text-left transition"
                :class="createForm.role === r.value ? 'border-bc-gold bg-bc-gold/5' : 'border-gray-200 hover:border-gray-300'">
                <p class="font-bold text-sm" :class="createForm.role === r.value ? 'text-bc-gold' : 'text-gray-700'">{{ r.icon }} {{ r.label }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ r.desc }}</p>
              </button>
            </div>
          </div>

          <!-- Permissões -->
          <div>
            <label class="text-xs font-semibold text-gray-500 mb-2 block">Permissões de acesso</label>
            <div class="space-y-2 border border-gray-100 rounded-xl p-3 bg-gray-50">
              <div v-for="p in availablePermissions" :key="p.key"
                class="flex items-start gap-3 cursor-pointer group"
                @click="toggleCreatePerm(p.key)">
                <div class="mt-0.5 w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0 transition"
                  :class="createForm.permissions.includes(p.key) ? 'border-bc-gold bg-bc-gold' : 'border-gray-300 group-hover:border-bc-gold'">
                  <svg v-if="createForm.permissions.includes(p.key)" class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="flex-1">
                  <p class="text-sm font-semibold text-gray-800">{{ p.icon }} {{ p.label }}</p>
                  <p class="text-xs text-gray-400">{{ p.desc }}</p>
                </div>
              </div>
            </div>
          </div>

          <div v-if="createError" class="text-red-500 text-sm">{{ createError }}</div>
          <div v-if="createSuccess" class="text-green-600 text-sm">✅ {{ createSuccess }}</div>
          <button type="submit" :disabled="createLoading || !createForm.role || !createForm.name || !createForm.password"
            class="w-full py-3 rounded-xl text-white font-bold text-sm transition hover:opacity-90 disabled:opacity-50"
            style="background:#F07820;">
            {{ createLoading ? 'A criar conta...' : '🆕 Criar Conta de Funcionário' }}
          </button>
        </form>

        <!-- ─── Tab: Adicionar conta existente ────────────────────────── -->
        <form v-else @submit.prevent="addEmployee" class="space-y-4">
          <p class="text-xs text-gray-400">
            Adiciona um utilizador que já tem conta no Beconnect, pelo email.
          </p>
          <div>
            <label class="text-xs font-semibold text-gray-500">Email da conta existente</label>
            <input v-model="form.email" type="email" placeholder="email@exemplo.com" required
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
          </div>

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

          <div>
            <label class="text-xs font-semibold text-gray-500 mb-2 block">Permissões de acesso</label>
            <div class="space-y-2 border border-gray-100 rounded-xl p-3 bg-gray-50">
              <div v-for="p in availablePermissions" :key="p.key"
                class="flex items-start gap-3 cursor-pointer group"
                @click="togglePermission(p.key)">
                <div class="mt-0.5 w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0 transition"
                  :class="form.permissions.includes(p.key) ? 'border-bc-gold bg-bc-gold' : 'border-gray-300 group-hover:border-bc-gold'">
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
            class="rounded-xl border border-gray-100 overflow-hidden">

            <!-- Cabeçalho -->
            <div class="flex items-center gap-3 p-3 bg-gray-50">
              <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-black text-base flex-shrink-0"
                style="background:#1C2B3C;">
                {{ (emp.user?.name ?? 'F')[0].toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm text-gray-800">{{ emp.user?.name ?? 'Utilizador' }}</p>
                <p class="text-xs text-gray-400">{{ emp.user?.email }}</p>
                <p v-if="emp.user?.phone" class="text-xs text-gray-400">📞 {{ emp.user.phone }}</p>
              </div>
              <span class="text-xs font-bold px-2.5 py-1 rounded-full" :class="roleBadge(emp.role)">
                {{ roleLabel(emp.role) }}
              </span>
              <span class="w-2 h-2 rounded-full flex-shrink-0" :class="emp.is_active ? 'bg-green-400' : 'bg-gray-300'"></span>
              <button @click="toggleEdit(emp)"
                class="text-xs px-2 py-1 rounded-lg border transition flex-shrink-0"
                :class="editingId === emp.id ? 'border-bc-gold text-bc-gold bg-bc-gold/5' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
                {{ editingId === emp.id ? 'Fechar' : '✏️' }}
              </button>
              <button @click="removeEmployee(emp)"
                class="text-red-400 hover:text-red-600 transition text-xs px-2 py-1 rounded-lg hover:bg-red-50 flex-shrink-0">
                Remover
              </button>
            </div>

            <!-- Permissões (collapsed) -->
            <div v-if="editingId !== emp.id" class="flex flex-wrap gap-1 px-3 py-2 border-t border-gray-100">
              <span v-for="perm in (emp.permissions ?? [])" :key="perm"
                class="text-[10px] font-semibold px-2 py-0.5 rounded-full" :class="permBadge(perm)">
                {{ permLabel(perm) }}
              </span>
              <span v-if="!(emp.permissions ?? []).length" class="text-xs text-gray-400 italic">Sem permissões</span>
            </div>

            <!-- Painel de edição -->
            <div v-else class="p-4 border-t border-gray-100 bg-white space-y-4">
              <!-- Sub-abas de edição -->
              <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
                <button @click="editTab[emp.id] = 'perms'"
                  class="flex-1 py-1.5 rounded-lg text-xs font-semibold transition"
                  :class="(editTab[emp.id] ?? 'perms') === 'perms' ? 'bg-white shadow text-gray-800' : 'text-gray-500'">
                  🔑 Permissões
                </button>
                <button @click="editTab[emp.id] = 'password'"
                  class="flex-1 py-1.5 rounded-lg text-xs font-semibold transition"
                  :class="editTab[emp.id] === 'password' ? 'bg-white shadow text-gray-800' : 'text-gray-500'">
                  🔒 Redefinir senha
                </button>
              </div>

              <!-- Permissões -->
              <div v-if="(editTab[emp.id] ?? 'perms') === 'perms'" class="space-y-3">
                <div>
                  <label class="text-xs font-semibold text-gray-500 block mb-2">Perfil base</label>
                  <div class="grid grid-cols-2 gap-2">
                    <button v-for="r in roles" :key="r.value" type="button"
                      @click="setEditRole(emp.id, r.value)"
                      class="p-2.5 rounded-xl border-2 text-left transition"
                      :class="editForms[emp.id]?.role === r.value ? 'border-bc-gold bg-bc-gold/5' : 'border-gray-200 hover:border-gray-300'">
                      <p class="font-bold text-xs" :class="editForms[emp.id]?.role === r.value ? 'text-bc-gold' : 'text-gray-700'">
                        {{ r.icon }} {{ r.label }}
                      </p>
                    </button>
                  </div>
                </div>
                <div>
                  <label class="text-xs font-semibold text-gray-500 block mb-2">Permissões de acesso</label>
                  <div class="space-y-2 border border-gray-100 rounded-xl p-3 bg-gray-50">
                    <div v-for="p in availablePermissions" :key="p.key"
                      class="flex items-start gap-3 cursor-pointer group"
                      @click="toggleEditPermission(emp.id, p.key)">
                      <div class="mt-0.5 w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0 transition"
                        :class="editForms[emp.id]?.permissions?.includes(p.key) ? 'border-bc-gold bg-bc-gold' : 'border-gray-300 group-hover:border-bc-gold'">
                        <svg v-if="editForms[emp.id]?.permissions?.includes(p.key)" class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                      </div>
                      <div class="flex-1">
                        <p class="text-xs font-semibold text-gray-800">{{ p.icon }} {{ p.label }}</p>
                        <p class="text-[11px] text-gray-400">{{ p.desc }}</p>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-if="editErrors[emp.id]" class="text-red-500 text-xs">{{ editErrors[emp.id] }}</div>
                <div class="flex gap-2">
                  <button @click="toggleEdit(emp)" class="flex-1 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50">Cancelar</button>
                  <button @click="saveEdit(emp)" :disabled="savingId === emp.id"
                    class="flex-1 py-2 rounded-xl text-white font-bold text-xs disabled:opacity-50" style="background:#F07820;">
                    {{ savingId === emp.id ? 'A guardar...' : '✓ Guardar' }}
                  </button>
                </div>
              </div>

              <!-- Redefinir senha -->
              <div v-else class="space-y-3">
                <p class="text-xs text-gray-400">
                  Define uma nova senha para <strong>{{ emp.user?.name }}</strong>. Avisa o funcionário da nova senha.
                </p>
                <div class="relative">
                  <input v-model="resetPassForms[emp.id]" :type="showResetPass[emp.id] ? 'text' : 'password'"
                    placeholder="Nova senha (mínimo 6 caracteres)" minlength="6"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-bc-gold pr-10" />
                  <button type="button" @click="showResetPass[emp.id] = !showResetPass[emp.id]"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                    {{ showResetPass[emp.id] ? '🙈' : '👁️' }}
                  </button>
                </div>
                <div v-if="resetPassErrors[emp.id]" class="text-red-500 text-xs">{{ resetPassErrors[emp.id] }}</div>
                <div v-if="resetPassSuccess[emp.id]" class="text-green-600 text-xs">✅ {{ resetPassSuccess[emp.id] }}</div>
                <button @click="resetPassword(emp)" :disabled="resetPassLoading[emp.id] || !resetPassForms[emp.id]"
                  class="w-full py-2 rounded-xl text-white font-bold text-xs disabled:opacity-50" style="background:#1C2B3C;">
                  {{ resetPassLoading[emp.id] ? 'A redefinir...' : '🔒 Redefinir Senha' }}
                </button>
              </div>
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
import { ref, reactive, onMounted, watch } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth.js'
import {
  useOfflinePos,
  cacheEmployees, getCachedEmployees,
  savePendingEmployeeOp,
} from '@/composables/useOfflinePos'

const { isOnline, pendingEmployeeCount, refreshPendingCount } = useOfflinePos()
const auth = useAuthStore()

const employees  = ref([])
const loading    = ref(true)

// ── Abas do formulário de adicionar ────────────────────────────────────────
const addTab = ref('create')

// ── Criar nova conta ───────────────────────────────────────────────────────
const createLoading = ref(false)
const createError   = ref('')
const createSuccess = ref('')
const showCreatePass = ref(false)
const createForm = reactive({ name: '', phone: '', email: '', password: '', role: '', permissions: [] })

function selectCreateRole(role) {
  createForm.role = role
  createForm.permissions = [...(defaultPerms[role] ?? [])]
}
function toggleCreatePerm(key) {
  const idx = createForm.permissions.indexOf(key)
  if (idx >= 0) createForm.permissions.splice(idx, 1)
  else createForm.permissions.push(key)
}

async function createAccount() {
  createError.value   = ''
  createSuccess.value = ''
  createLoading.value = true
  const payload = {
    name: createForm.name, phone: createForm.phone, email: createForm.email,
    password: createForm.password, role: createForm.role,
    permissions: createForm.permissions,
  }
  try {
    if (!isOnline.value) {
      await savePendingEmployeeOp({
        local_id: `emp_create_${Date.now()}`,
        op_type:  'create_account',
        payload,
        created_at: new Date().toISOString(),
      })
      await refreshPendingCount()
      createSuccess.value = `💾 Offline — conta de ${createForm.name} guardada. Será criada quando houver ligação.`
    } else {
      await axios.post('/pos/employees/create-account', payload)
      createSuccess.value = `Conta criada! ${createForm.name} já pode entrar com ${createForm.email}`
      await loadEmployees()
    }
    Object.assign(createForm, { name: '', phone: '', email: '', password: '', role: '', permissions: [] })
    showCreatePass.value = false
  } catch (e) {
    createError.value = e.response?.data?.errors?.email?.[0]
      ?? e.response?.data?.message ?? 'Erro ao criar conta.'
  } finally {
    createLoading.value = false
  }
}

// ── Adicionar existente ─────────────────────────────────────────────────────
const addLoading = ref(false)
const addError   = ref('')
const addSuccess = ref('')
const form = reactive({ email: '', role: '', permissions: [] })

function selectRole(role) {
  form.role = role
  form.permissions = [...(defaultPerms[role] ?? [])]
}
function togglePermission(key) {
  const idx = form.permissions.indexOf(key)
  if (idx >= 0) form.permissions.splice(idx, 1)
  else form.permissions.push(key)
}

async function addEmployee() {
  addError.value   = ''
  addSuccess.value = ''
  addLoading.value = true
  const payload = { email: form.email, role: form.role, permissions: form.permissions }
  try {
    if (!isOnline.value) {
      await savePendingEmployeeOp({
        local_id: `emp_add_${Date.now()}`,
        op_type:  'add',
        payload,
        created_at: new Date().toISOString(),
      })
      await refreshPendingCount()
      addSuccess.value = `💾 Offline — funcionário (${form.email}) guardado. Será adicionado quando houver ligação.`
      Object.assign(form, { email: '', role: '', permissions: [] })
    } else {
      await axios.post('/pos/employees', payload)
      addSuccess.value = 'Funcionário adicionado com sucesso!'
      Object.assign(form, { email: '', role: '', permissions: [] })
      await loadEmployees()
    }
  } catch (e) {
    addError.value = e.response?.data?.errors?.email?.[0]
      ?? e.response?.data?.message
      ?? 'Erro ao adicionar funcionário.'
  } finally {
    addLoading.value = false
  }
}

// ── Edição de permissões ────────────────────────────────────────────────────
const editingId  = ref(null)
const editForms  = reactive({})
const editErrors = reactive({})
const savingId   = ref(null)
const editTab    = reactive({})

function toggleEdit(emp) {
  if (editingId.value === emp.id) { editingId.value = null; return }
  editingId.value = emp.id
  editTab[emp.id] = 'perms'
  editForms[emp.id] = {
    role:        emp.role,
    permissions: [...(emp.permissions ?? defaultPerms[emp.role] ?? [])],
  }
  editErrors[emp.id] = ''
}
function setEditRole(empId, role) {
  editForms[empId].role        = role
  editForms[empId].permissions = [...(defaultPerms[role] ?? [])]
}
function toggleEditPermission(empId, key) {
  const perms = editForms[empId].permissions
  const idx   = perms.indexOf(key)
  if (idx >= 0) perms.splice(idx, 1)
  else perms.push(key)
}
async function saveEdit(emp) {
  editErrors[emp.id] = ''
  savingId.value     = emp.id
  const payload = { id: emp.id, role: editForms[emp.id].role, permissions: editForms[emp.id].permissions }
  try {
    if (!isOnline.value) {
      await savePendingEmployeeOp({
        local_id: `emp_update_${emp.id}_${Date.now()}`,
        op_type:  'update',
        payload,
        created_at: new Date().toISOString(),
      })
      await refreshPendingCount()
      // Actualizar lista local optimisticamente
      const idx = employees.value.findIndex(e => e.id === emp.id)
      if (idx >= 0) employees.value[idx] = { ...employees.value[idx], role: payload.role, permissions: payload.permissions }
      await cacheEmployees(employees.value)
      editingId.value = null
    } else {
      const { data } = await axios.put(`/pos/employees/${emp.id}`, payload)
      const idx = employees.value.findIndex(e => e.id === emp.id)
      if (idx >= 0) employees.value[idx] = { ...employees.value[idx], ...data.employee }
      editingId.value = null
    }
  } catch (e) {
    editErrors[emp.id] = e.response?.data?.message ?? 'Erro ao guardar.'
  } finally {
    savingId.value = null
  }
}

// ── Redefinir senha (pelo dono) ─────────────────────────────────────────────
const resetPassForms   = reactive({})
const resetPassLoading = reactive({})
const resetPassErrors  = reactive({})
const resetPassSuccess = reactive({})
const showResetPass    = reactive({})

async function resetPassword(emp) {
  const pass = resetPassForms[emp.id]
  if (!pass || pass.length < 6) {
    resetPassErrors[emp.id] = 'A senha deve ter pelo menos 6 caracteres.'
    return
  }
  resetPassErrors[emp.id]  = ''
  resetPassSuccess[emp.id] = ''
  resetPassLoading[emp.id] = true
  try {
    if (!isOnline.value) {
      await savePendingEmployeeOp({
        local_id: `emp_resetpw_${emp.id}_${Date.now()}`,
        op_type:  'reset_password',
        payload:  { id: emp.id, password: pass },
        created_at: new Date().toISOString(),
      })
      await refreshPendingCount()
      resetPassSuccess[emp.id] = '💾 Offline — redefinição guardada. Será aplicada quando houver ligação.'
    } else {
      await axios.put(`/pos/employees/${emp.id}/reset-password`, { password: pass })
      resetPassSuccess[emp.id] = 'Senha redefinida com sucesso.'
    }
    resetPassForms[emp.id] = ''
  } catch (e) {
    resetPassErrors[emp.id] = e.response?.data?.message ?? 'Erro ao redefinir senha.'
  } finally {
    resetPassLoading[emp.id] = false
  }
}

async function removeEmployee(emp) {
  if (!confirm(`Remover ${emp.user?.name} da equipa?`)) return
  if (!isOnline.value) {
    await savePendingEmployeeOp({
      local_id: `emp_remove_${emp.id}_${Date.now()}`,
      op_type:  'remove',
      payload:  { id: emp.id },
      created_at: new Date().toISOString(),
    })
    await refreshPendingCount()
    employees.value = employees.value.filter(e => e.id !== emp.id)
    await cacheEmployees(employees.value)
    return
  }
  await axios.delete(`/pos/employees/${emp.id}`)
  await loadEmployees()
}

// ── Dados estáticos ─────────────────────────────────────────────────────────
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

function roleLabel(role) {
  return { owner:'Proprietário', manager:'Gerente', cashier:'Vendedor', stock_keeper:'Gest. Stock', viewer:'Visualizador' }[role] ?? role
}
function roleBadge(role) {
  return { owner:'bg-bc-gold/10 text-bc-gold', manager:'bg-purple-100 text-purple-700', cashier:'bg-blue-100 text-blue-700', stock_keeper:'bg-green-100 text-green-700', viewer:'bg-gray-100 text-gray-600' }[role] ?? 'bg-gray-100 text-gray-500'
}
function permLabel(perm) {
  return { fazer_vendas:'🛒 Vendas', gerir_stock:'📦 Stock', ver_relatorios:'📊 Relatórios', gerir_equipa:'👥 Equipa', adicionar_produtos:'➕ Produtos' }[perm] ?? perm
}
function permBadge(perm) {
  return { fazer_vendas:'bg-blue-100 text-blue-700', gerir_stock:'bg-green-100 text-green-700', ver_relatorios:'bg-amber-100 text-amber-700', gerir_equipa:'bg-purple-100 text-purple-700', adicionar_produtos:'bg-teal-100 text-teal-700' }[perm] ?? 'bg-gray-100 text-gray-600'
}

async function loadEmployees() {
  loading.value = true

  // Mostrar cache imediatamente — filtrada pela loja activa
  const cached = await getCachedEmployees(auth.activeStoreId)
  if (cached) {
    employees.value = cached.value
    loading.value   = false
  }

  if (isOnline.value) {
    try {
      const { data } = await axios.get('/pos/employees')
      employees.value = data
      await cacheEmployees(data, auth.activeStoreId)
    } catch {
      // mantém cache
    }
  }

  loading.value = false
}

function switchStore(store) {
  auth.setActiveStore(store)
  loadEmployees()
}

watch(() => auth.activeStoreId, loadEmployees)

onMounted(loadEmployees)
</script>
