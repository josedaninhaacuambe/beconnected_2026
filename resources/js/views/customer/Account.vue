<template>
  <div class="max-w-2xl mx-auto px-4 py-8">
    <h1 class="text-xl font-bold text-bc-light mb-6">A Minha Conta</h1>

    <div class="card-african p-6 mb-4">
      <div class="flex items-center gap-4 mb-6">
        <AppImg v-if="user.avatar || user.google_avatar" :src="user.avatar || user.google_avatar" class="w-16 h-16 rounded-full object-cover" />
        <div v-else class="w-16 h-16 rounded-full bg-bc-gold/20 flex items-center justify-center text-bc-gold text-2xl font-bold">
          {{ user.name?.charAt(0)?.toUpperCase() }}
        </div>
        <div>
          <p class="text-bc-light font-bold text-lg">{{ user.name }}</p>
          <p class="text-bc-muted text-sm">{{ user.email }}</p>
          <span class="text-xs px-2 py-0.5 rounded-full bg-bc-gold/20 text-bc-gold">{{ roleLabel }}</span>
        </div>
      </div>

      <form @submit.prevent="saveProfile" class="space-y-3">
        <input v-model="form.name" type="text" placeholder="Nome" class="input-african" required />
        <input v-model="form.phone" type="tel" placeholder="Telefone (ex: 841234567)" class="input-african" />
        <input v-model="form.address" type="text" placeholder="Endereço" class="input-african" />
        <p v-if="saved" class="text-green-400 text-sm">Perfil actualizado com sucesso.</p>
        <button type="submit" :disabled="saving" class="btn-green w-full py-2">
          {{ saving ? 'A guardar...' : 'Guardar Alterações' }}
        </button>
      </form>
    </div>

    <!-- Links rápidos -->
    <div class="grid grid-cols-2 gap-3">
      <RouterLink to="/conta/pedidos" class="card-african p-4 text-center hover:border-bc-gold/40 transition">
        <p class="text-2xl mb-1">📦</p>
        <p class="text-bc-light text-sm font-medium">Os Meus Pedidos</p>
      </RouterLink>
      <RouterLink to="/conta/favoritos" class="card-african p-4 text-center hover:border-bc-gold/40 transition">
        <p class="text-2xl mb-1">❤️</p>
        <p class="text-bc-light text-sm font-medium">Favoritos</p>
      </RouterLink>
      <RouterLink v-if="authStore.user?.role === 'store_owner'" to="/loja" class="card-african p-4 text-center hover:border-bc-gold/40 transition">
        <p class="text-2xl mb-1">🏪</p>
        <p class="text-bc-light text-sm font-medium">Painel da Loja</p>
      </RouterLink>
      <RouterLink v-if="authStore.user?.role === 'admin'" to="/admin" class="card-african p-4 text-center hover:border-bc-gold/40 transition">
        <p class="text-2xl mb-1">⚙️</p>
        <p class="text-bc-light text-sm font-medium">Admin</p>
      </RouterLink>
    </div>

    <button @click="logout" class="mt-6 w-full text-center text-red-400 hover:text-red-300 text-sm py-2">
      Terminar sessão
    </button>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import axios from 'axios'

const authStore = useAuthStore()
const router = useRouter()
const user = authStore.user ?? {}
const saving = ref(false)
const saved = ref(false)

const form = reactive({
  name: user.name ?? '',
  phone: user.phone ?? '',
  address: user.address ?? '',
})

const roleLabel = computed(() => ({
  customer: 'Cliente', store_owner: 'Dono de Loja', admin: 'Admin'
}[user.role] ?? user.role))

async function saveProfile() {
  saving.value = true
  try {
    const { data } = await axios.post('/auth/profile', form)
    authStore.user = data.user
    saved.value = true
    setTimeout(() => saved.value = false, 3000)
  } finally {
    saving.value = false
  }
}

async function logout() {
  await authStore.logout()
  router.push('/login')
}
</script>
