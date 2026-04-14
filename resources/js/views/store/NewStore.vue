<template>
  <div class="min-h-screen flex items-center justify-center p-4" style="background:#0F1923;">
    <div class="w-full max-w-lg">
      <!-- Header -->
      <div class="mb-6 flex items-center gap-3">
        <RouterLink to="/loja" class="text-white/50 hover:text-white transition text-sm">← Voltar</RouterLink>
        <h1 class="text-white font-black text-xl">Registar Nova Loja</h1>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <!-- Nome -->
        <div>
          <label class="text-white/70 text-sm block mb-1">Nome da loja *</label>
          <input v-model="form.name" type="text" placeholder="Ex: Boutique da Maria" required
            class="w-full bg-white/5 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:border-bc-gold" />
        </div>

        <!-- Categoria -->
        <div>
          <label class="text-white/70 text-sm block mb-1">Categoria *</label>
          <select v-model="form.store_category_id" required
            class="w-full bg-white/5 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-bc-gold">
            <option value="" disabled>Seleccionar categoria</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>

        <!-- Descrição -->
        <div>
          <label class="text-white/70 text-sm block mb-1">Descrição</label>
          <textarea v-model="form.description" rows="3" placeholder="Descreve a tua loja..."
            class="w-full bg-white/5 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:border-bc-gold resize-none"></textarea>
        </div>

        <!-- Telefone -->
        <div>
          <label class="text-white/70 text-sm block mb-1">Telefone</label>
          <input v-model="form.phone" type="tel" placeholder="+258 84 000 0000"
            class="w-full bg-white/5 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:border-bc-gold" />
        </div>

        <!-- Província + Cidade -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-white/70 text-sm block mb-1">Província *</label>
            <select v-model="form.province_id" @change="loadCities" required
              class="w-full bg-white/5 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-bc-gold">
              <option value="" disabled>Seleccionar</option>
              <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-white/70 text-sm block mb-1">Cidade *</label>
            <select v-model="form.city_id" required :disabled="!cities.length"
              class="w-full bg-white/5 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-bc-gold disabled:opacity-40">
              <option value="" disabled>Seleccionar</option>
              <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>

        <!-- Logo -->
        <div>
          <label class="text-white/70 text-sm block mb-1">Logo da loja</label>
          <div
            class="flex items-center gap-4 p-4 border-2 border-dashed border-white/20 rounded-xl cursor-pointer hover:border-bc-gold/60 transition"
            @click="$refs.logoRef.click()"
          >
            <div class="w-14 h-14 rounded-xl overflow-hidden bg-white/10 flex items-center justify-center flex-shrink-0">
              <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-cover" />
              <span v-else class="text-white/30 text-2xl">🏪</span>
            </div>
            <div>
              <p class="text-white/60 text-sm">{{ logoFile ? logoFile.name : 'Clica para adicionar logo' }}</p>
              <p class="text-white/30 text-xs">JPG, PNG · máx. 2MB</p>
            </div>
          </div>
          <input ref="logoRef" type="file" accept="image/*" class="hidden" @change="onLogo" />
        </div>

        <!-- Erro -->
        <p v-if="error" class="text-red-400 text-sm bg-red-400/10 rounded-xl px-4 py-3">{{ error }}</p>

        <!-- Nota sobre aprovação -->
        <p class="text-white/40 text-xs text-center">
          A nova loja ficará pendente de aprovação pela administração antes de ser publicada.
        </p>

        <!-- Botão submeter -->
        <button type="submit" :disabled="loading"
          class="w-full py-3.5 rounded-2xl font-black text-white text-base transition disabled:opacity-40"
          style="background:#F07820;"
        >
          {{ loading ? 'A registar...' : 'Registar Loja' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import axios from 'axios'

const auth   = useAuthStore()
const router = useRouter()

const loading    = ref(false)
const error      = ref('')
const categories = ref([])
const provinces  = ref([])
const cities     = ref([])
const logoFile   = ref(null)
const logoPreview = ref('')

const form = reactive({
  name: '',
  description: '',
  phone: '',
  store_category_id: '',
  province_id: '',
  city_id: '',
})

onMounted(async () => {
  const [cats, provs] = await Promise.all([
    axios.get('/store-categories'),
    axios.get('/locations/provinces'),
  ])
  categories.value = cats.data
  provinces.value  = provs.data
})

async function loadCities() {
  form.city_id = ''
  cities.value = []
  if (!form.province_id) return
  const { data } = await axios.get('/locations/cities', { params: { province_id: form.province_id } })
  cities.value = data
}

function onLogo(e) {
  const file = e.target.files[0]
  if (!file) return
  logoFile.value = file
  logoPreview.value = URL.createObjectURL(file)
}

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const fd = new FormData()
    Object.entries(form).forEach(([k, v]) => { if (v !== '' && v !== null) fd.append(k, v) })
    if (logoFile.value) fd.append('logo', logoFile.value)

    const { data } = await axios.post('/store', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    // Actualizar a lista de lojas no auth store
    const meRes = await axios.get('/auth/me')
    auth.user.stores = meRes.data.stores ?? auth.user.stores
    localStorage.setItem('bc_user', JSON.stringify({ ...auth.user, stores: meRes.data.stores }))

    // Seleccionar a nova loja e ir para o painel
    auth.setActiveStore(data)
    router.push('/loja')
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erro ao registar a loja. Tente novamente.'
  } finally {
    loading.value = false
  }
}
</script>
