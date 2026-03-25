<template>
  <div class="p-4 pb-mobile max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold text-bc-gold mb-1">Posicionamento & Visibilidade</h1>
    <p class="text-bc-muted mb-8">Escolha o plano certo para a sua loja e comece a vender mais.</p>

    <!-- Plano gratuito (sem contrato) -->
    <div class="card-african border border-bc-surface-2 p-5 mb-6">
      <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
        <div>
          <h3 class="text-bc-light font-bold text-lg mb-1">🔓 Sem Plano (Gratuito)</h3>
          <p class="text-bc-muted text-sm">Acesso basico a plataforma com funcionalidades limitadas.</p>
        </div>
        <span class="text-bc-muted text-sm border border-bc-surface-2 rounded-lg px-3 py-1">Plano actual</span>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
        <div v-for="f in freePlanFeatures" :key="f.text" class="flex items-start gap-2 text-sm">
          <span :class="f.ok ? 'text-green-400' : 'text-red-400'">{{ f.ok ? '✓' : '✗' }}</span>
          <span :class="f.ok ? 'text-bc-muted' : 'text-red-400/70'">{{ f.text }}</span>
        </div>
      </div>
      <div class="bg-orange-900/20 border border-orange-500/30 rounded-lg p-3">
        <p class="text-orange-400 text-xs font-semibold mb-1">⚠ Restricoes do plano gratuito</p>
        <ul class="text-orange-300/80 text-xs space-y-0.5">
          <li>• Maximo de 100 produtos carregados</li>
          <li>• Produtos aparecem em ULTIMO lugar nas pesquisas e na apresentacao da loja</li>
          <li>• Nao pode carregar logotipo nem capa da loja — o sistema pedira upgrade</li>
          <li>• Nao tem acesso a queima de stock nem importacao de stock</li>
          <li>• Apenas 1 funcionario adicional permitido</li>
          <li>• Nao tem acesso a categorias, seccoes, POS nem Scan & Go</li>
        </ul>
      </div>
    </div>

    <!-- Planos pagos -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
      <div
        v-for="plan in enrichedPlans"
        :key="plan.id"
        class="card-african p-5 relative flex flex-col"
        :class="{
          'border-bc-gold shadow-lg shadow-bc-gold/10': plan.highlighted,
          'border-purple-500/60 shadow-lg shadow-purple-500/10': plan.isPremium,
        }"
      >
        <!-- Badge topo -->
        <div v-if="plan.badge" class="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap">
          <span class="text-xs font-bold px-3 py-1 rounded-full" :class="plan.isPremium ? 'bg-purple-600 text-white' : 'bg-bc-gold text-bc-dark'">
            {{ plan.badge }}
          </span>
        </div>

        <div class="mb-4">
          <div class="text-2xl mb-1">{{ plan.icon }}</div>
          <h3 class="text-bc-light font-bold text-lg">{{ plan.name }}</h3>
          <p class="price-tag text-2xl mt-1">{{ formatPrice(plan.price) }}</p>
          <p class="text-bc-muted text-xs mt-0.5">{{ plan.duration_days }} dias</p>
        </div>

        <!-- Barra de visibilidade -->
        <div class="mb-4">
          <div class="h-1.5 bg-bc-surface-2 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full"
              :class="plan.isPremium ? 'bg-gradient-to-r from-purple-500 to-pink-500' : 'bg-gradient-to-r from-bc-gold to-bc-orange'"
              :style="`width: ${plan.position_boost}%`"
            ></div>
          </div>
          <p class="text-bc-muted text-xs mt-1">Visibilidade: {{ plan.position_boost }}%</p>
        </div>

        <!-- Lista de features -->
        <ul class="space-y-1.5 flex-1 mb-5">
          <li v-for="f in plan.features" :key="f.text" class="flex items-start gap-2 text-xs">
            <span class="text-green-400 flex-shrink-0 mt-0.5">✓</span>
            <span class="text-bc-muted leading-snug">{{ f.text }}</span>
          </li>
          <li v-if="plan.highlightFeature" class="flex items-start gap-2 text-xs mt-2 pt-2 border-t border-bc-gold/20">
            <span class="text-bc-gold flex-shrink-0 mt-0.5">⭐</span>
            <span class="text-bc-gold font-medium leading-snug">{{ plan.highlightFeature }}</span>
          </li>
        </ul>

        <button @click="selectPlan(plan)" class="w-full py-2 text-sm font-bold rounded-xl transition" :class="plan.isPremium ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'btn-gold'">
          Contratar agora
        </button>
      </div>
    </div>

    <!-- Tabela comparativa resumida -->
    <div class="mt-8 card-african p-5 overflow-x-auto">
      <h3 class="text-bc-gold font-bold mb-4">Comparação de planos</h3>
      <table class="w-full text-sm min-w-[600px]">
        <thead>
          <tr class="border-b border-bc-gold/20">
            <th class="text-left text-bc-muted py-2 pr-4 font-normal">Funcionalidade</th>
            <th class="text-center text-bc-muted py-2 px-2 font-normal">Gratuito</th>
            <th class="text-center text-bc-muted py-2 px-2 font-normal">500</th>
            <th class="text-center text-bc-muted py-2 px-2 font-normal">1000</th>
            <th class="text-center text-bc-muted py-2 px-2 font-normal">2000</th>
            <th class="text-center text-purple-400 py-2 px-2 font-bold">15000</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in comparisonTable" :key="row.label" class="border-b border-bc-surface-2/50">
            <td class="py-2 pr-4 text-bc-muted">{{ row.label }}</td>
            <td v-for="val in row.values" :key="val" class="text-center py-2 px-2">
              <span v-if="val === true" class="text-green-400">✓</span>
              <span v-else-if="val === false" class="text-red-400/60">✗</span>
              <span v-else class="text-bc-light text-xs">{{ val }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal pagamento -->
    <div v-if="selectedPlan" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
      <div class="card-african p-6 w-full max-w-md">
        <h3 class="text-bc-gold font-bold text-lg mb-1">Contratar {{ selectedPlan.name }}</h3>
        <p class="text-bc-muted text-sm mb-4">Total: <span class="text-bc-gold font-bold">{{ formatPrice(selectedPlan.price) }}</span> · {{ selectedPlan.duration_days }} dias</p>

        <div class="space-y-3 mb-4">
          <label v-for="method in ['mpesa', 'emola']" :key="method" class="flex items-center gap-3 cursor-pointer p-3 rounded-lg border border-bc-surface-2 hover:border-bc-gold/40">
            <input type="radio" v-model="paymentMethod" :value="method" />
            <span class="text-bc-light text-sm">{{ method === 'mpesa' ? '📱 M-Pesa (Vodacom)' : '📱 eMola (Movitel)' }}</span>
          </label>
          <input v-model="paymentPhone" type="tel" placeholder="Número de telefone (ex: 84xxxxxxx)" class="input-african" />
        </div>

        <div class="flex gap-3">
          <button @click="selectedPlan = null" class="btn-ghost flex-1">Cancelar</button>
          <button @click="purchasePlan" :disabled="loading || !paymentPhone" class="btn-gold flex-1">
            {{ loading ? 'A processar...' : 'Confirmar pagamento' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const plans = ref([])
const selectedPlan = ref(null)
const paymentMethod = ref('mpesa')
const paymentPhone = ref('')
const loading = ref(false)

function formatPrice(v) {
  return new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 0 }).format(v || 0)
}

const freePlanFeatures = [
  { ok: true,  text: 'Controlo de pedidos' },
  { ok: true,  text: 'Gestao de stock' },
  { ok: true,  text: 'Maximo 100 produtos' },
  { ok: true,  text: '1 funcionario adicional' },
  { ok: false, text: 'Queima de stock' },
  { ok: false, text: 'Importacao de stock' },
  { ok: false, text: 'Categorias e seccoes' },
  { ok: false, text: 'Relatorios de vendas' },
  { ok: false, text: 'Personalizar loja (logo/capa)' },
  { ok: false, text: 'Sistema POS' },
  { ok: false, text: 'Scan & Go' },
  { ok: false, text: 'Produtos ficam em ultimo nas pesquisas' },
]

const planFeaturesMap = {
  500: [
    { text: 'Tudo do plano gratuito' },
    { text: '1 utilizador adicional fora do dono da loja' },
    { text: 'Visibilidade dos relatorios de vendas feitas no sistema' },
    { text: 'Gestao completa de Produtos / Stock / Pedidos' },
    { text: 'Importar stock via ficheiro' },
    { text: 'Produtos com maior visibilidade nas pesquisas' },
  ],
  1000: [
    { text: 'Tudo do Pacote 500' },
    { text: '2 funcionarios fora do dono da loja' },
    { text: 'Organizar a loja por filtro de categorias e seccoes' },
    { text: 'Queima de stock — ate 10 produtos por dia' },
    { text: 'Importar stock' },
    { text: 'Posicao melhorada nas pesquisas de produtos' },
  ],
  2000: [
    { text: 'Tudo do Pacote 1000' },
    { text: '5 funcionarios permitidos' },
    { text: 'Queima de stock — ate 20 produtos por dia' },
    { text: 'Acesso a personalizar o perfil da loja (logo e capa)' },
    { text: 'Posicao privilegiada nas pesquisas — posicao 2' },
    { text: 'Destaque na pagina inicial do sistema — posicao 2' },
  ],
  15000: [
    { text: 'Todos os direitos dos pacotes anteriores' },
    { text: 'Funcionarios ilimitados' },
    { text: 'Sistema POS incluso' },
    { text: 'Queima de stock — mais de 50 produtos por dia' },
    { text: '1a posicao garantida em TUDO: pesquisas, destaque, pagina inicial' },
    { text: 'Acesso ao Scan & Go (exclusivo deste pacote)' },
    { text: 'Actualizacoes do sistema em tempo real assim que sairem' },
  ],
}

const planExtras = {
  500:   { icon: '🚀', badge: null,          highlighted: false, isPremium: false, highlightFeature: 'Relatorios de vendas + importar stock' },
  1000:  { icon: '⭐', badge: 'Mais Popular', highlighted: true,  isPremium: false, highlightFeature: 'Queima de stock incluida' },
  2000:  { icon: '💎', badge: 'Recomendado',  highlighted: false, isPremium: false, highlightFeature: 'Posicao 2 em pesquisas + personalizar perfil' },
  15000: { icon: '👑', badge: 'Plano Maximo', highlighted: false, isPremium: true,  highlightFeature: '1a posicao garantida + POS + Scan & Go exclusivo' },
}

const enrichedPlans = computed(() =>
  plans.value.map(p => ({
    ...p,
    features: planFeaturesMap[p.price] ?? [{ text: p.description }],
    ...(planExtras[p.price] ?? { icon: '📦', badge: null, highlighted: false, isPremium: false, highlightFeature: null }),
  }))
)

const comparisonTable = [
  { label: 'Produtos maximos',      values: ['100',    'Ilimitado', 'Ilimitado', 'Ilimitado', 'Ilimitado'] },
  { label: 'Funcionarios',          values: ['1',      '1',         '2',         '5',         'Ilimitados'] },
  { label: 'Relatorios de vendas',  values: [false,    true,        true,        true,        true] },
  { label: 'Importar stock',        values: [false,    true,        true,        true,        true] },
  { label: 'Categorias/Seccoes',    values: [false,    false,       true,        true,        true] },
  { label: 'Queima de stock/dia',   values: [false,    false,       '10/dia',    '20/dia',    '50+/dia'] },
  { label: 'Personalizar perfil',   values: [false,    false,       false,       true,        true] },
  { label: 'Sistema POS',           values: [false,    false,       false,       false,       true] },
  { label: 'Scan & Go',             values: [false,    false,       false,       false,       true] },
  { label: 'Posicao nas pesquisas', values: ['Ultimo', 'Alta',      'Alta',      '2a pos.',   '1a pos.'] },
  { label: 'Logo e capa da loja',   values: [false,    false,       false,       true,        true] },
  { label: 'Actualizacoes auto.',   values: [false,    false,       false,       false,       true] },
]

function selectPlan(plan) { selectedPlan.value = plan }

async function purchasePlan() {
  loading.value = true
  try {
    await axios.post('/store/visibility/purchase', {
      visibility_plan_id: selectedPlan.value.id,
      payment_method: paymentMethod.value,
      payment_phone: paymentPhone.value,
    })
    alert('Pedido enviado! Complete o pagamento no seu telemóvel.')
    selectedPlan.value = null
  } catch (e) {
    alert(e.response?.data?.message || 'Erro ao processar.')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  const { data } = await axios.get('/visibility-plans')
  plans.value = data
})
</script>
