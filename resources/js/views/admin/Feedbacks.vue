<template>
  <div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-bc-light">Reclamações &amp; Sugestões</h1>
        <p class="text-bc-muted text-sm">Mensagens enviadas pelos utilizadores</p>
      </div>
      <span class="text-xs bg-bc-gold/10 text-bc-gold px-3 py-1 rounded-full font-semibold">
        {{ total }} mensagens
      </span>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-6">
      <select v-model="filterStatus" @change="load" class="select-african text-sm">
        <option value="">Todos os estados</option>
        <option value="novo">Novos</option>
        <option value="em_analise">Em análise</option>
        <option value="resolvido">Resolvidos</option>
      </select>
      <select v-model="filterType" @change="load" class="select-african text-sm">
        <option value="">Todos os tipos</option>
        <option value="reclamacao">Reclamações</option>
        <option value="sugestao">Sugestões</option>
        <option value="elogio">Elogios</option>
        <option value="outro">Outros</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="skeleton h-24 rounded-xl"></div>
    </div>

    <!-- Lista -->
    <div v-else-if="feedbacks.length" class="space-y-3">
      <div
        v-for="fb in feedbacks"
        :key="fb.id"
        class="card-african p-4"
        :class="fb.status === 'novo' ? 'border-l-4 border-l-bc-gold' : fb.status === 'em_analise' ? 'border-l-4 border-l-blue-400' : 'border-l-4 border-l-green-400'"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <!-- Cabeçalho -->
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <span class="text-xs font-bold rounded-full px-2 py-0.5"
                :class="{
                  'bg-red-100 text-red-600':   fb.type === 'reclamacao',
                  'bg-blue-100 text-blue-600':  fb.type === 'sugestao',
                  'bg-green-100 text-green-600':fb.type === 'elogio',
                  'bg-gray-100 text-gray-600':  fb.type === 'outro',
                }">
                {{ typeLabels[fb.type] }}
              </span>
              <span class="font-semibold text-bc-light text-sm">{{ fb.subject }}</span>
              <span class="text-xs text-bc-muted ml-auto">{{ formatDate(fb.created_at) }}</span>
            </div>

            <!-- Remetente -->
            <p class="text-xs text-bc-muted mb-2">
              De: <strong>{{ fb.user?.name || fb.name || 'Anónimo' }}</strong>
              <span v-if="fb.user?.email || fb.email"> — {{ fb.user?.email || fb.email }}</span>
            </p>

            <!-- Mensagem -->
            <p class="text-sm text-bc-light bg-bc-surface rounded-lg px-3 py-2 whitespace-pre-wrap">{{ fb.message }}</p>

            <!-- Nota admin -->
            <p v-if="fb.admin_note" class="text-xs text-blue-600 mt-2 italic">
              📝 Nota admin: {{ fb.admin_note }}
            </p>
          </div>

          <!-- Acções -->
          <div class="flex flex-col gap-2 flex-shrink-0">
            <select
              v-model="fb.status"
              @change="updateFeedback(fb)"
              class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-bc-gold"
            >
              <option value="novo">Novo</option>
              <option value="em_analise">Em análise</option>
              <option value="resolvido">Resolvido</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-16 text-bc-muted">
      Nenhuma mensagem encontrada.
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const feedbacks = ref([])
const loading = ref(true)
const total = ref(0)
const filterStatus = ref('')
const filterType = ref('')

const typeLabels = {
  reclamacao: '😠 Reclamação',
  sugestao:   '💡 Sugestão',
  elogio:     '👏 Elogio',
  outro:      '📝 Outro',
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/feedbacks', {
      params: { status: filterStatus.value || undefined, type: filterType.value || undefined }
    })
    feedbacks.value = data.data
    total.value = data.total
  } finally {
    loading.value = false
  }
}

async function updateFeedback(fb) {
  await axios.put(`/api/admin/feedbacks/${fb.id}`, { status: fb.status, admin_note: fb.admin_note })
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('pt-MZ', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(load)
</script>
