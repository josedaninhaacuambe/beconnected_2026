<template>
  <div class="min-h-screen flex flex-col" style="background:#F4F6F8;">
    <!-- Barra superior POS -->
    <header class="flex items-center justify-between px-4 py-2.5 shadow-sm flex-shrink-0" style="background:#1C2B3C;">
      <!-- Logo + Loja -->
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm flex-shrink-0" style="background:#F07820; color:white;">BC</div>
        <div class="relative">
          <button
            v-if="multiStore"
            @click="showStoreSel = !showStoreSel"
            class="flex items-center gap-1 hover:opacity-80 transition text-left"
          >
            <div>
              <p class="text-white font-bold text-sm leading-none">{{ storeName }}</p>
              <p class="text-white/50 text-xs">POS · {{ roleLabel }} ⌄</p>
            </div>
          </button>
          <div v-else>
            <p class="text-white font-bold text-sm leading-none">{{ storeName }}</p>
            <p class="text-white/50 text-xs">POS · {{ roleLabel }}</p>
          </div>
          <!-- Dropdown de lojas -->
          <div v-if="showStoreSel" class="absolute left-0 top-full mt-2 w-52 rounded-xl shadow-xl z-50 overflow-hidden border border-white/20" style="background:#1C2B3C;">
            <button
              v-for="store in auth.allStores"
              :key="store.id"
              @click="switchStore(store)"
              class="w-full text-left px-4 py-2.5 text-xs transition flex items-center gap-2"
              :class="store.id === auth.activeStoreId ? 'text-bc-gold font-bold' : 'text-white/70 hover:bg-white/10'"
            >
              <span>{{ store.id === auth.activeStoreId ? '✓' : '○' }}</span>
              <span class="truncate">{{ store.name }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Nav tabs -->
      <nav class="hidden sm:flex items-center gap-1">
        <RouterLink
          v-for="tab in visibleTabs" :key="tab.to"
          :to="tab.to"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition"
          :class="isActive(tab.to)
            ? 'text-white'
            : 'text-white/50 hover:text-white/80 hover:bg-white/5'"
          :style="isActive(tab.to) ? 'background:#F07820' : ''"
        >
          <span>{{ tab.icon }}</span> {{ tab.label }}
        </RouterLink>
      </nav>

      <!-- Direita: online/offline + user -->
      <div class="flex items-center gap-3">
        <!-- Indicador offline + pending -->
        <div class="flex items-center gap-1.5">
          <span
            class="w-2 h-2 rounded-full"
            :class="isOnline ? 'bg-green-400' : 'bg-red-400'"
          ></span>
          <span class="text-white/60 text-xs hidden sm:block">{{ isOnline ? 'Online' : 'Offline' }}</span>
          <span v-if="pendingCount > 0" class="bg-yellow-400 text-black text-[10px] font-bold px-1.5 py-0.5 rounded-full">
            {{ pendingCount }} pendente{{ pendingCount > 1 ? 's' : '' }}
          </span>
        </div>

        <!-- Sync manual -->
        <button
          v-if="pendingCount > 0 && isOnline"
          @click="trySyncNow"
          :disabled="syncing"
          class="text-xs text-white/70 hover:text-white border border-white/20 rounded-lg px-2 py-1 transition"
        >
          {{ syncing ? '↻' : '↑' }} Sync
        </button>

        <!-- User -->
        <div class="flex items-center gap-2 cursor-pointer" @click="showMenu = !showMenu" style="position:relative">
          <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center text-white text-xs font-bold">
            {{ initial }}
          </div>
          <div v-if="showMenu" class="absolute right-0 top-9 bg-white rounded-xl shadow-xl border border-gray-100 w-48 z-50 py-1">
            <button @click="goHome" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">🏠 Ir para loja</button>
            <button @click="showChangePass = true; showMenu = false" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">🔒 Alterar senha</button>
            <button @click="logout" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Sair</button>
          </div>
        </div>
      </div>
    </header>

    <!-- Banner de instalação PWA — só aparece se a app não estiver instalada -->
    <div
      v-if="pwaInstallable && !pwaDismissed"
      class="flex items-center justify-between gap-3 px-4 py-2 text-xs font-medium"
      style="background:#0f4c75; color:white;"
    >
      <span>📲 Instala a app para usar o POS <strong>sem internet</strong>, mesmo com o computador desligado.</span>
      <div class="flex items-center gap-2 flex-shrink-0">
        <button
          @click="installPwa"
          class="px-3 py-1 rounded-lg font-bold text-xs"
          style="background:#F07820; color:white;"
        >Instalar</button>
        <button @click="pwaDismissed = true" class="text-white/50 hover:text-white text-base leading-none">✕</button>
      </div>
    </div>

    <!-- Mobile nav → Footer em mobile -->
    <nav class="sm:hidden flex border-t fixed bottom-0 left-0 right-0 z-50" style="background:#1C2B3C;">
      <RouterLink
        v-for="tab in visibleTabs" :key="tab.to"
        :to="tab.to"
        class="flex-1 flex flex-col items-center py-2 text-[10px] font-semibold transition gap-0.5"
        :class="isActive(tab.to) ? 'text-white' : 'text-white/40'"
      >
        <span class="text-base">{{ tab.icon }}</span>
        {{ tab.label }}
      </RouterLink>
    </nav>

    <!-- Mensagem sync -->
    <div v-if="syncMessage" class="text-center text-xs py-1.5 font-semibold" style="background:#F07820; color:white;">
      {{ syncMessage }}
    </div>

    <!-- Conteúdo (com espaço para footer no mobile) -->
    <main class="flex-1 overflow-hidden pb-16 sm:pb-0">
      <RouterView />
    </main>

    <!-- Modal: Alterar senha -->
    <Teleport to="body">
      <div v-if="showChangePass" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
          <h3 class="font-black text-gray-800 mb-1">🔒 Alterar Senha</h3>
          <p class="text-xs text-gray-400 mb-4">A nova senha entra em vigor imediatamente.</p>
          <div class="space-y-3">
            <div class="relative">
              <label class="text-xs font-semibold text-gray-500">Senha actual</label>
              <input v-model="passForm.current" :type="showPass.current ? 'text' : 'password'"
                placeholder="Senha actual" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold pr-10" />
              <button type="button" @click="showPass.current = !showPass.current"
                class="absolute right-3 bottom-2.5 text-gray-400 text-xs">{{ showPass.current ? '🙈' : '👁️' }}</button>
            </div>
            <div class="relative">
              <label class="text-xs font-semibold text-gray-500">Nova senha</label>
              <input v-model="passForm.password" :type="showPass.new ? 'text' : 'password'"
                placeholder="Mínimo 8 caracteres" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold pr-10" />
              <button type="button" @click="showPass.new = !showPass.new"
                class="absolute right-3 bottom-2.5 text-gray-400 text-xs">{{ showPass.new ? '🙈' : '👁️' }}</button>
            </div>
            <div class="relative">
              <label class="text-xs font-semibold text-gray-500">Confirmar nova senha</label>
              <input v-model="passForm.password_confirmation" :type="showPass.confirm ? 'text' : 'password'"
                placeholder="Repita a nova senha" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold pr-10" />
              <button type="button" @click="showPass.confirm = !showPass.confirm"
                class="absolute right-3 bottom-2.5 text-gray-400 text-xs">{{ showPass.confirm ? '🙈' : '👁️' }}</button>
            </div>
          </div>
          <div v-if="passError" class="text-red-500 text-sm mt-3">{{ passError }}</div>
          <div v-if="passSuccess" class="text-green-600 text-sm mt-3">✅ {{ passSuccess }}</div>
          <div class="flex gap-3 mt-4">
            <button @click="closeChangePass" class="flex-1 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancelar</button>
            <button @click="changePassword" :disabled="passLoading"
              class="flex-1 py-2 rounded-xl text-white font-bold text-sm disabled:opacity-40" style="background:#F07820;">
              {{ passLoading ? 'A guardar...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useOfflinePos, prefetchPosData } from '@/composables/useOfflinePos'
import axios from 'axios'

const auth    = useAuthStore()
const route   = useRoute()
const router  = useRouter()
const showMenu = ref(false)

const { isOnline, pendingCount, syncing, syncMessage, trySyncNow } = useOfflinePos()

// Pré-carregar todos os dados POS para IndexedDB sempre que online,
// garantindo funcionamento offline em todos os ecrãs sem visita prévia.
function resolveStoreId() {
  return auth.activeStoreId
    ?? auth.activeStore?.id
    ?? auth.user?.pos_employee?.store?.id
    ?? auth.user?.pos_employee?.store_id
    ?? null
}
function runPrefetch() { prefetchPosData(resolveStoreId()) }
onMounted(runPrefetch)
watch(isOnline, (online) => { if (online) runPrefetch() })
watch(() => auth.activeStoreId, runPrefetch)
watch(() => auth.activeStore?.id, runPrefetch)

// ── Instalação PWA ────────────────────────────────────────────────────────
// Captura o evento do browser para mostrar o nosso próprio botão de instalação.
// Só aparece se a app não estiver já instalada (standalone) e o browser suportar.
const pwaInstallPrompt = ref(null)
const pwaInstallable   = ref(false)
const pwaDismissed     = ref(localStorage.getItem('pwa_install_dismissed') === '1'
                          || window.matchMedia('(display-mode: standalone)').matches)

function onBeforeInstallPrompt(e) {
  e.preventDefault()
  pwaInstallPrompt.value = e
  pwaInstallable.value   = true
}

async function installPwa() {
  if (!pwaInstallPrompt.value) return
  pwaInstallPrompt.value.prompt()
  const { outcome } = await pwaInstallPrompt.value.userChoice
  if (outcome === 'accepted') {
    pwaInstallable.value = false
    localStorage.setItem('pwa_install_dismissed', '1')
  }
  pwaInstallPrompt.value = null
}

watch(pwaDismissed, (v) => { if (v) localStorage.setItem('pwa_install_dismissed', '1') })

onMounted(() => window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt))
onUnmounted(() => window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt))

// Multi-loja
const multiStore   = computed(() => auth.allStores.length > 1)
const showStoreSel = ref(false)
function switchStore(store) {
  auth.setActiveStore(store)
  showStoreSel.value = false
  router.go(0)
}

const storeName = computed(() => auth.activeStore?.name ?? auth.user?.store?.name ?? auth.user?.name ?? 'Loja')
const initial   = computed(() => (auth.user?.name ?? 'U')[0].toUpperCase())

const roleLabel = computed(() => ({
  owner: 'Proprietário', manager: 'Gerente',
  cashier: 'Caixa', stock_keeper: 'Gestor de Stock', viewer: 'Visualizador'
}[auth.posRole] ?? 'Funcionário'))

// Tabs visíveis com base nas permissões POS do utilizador
const allTabs = [
  { to: '/pos/terminal',   icon: '🛒', label: 'Venda',       perm: 'fazer_vendas'   },
  { to: '/pos/caixa',      icon: '💰', label: 'Caixa',       perm: 'fazer_vendas'   },
  { to: '/pos/products',   icon: '📦', label: 'Produtos',    perm: null, rolesOnly: ['owner', 'manager'] },
  { to: '/pos/stock',      icon: '🗃️', label: 'Stock',       perm: 'gerir_stock'    },
  { to: '/pos/reports',    icon: '📊', label: 'Relatórios',  perm: 'ver_relatorios' },
  { to: '/pos/employees',  icon: '👥', label: 'Equipa',      perm: 'gerir_equipa'   },
]

const visibleTabs = computed(() => allTabs.filter(t => {
  // Se tem restrição de role
  if (t.rolesOnly) return t.rolesOnly.includes(auth.posRole)
  // Se tem permissão POS
  if (t.perm) return auth.hasPosPermission(t.perm)
  return true
}))

function isActive(path) { return route.path.startsWith(path) }
function goHome()       { router.push('/'); showMenu.value = false }
async function logout() { await auth.logout(); router.push('/') }

// ── Alterar senha ────────────────────────────────────────────────────────────
const showChangePass = ref(false)
const passLoading    = ref(false)
const passError      = ref('')
const passSuccess    = ref('')
const showPass       = reactive({ current: false, new: false, confirm: false })
const passForm       = reactive({ current: '', password: '', password_confirmation: '' })

function closeChangePass() {
  showChangePass.value = false
  passError.value   = ''
  passSuccess.value = ''
  Object.assign(passForm, { current: '', password: '', password_confirmation: '' })
  Object.assign(showPass, { current: false, new: false, confirm: false })
}

async function changePassword() {
  passError.value   = ''
  passSuccess.value = ''
  if (passForm.password !== passForm.password_confirmation) {
    passError.value = 'As senhas não coincidem.'
    return
  }
  if (passForm.password.length < 8) {
    passError.value = 'A nova senha deve ter pelo menos 8 caracteres.'
    return
  }
  passLoading.value = true
  try {
    await axios.post('/auth/change-password', {
      current_password:      passForm.current,
      password:              passForm.password,
      password_confirmation: passForm.password_confirmation,
    })
    passSuccess.value = 'Senha alterada com sucesso!'
    setTimeout(closeChangePass, 2000)
  } catch (e) {
    passError.value = e.response?.data?.message ?? 'Erro ao alterar senha.'
  } finally {
    passLoading.value = false
  }
}
</script>
