<template>
  <div class="flex flex-col h-full overflow-hidden">

    <!-- Aviso offline -->
    <div v-if="!isOnline" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border-b border-amber-200">
      <span>📵</span>
      <span>Modo offline — os movimentos serão sincronizados quando houver ligação</span>
      <span v-if="pendingMovementsCount > 0" class="ml-auto bg-amber-200 text-amber-800 rounded-full px-2 py-0.5">
        {{ pendingMovementsCount }} pendente{{ pendingMovementsCount > 1 ? 's' : '' }}
      </span>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-gray-200 bg-white px-4 overflow-x-auto">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        class="px-4 py-3 text-sm font-semibold border-b-2 transition whitespace-nowrap"
        :class="activeTab === t.key ? 'border-bc-gold text-bc-gold' : 'border-transparent text-gray-500 hover:text-gray-700'">
        {{ t.icon }} {{ t.label }}
        <span v-if="t.key === 'history' && pendingMovementsCount > 0"
          class="ml-1 bg-amber-400 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
          {{ pendingMovementsCount }}
        </span>
        <span v-if="t.key === 'expiry' && expiringCount > 0"
          class="ml-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
          {{ expiringCount }}
        </span>
      </button>
    </div>

    <div class="flex-1 overflow-y-auto p-4">

      <!-- ── Tab: Produtos por Unidade ────────────────────────────────────── -->
      <div v-if="activeTab === 'stock'">
        <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex-1 min-w-0 flex items-center gap-3">
            <input v-model="search" type="text" placeholder="🔍 Pesquisar produto..."
              class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-bc-gold" />
            <select v-model="stockFilter" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
              <option value="all">Todos</option>
              <option value="low">Stock baixo</option>
              <option value="out">Sem stock</option>
            </select>
          </div>
          <div class="flex flex-col gap-2 w-full sm:w-auto sm:flex-row sm:items-center">
            <button v-if="canPrintStock" @click="printStockList"
              class="w-full sm:w-auto px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
              🖨️ Imprimir / Guardar PDF
            </button>
            <button v-if="canPrintStock" @click="exportStockCsv"
              class="w-full sm:w-auto px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
              📄 Exportar CSV
            </button>
          </div>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 6" :key="i" class="skeleton h-16 rounded-xl"></div>
        </div>

        <div v-else class="space-y-2">
          <div v-for="p in filteredUnitProducts" :key="p.id"
            class="bg-white rounded-xl border border-gray-100 px-4 py-3 flex flex-col gap-2">
            <div class="flex items-center gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p class="font-semibold text-sm text-gray-800 truncate">{{ p.name }}</p>
                  <span v-if="p._offline" class="text-[9px] bg-amber-100 text-amber-700 font-bold px-1.5 py-0.5 rounded-full flex-shrink-0">offline</span>
                </div>
                <p class="text-xs text-gray-400">{{ p.sku || 'Sem SKU' }}</p>
              </div>
              <div class="text-center flex-shrink-0">
                <p class="text-lg font-black" :class="unitStockColor(p.stock?.quantity)">{{ p.stock?.quantity ?? 0 }}</p>
                <p class="text-[10px] text-gray-400">unidades</p>
              </div>
            </div>
            <div class="flex gap-2">
              <button @click="openMovement(p, 'in')"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-white transition hover:opacity-90"
                style="background:#22C55E;">
                + Entrada
              </button>
              <button @click="openMovement(p, 'out')"
                :disabled="(p.stock?.quantity ?? 0) <= 0"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-white bg-red-500 hover:bg-red-600 transition disabled:opacity-40">
                − Saída
              </button>
              <button @click="openMovement(p, 'adjustment')"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">
                ± Ajuste
              </button>
              <button @click="openProductHistory(p)"
                class="px-3 py-1.5 rounded-lg text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition"
                title="Ver histórico deste produto">
                📋
              </button>
            </div>
          </div>
          <p v-if="!filteredUnitProducts.length && !loading" class="text-center py-12 text-gray-400">Nenhum produto encontrado.</p>
        </div>
      </div>

      <!-- ── Tab: Produtos por Peso ────────────────────────────────────────── -->
      <div v-if="activeTab === 'weight'">
        <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex-1 min-w-0 flex items-center gap-3">
            <input v-model="weightSearch" type="text" placeholder="🔍 Pesquisar produto por peso..."
              class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-bc-gold" />
            <select v-model="weightStockFilter" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
              <option value="all">Todos</option>
              <option value="low">Stock baixo</option>
              <option value="out">Sem stock</option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 4" :key="i" class="skeleton h-16 rounded-xl"></div>
        </div>

        <div v-else class="space-y-2">
          <div v-for="p in filteredWeightProducts" :key="p.id"
            class="bg-white rounded-xl border border-gray-100 px-4 py-3 flex flex-col gap-2">
            <div class="flex items-center gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p class="font-semibold text-sm text-gray-800 truncate">{{ p.name }}</p>
                  <span class="text-[9px] bg-bc-gold/20 text-bc-gold font-bold px-1.5 py-0.5 rounded-full flex-shrink-0">⚖️ peso</span>
                  <span v-if="p._offline" class="text-[9px] bg-amber-100 text-amber-700 font-bold px-1.5 py-0.5 rounded-full flex-shrink-0">offline</span>
                </div>
                <p class="text-xs text-gray-400">
                  {{ p.sku || 'Sem SKU' }} ·
                  Unidades: {{ (p.weight_units || [p.weight_unit || 'kg']).join(', ') }}
                </p>
              </div>
              <div class="text-center flex-shrink-0">
                <p class="text-lg font-black" :class="weightStockColor(p.stock?.weight_quantity)">
                  {{ formatWeightQty(p.stock?.weight_quantity) }}
                </p>
                <p class="text-[10px] text-gray-400">{{ p.weight_unit || 'kg' }} em stock</p>
              </div>
            </div>
            <div class="flex gap-2">
              <button @click="openMovement(p, 'in')"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-white transition hover:opacity-90"
                style="background:#22C55E;">
                + Entrada
              </button>
              <button @click="openMovement(p, 'out')"
                :disabled="(p.stock?.weight_quantity ?? 0) <= 0"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-white bg-red-500 hover:bg-red-600 transition disabled:opacity-40">
                − Saída
              </button>
              <button @click="openMovement(p, 'adjustment')"
                class="flex-1 py-1.5 rounded-lg text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">
                ± Ajuste
              </button>
              <button @click="openProductHistory(p)"
                class="px-3 py-1.5 rounded-lg text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition"
                title="Ver histórico deste produto">
                📋
              </button>
            </div>
          </div>
          <p v-if="!filteredWeightProducts.length && !loading" class="text-center py-12 text-gray-400">
            Nenhum produto por peso encontrado.<br>
            <span class="text-xs text-gray-300 mt-1 block">Crie produtos com "Vendido por peso" activo para os ver aqui.</span>
          </p>
        </div>
      </div>

      <!-- ── Tab: Histórico ───────────────────────────────────────────────── -->
      <div v-if="activeTab === 'history'">

        <!-- Movimentos pendentes offline -->
        <div v-if="pendingMovements.length" class="mb-4">
          <p class="text-xs font-bold text-amber-700 mb-2">📵 Pendentes (offline)</p>
          <div class="space-y-1.5">
            <div v-for="m in pendingMovements" :key="m.local_id"
              class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-center gap-3">
              <span class="text-lg">{{ m.type === 'in' ? '📥' : m.type === 'out' ? '📤' : '⚖️' }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ m.product_name }}</p>
                <p class="text-xs text-amber-600">{{ m.reason || 'Sem motivo' }} · pendente sync</p>
              </div>
              <div class="text-right">
                <p class="font-bold text-sm" :class="m.type === 'in' ? 'text-green-600' : m.type === 'out' ? 'text-red-500' : 'text-blue-500'">
                  {{ m.type === 'in' ? '+' : m.type === 'out' ? '-' : '' }}{{ m.quantity }}
                </p>
                <p class="text-[10px] text-gray-400">{{ formatDate(m.created_at) }}</p>
              </div>
            </div>
          </div>
          <div class="border-t border-gray-200 my-3"></div>
        </div>

        <!-- Aviso offline com cache -->
        <div v-if="!isOnline && !loadingHistory && !movements.length" class="text-center py-8 text-gray-400">
          <span class="text-3xl block mb-2">📵</span>
          <p class="text-sm">Sem histórico em cache. Ligue-se para carregar.</p>
        </div>
        <div v-else-if="!isOnline && movements.length" class="flex items-center gap-2 px-3 py-2 mb-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl">
          <span>📵</span><span>Histórico offline — última sincronização guardada localmente</span>
        </div>

        <div v-else-if="loadingHistory" class="space-y-2">
          <div v-for="i in 6" :key="i" class="skeleton h-12 rounded-xl"></div>
        </div>

        <div v-else class="space-y-1.5">
          <div v-for="m in movements" :key="m.id"
            class="bg-white rounded-xl border border-gray-100 px-4 py-3 flex items-center gap-3">
            <span class="text-lg">{{ m.type === 'in' ? '📥' : m.type === 'out' ? '📤' : '⚖️' }}</span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 truncate">{{ m.product?.name }}</p>
              <p class="text-xs text-gray-400">{{ m.reason }} · {{ m.user?.name }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-sm" :class="m.type === 'in' ? 'text-green-600' : m.type === 'out' ? 'text-red-500' : 'text-blue-500'">
                {{ m.type === 'in' ? '+' : m.type === 'out' ? '-' : '' }}{{ m.quantity }}
              </p>
              <p class="text-[10px] text-gray-400">{{ m.quantity_before }} → {{ m.quantity_after }}</p>
            </div>
            <p class="text-[10px] text-gray-400 w-20 text-right">{{ formatDate(m.created_at) }}</p>
          </div>
          <p v-if="!movements.length && !loadingHistory" class="text-center py-12 text-gray-400">Sem movimentos registados.</p>
        </div>
      </div>

      <!-- ── Tab: Validades ──────────────────────────────────────────────────── -->
      <div v-if="activeTab === 'expiry'">
        <div class="flex items-center gap-3 mb-4">
          <select v-model="expiryDays" @change="loadExpiring"
            class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
            <option :value="7">Próximos 7 dias</option>
            <option :value="15">Próximos 15 dias</option>
            <option :value="30">Próximos 30 dias</option>
            <option :value="60">Próximos 60 dias</option>
            <option :value="90">Próximos 90 dias</option>
          </select>
          <span class="text-xs text-gray-400">{{ expiringItems.length }} lote(s) a expirar</span>
        </div>

        <div v-if="loadingExpiry" class="space-y-2">
          <div v-for="i in 4" :key="i" class="skeleton h-16 rounded-xl"></div>
        </div>

        <div v-else-if="!expiringItems.length" class="flex flex-col items-center justify-center py-16 text-gray-400">
          <span class="text-4xl mb-3">✅</span>
          <p class="text-sm font-semibold">Nenhum produto a expirar</p>
          <p class="text-xs mt-1 text-gray-300">Nos próximos {{ expiryDays }} dias não há validades a vencer.</p>
        </div>

        <div v-else class="space-y-2">
          <div v-for="item in expiringItems" :key="item.id"
            class="bg-white rounded-xl border px-4 py-3 flex items-center gap-3"
            :class="item.days_left < 0 ? 'border-red-300 bg-red-50' : item.days_left <= 7 ? 'border-orange-300 bg-orange-50' : 'border-yellow-200 bg-yellow-50'">
            <div class="text-2xl flex-shrink-0">
              {{ item.days_left < 0 ? '🚫' : item.days_left <= 7 ? '🔴' : '🟡' }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-sm text-gray-800 truncate">{{ item.product_name }}</p>
              <p class="text-xs text-gray-500">
                SKU: {{ item.product_sku || '—' }} ·
                Qtd. entrada: {{ item.quantity }} unid.
                <span v-if="item.entry_mode === 'box'"> ({{ item.boxes_count }}cx × {{ item.units_per_box }}un)</span>
              </p>
              <p class="text-xs font-semibold mt-0.5"
                :class="item.days_left < 0 ? 'text-red-600' : item.days_left <= 7 ? 'text-orange-600' : 'text-yellow-700'">
                {{ item.days_left < 0
                    ? `Expirado há ${Math.abs(item.days_left)} dia(s)`
                    : item.days_left === 0
                      ? 'Expira hoje — vender com urgência!'
                      : `Expira em ${item.days_left} dia(s)` }}
              </p>
            </div>
            <div class="text-right flex-shrink-0">
              <p class="text-xs font-bold text-gray-700">{{ formatDateShort(item.expiry_date) }}</p>
              <p v-if="item.acquisition_price" class="text-[10px] text-gray-400">
                Custo: {{ fmtMoney(item.acquisition_price) }}
              </p>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Modal: histórico por produto ──────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="prodHistModal.open" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4" style="background:rgba(0,0,0,0.5)" @click.self="prodHistModal.open = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col" style="max-height:85vh">
          <!-- Cabeçalho -->
          <div class="flex items-center gap-3 p-5 border-b border-gray-100">
            <div class="flex-1 min-w-0">
              <h3 class="font-bold text-base text-gray-800 truncate">📋 {{ prodHistModal.product?.name }}</h3>
              <p class="text-xs text-gray-400">SKU: {{ prodHistModal.product?.sku || '—' }}</p>
            </div>
            <button @click="prodHistModal.open = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
          </div>

          <!-- Corpo -->
          <div class="overflow-y-auto flex-1 p-4">
            <div v-if="prodHistModal.loading" class="space-y-2">
              <div v-for="i in 5" :key="i" class="skeleton h-12 rounded-xl"></div>
            </div>
            <div v-else-if="!prodHistModal.movements.length" class="text-center py-12 text-gray-400">
              <span class="text-3xl block mb-2">📭</span>
              <p class="text-sm">Nenhum movimento registado para este produto.</p>
            </div>
            <div v-else class="space-y-2">
              <div v-for="m in prodHistModal.movements" :key="m.id"
                class="bg-gray-50 rounded-xl border border-gray-100 px-4 py-3 flex items-center gap-3">
                <span class="text-xl flex-shrink-0">{{ m.type === 'in' ? '📥' : m.type === 'out' ? '📤' : '⚖️' }}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-gray-800">
                    {{ m.type === 'in' ? 'Entrada' : m.type === 'out' ? 'Saída' : 'Ajuste' }}
                    <span class="font-black" :class="m.type === 'in' ? 'text-green-600' : m.type === 'out' ? 'text-red-500' : 'text-blue-500'">
                      {{ m.type === 'in' ? '+' : m.type === 'out' ? '−' : '' }}{{ m.quantity }}
                    </span>
                  </p>
                  <p class="text-xs text-gray-400 truncate">
                    {{ m.reason || 'Sem motivo' }}
                    <span v-if="m.user?.name"> · {{ m.user.name }}</span>
                  </p>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-xs text-gray-500 font-semibold">{{ m.quantity_before }} → {{ m.quantity_after }}</p>
                  <p class="text-[10px] text-gray-400">{{ formatDate(m.created_at) }}</p>
                </div>
              </div>
            </div>

            <!-- Paginação -->
            <div v-if="prodHistModal.lastPage > 1" class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
              <button :disabled="prodHistModal.page <= 1" @click="loadProductHistoryPage(prodHistModal.page - 1)"
                class="px-4 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 disabled:opacity-40">← Anterior</button>
              <span class="text-xs text-gray-400">Página {{ prodHistModal.page }} / {{ prodHistModal.lastPage }}</span>
              <button :disabled="prodHistModal.page >= prodHistModal.lastPage" @click="loadProductHistoryPage(prodHistModal.page + 1)"
                class="px-4 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 disabled:opacity-40">Seguinte →</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Modal de movimento ──────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="movModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
          <h3 class="font-bold text-lg mb-1">
            {{ movModal.type === 'in' ? '📥 Entrada de Stock' : movModal.type === 'out' ? '📤 Saída de Stock' : '⚖️ Ajuste de Stock' }}
          </h3>
          <p class="text-sm text-gray-500 mb-1">{{ movModal.product?.name }}</p>

          <!-- Badge produto pesável -->
          <div v-if="movModal.product?.is_weighable" class="flex items-center gap-2 mb-2">
            <span class="text-xs bg-bc-gold/10 text-bc-gold font-semibold px-2 py-0.5 rounded-full">
              ⚖️ Produto por peso · {{ movModal.product?.weight_unit || 'kg' }}
            </span>
            <span class="text-xs text-gray-400">
              Stock actual: {{ formatWeightQty(movModal.product?.stock?.weight_quantity) }} {{ movModal.product?.weight_unit || 'kg' }}
            </span>
          </div>
          <div v-else class="flex items-center gap-2 mb-2">
            <span class="text-xs bg-gray-100 text-gray-600 font-semibold px-2 py-0.5 rounded-full">
              📦 Produto por unidade
            </span>
            <span class="text-xs text-gray-400">
              Stock actual: {{ movModal.product?.stock?.quantity ?? 0 }} un
            </span>
          </div>

          <p v-if="!isOnline" class="text-xs text-amber-600 font-semibold mb-4">
            📵 Offline — será sincronizado quando houver ligação
          </p>
          <p v-else class="mb-4"></p>

          <div class="space-y-3">

            <!-- Toggle entrada por caixa (apenas para entradas de produtos por unidade) -->
            <div v-if="movModal.type === 'in' && !movModal.product?.is_weighable"
              class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
              <span class="text-sm">📦 Entrada por caixa</span>
              <button @click="movModal.boxMode = !movModal.boxMode"
                class="ml-auto flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-bold transition"
                :class="movModal.boxMode ? 'bg-bc-gold text-white' : 'bg-gray-200 text-gray-600'">
                {{ movModal.boxMode ? 'Activo' : 'Inactivo' }}
              </button>
            </div>

            <!-- Modo caixa -->
            <div v-if="movModal.boxMode && movModal.type === 'in'" class="space-y-2 p-3 bg-blue-50 rounded-xl border border-blue-200">
              <p class="text-xs font-bold text-blue-700 mb-1">Configurar entrada por caixa</p>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="text-xs font-semibold text-gray-600">Un. por caixa</label>
                  <input v-model.number="movModal.unitsPerBox" type="number" min="1" step="1" placeholder="24"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-bc-gold text-center font-bold" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-gray-600">Nº de caixas</label>
                  <input v-model.number="movModal.boxesCount" type="number" min="1" step="1" placeholder="10"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-bc-gold text-center font-bold" />
                </div>
              </div>
              <div v-if="movModal.unitsPerBox > 0 && movModal.boxesCount > 0"
                class="text-center text-sm font-black text-blue-800 bg-blue-100 rounded-lg py-2">
                Total: {{ movModal.unitsPerBox * movModal.boxesCount }} unidades
              </div>
            </div>

            <!-- Quantidade manual (quando não é modo caixa) -->
            <div v-if="!movModal.boxMode">
              <label class="text-xs font-semibold text-gray-600">
                {{ movModal.type === 'adjustment' ? 'Novo total em stock' : 'Quantidade' }}
                <span v-if="movModal.product?.is_weighable" class="text-bc-gold">({{ movModal.product?.weight_unit || 'kg' }})</span>
              </label>
              <input
                v-model.number="movModal.quantity"
                type="number"
                :min="movModal.type === 'adjustment' ? '0' : '0.001'"
                :step="movModal.product?.is_weighable ? '0.001' : '1'"
                :placeholder="movModal.product?.is_weighable ? '0.000' : '1'"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mt-1 focus:outline-none focus:border-bc-gold text-lg font-bold text-center"
              />
            </div>

            <!-- Preço de aquisição (apenas para entradas) -->
            <div v-if="movModal.type === 'in'">
              <label class="text-xs font-semibold text-gray-600">Preço de aquisição deste lote (opcional)</label>
              <div class="relative mt-1">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">MZN</span>
                <input v-model.number="movModal.acquisitionPrice" type="number" min="0" step="0.01" placeholder="0.00"
                  class="w-full border border-gray-200 rounded-xl pl-12 pr-4 py-2.5 focus:outline-none focus:border-bc-gold text-sm" />
              </div>
            </div>

            <!-- Data de validade (apenas para produtos com has_expiry + entradas) -->
            <div v-if="movModal.type === 'in' && movModal.product?.has_expiry">
              <label class="text-xs font-semibold text-gray-600">Data de validade deste lote <span class="text-red-500">*</span></label>
              <input v-model="movModal.expiryDate" type="date" :min="todayStr"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
            </div>

            <div>
              <label class="text-xs font-semibold text-gray-600">Motivo (opcional)</label>
              <input v-model="movModal.reason" type="text" placeholder="ex: Compra ao fornecedor"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
            </div>
          </div>

          <div v-if="movModal.error" class="mt-3 text-red-500 text-sm text-center">{{ movModal.error }}</div>

          <div class="flex gap-3 mt-5">
            <button @click="movModal.open = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600">Cancelar</button>
            <button @click="confirmMovement"
              :disabled="movModal.loading || !movModal.quantity || movModal.quantity <= 0"
              class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm transition disabled:opacity-40"
              style="background:#F07820;">
              {{ movModal.loading ? 'A guardar...' : isOnline ? 'Confirmar' : '💾 Guardar Offline' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth.js'
import {
  useOfflinePos,
  getCachedProducts, updateCachedProduct,
  savePendingMovement, getPendingMovements,
  cacheStockHistory, getCachedStockHistory, fmtCacheAge,
} from '@/composables/useOfflinePos'

const { isOnline, trySyncNow, refreshPendingCount } = useOfflinePos()

const activeTab = ref('stock')
const tabs = [
  { key: 'stock',   icon: '📦', label: 'Produtos' },
  { key: 'weight',  icon: '⚖️', label: 'Por Peso' },
  { key: 'history', icon: '📋', label: 'Histórico' },
  { key: 'expiry',  icon: '⏰', label: 'Validades' },
]

const auth = useAuthStore()
const products         = ref([])
const movements        = ref([])
const pendingMovements = ref([])
const loading          = ref(true)
const loadingHistory   = ref(false)
const search           = ref('')
const stockFilter      = ref('all')
const weightSearch     = ref('')
const weightStockFilter = ref('all')

const pendingMovementsCount = computed(() => pendingMovements.value.length)

const movModal = ref({
  open: false, product: null, type: 'in',
  quantity: null, reason: '', loading: false, error: '',
  boxMode: false, unitsPerBox: null, boxesCount: null,
  acquisitionPrice: null, expiryDate: '',
})

// ── Validades ────────────────────────────────────────────────────────────────
const expiringItems   = ref([])
const loadingExpiry   = ref(false)
const expiryDays      = ref(30)
const expiringCount   = computed(() => expiringItems.value.length)

const todayStr = new Date().toISOString().slice(0, 10)

// ── Produtos por unidade (não pesáveis) ──────────────────────────────────────
const filteredUnitProducts = computed(() => {
  let list = products.value.filter(p => !p.is_weighable)
  if (search.value) list = list.filter(p =>
    p.name.toLowerCase().includes(search.value.toLowerCase()) ||
    (p.sku && p.sku.toLowerCase().includes(search.value.toLowerCase()))
  )
  if (stockFilter.value === 'low')  list = list.filter(p => (p.stock?.quantity ?? 0) > 0 && (p.stock?.quantity ?? 0) <= (p.stock?.minimum_stock ?? 5))
  if (stockFilter.value === 'out')  list = list.filter(p => (p.stock?.quantity ?? 0) <= 0)
  return list
})

// ── Produtos por peso (pesáveis) ─────────────────────────────────────────────
const filteredWeightProducts = computed(() => {
  let list = products.value.filter(p => p.is_weighable)
  if (weightSearch.value) list = list.filter(p =>
    p.name.toLowerCase().includes(weightSearch.value.toLowerCase()) ||
    (p.sku && p.sku.toLowerCase().includes(weightSearch.value.toLowerCase()))
  )
  if (weightStockFilter.value === 'low')  list = list.filter(p => (p.stock?.weight_quantity ?? 0) > 0 && (p.stock?.weight_quantity ?? 0) <= (p.stock?.minimum_stock ?? 0.5))
  if (weightStockFilter.value === 'out')  list = list.filter(p => (p.stock?.weight_quantity ?? 0) <= 0)
  return list
})

const canPrintStock = computed(() => auth.hasPosPermission('gerir_stock'))

function unitStockColor(qty) {
  if (!qty || qty <= 0) return 'text-red-500'
  if (qty <= 5) return 'text-yellow-500'
  return 'text-green-600'
}

function weightStockColor(qty) {
  if (!qty || qty <= 0) return 'text-red-500'
  if (qty <= 0.5) return 'text-yellow-500'
  return 'text-green-600'
}

function formatWeightQty(qty) {
  if (!qty && qty !== 0) return '0'
  const n = parseFloat(qty)
  return n % 1 === 0 ? n.toFixed(0) : n.toFixed(3).replace(/\.?0+$/, '')
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('pt-MZ', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function formatDateShort(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('pt-MZ', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function fmtMoney(v) {
  if (!v) return '—'
  return new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 }).format(v)
}

function printStockList() {
  const allVisible = [...filteredUnitProducts.value, ...filteredWeightProducts.value]
  const rows = allVisible.map((p, index) => {
    const qty = p.is_weighable
      ? `${formatWeightQty(p.stock?.weight_quantity)} ${p.weight_unit || 'kg'}`
      : (p.stock?.quantity ?? 0) + ' un'
    const min = p.stock?.minimum_stock ?? 0
    return `
      <tr>
        <td>${index + 1}</td>
        <td>${p.name}${p.is_weighable ? ' ⚖️' : ''}</td>
        <td>${p.sku || '-'}</td>
        <td>${p.barcode || '-'}</td>
        <td class="text-right">${qty}</td>
        <td class="text-right">${min}</td>
      </tr>
    `
  }).join('')

  const html = `<!doctype html>
    <html lang="pt">
      <head>
        <meta charset="utf-8" />
        <title>Lista de Stock - POS</title>
        <style>
          @page { size: A4 portrait; margin: 16mm; }
          body { font-family: Inter, sans-serif; color: #111827; margin: 0; padding: 18px; background: #fff; }
          h1 { font-size: 18px; margin-bottom: 10px; }
          p { margin: 0; }
          table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 11px; }
          th, td { border: 1px solid #D1D5DB; padding: 8px 10px; vertical-align: top; }
          th { background: #F9FAFB; text-align: left; }
          td.text-right { text-align: right; }
          tbody tr { page-break-inside: avoid; }
          .small { font-size: 10px; color: #6B7280; }
        </style>
      </head>
      <body>
        <h1>Lista de Stock - POS</h1>
        <p class="small">Gerado em ${new Date().toLocaleString('pt-MZ')}</p>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Produto</th>
              <th>SKU</th>
              <th>Barcode</th>
              <th>Stock</th>
              <th>Stock mínimo</th>
            </tr>
          </thead>
          <tbody>
            ${rows || '<tr><td colspan="6" class="small">Nenhum produto encontrado.</td></tr>'}
          </tbody>
        </table>
      </body>
    </html>`

  const win = window.open('', '_blank')
  if (!win) return
  win.document.write(html)
  win.document.close()
  win.focus()
  setTimeout(() => {
    win.print()
    win.close()
  }, 300)
}

function exportStockCsv() {
  const header = ['#', 'Produto', 'Tipo', 'SKU', 'Barcode', 'Stock', 'Unidade', 'Stock mínimo']
  const allVisible = [...filteredUnitProducts.value, ...filteredWeightProducts.value]
  const rows = allVisible.map((p, index) => [
    index + 1,
    p.name,
    p.is_weighable ? 'Peso' : 'Unidade',
    p.sku || '-',
    p.barcode || '-',
    p.is_weighable ? formatWeightQty(p.stock?.weight_quantity ?? 0) : (p.stock?.quantity ?? 0),
    p.is_weighable ? (p.weight_unit || 'kg') : 'un',
    p.stock?.minimum_stock ?? 0,
  ])
  const csv = [header, ...rows].map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `stock-pos-${new Date().toISOString().slice(0,10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

// ── Histórico por produto ────────────────────────────────────────────────────
const prodHistModal = ref({
  open: false, product: null, movements: [],
  loading: false, page: 1, lastPage: 1,
})

async function openProductHistory(product) {
  prodHistModal.value = { open: true, product, movements: [], loading: true, page: 1, lastPage: 1 }
  await loadProductHistoryPage(1)
}

async function loadProductHistoryPage(page) {
  prodHistModal.value.loading = true
  try {
    const { data } = await axios.get('/pos/stock/history', {
      params: { product_id: prodHistModal.value.product.id, page },
    })
    prodHistModal.value.movements = data.data
    prodHistModal.value.page      = data.current_page
    prodHistModal.value.lastPage  = data.last_page
  } finally {
    prodHistModal.value.loading = false
  }
}

function openMovement(product, type) {
  const defaultQty = product.is_weighable ? null : (type === 'in' ? null : 1)
  movModal.value = {
    open: true, product, type,
    quantity: defaultQty, reason: '', loading: false, error: '',
    boxMode: false, unitsPerBox: null, boxesCount: null,
    acquisitionPrice: null, expiryDate: '',
  }
}

async function confirmMovement() {
  const m = movModal.value

  // Calcular quantidade efectiva
  const effectiveQty = (m.type === 'in' && m.boxMode)
    ? (m.unitsPerBox || 0) * (m.boxesCount || 0)
    : m.quantity

  if (!effectiveQty || effectiveQty <= 0) {
    m.error = 'Indique a quantidade ou configure as caixas correctamente.'
    return
  }

  // Validade obrigatória para produtos com has_expiry em entradas
  if (m.type === 'in' && m.product?.has_expiry && !m.expiryDate) {
    m.error = 'A data de validade é obrigatória para este produto.'
    return
  }

  m.loading = true
  m.error   = ''

  const { product, type, reason } = m
  const isWeighable = product.is_weighable

  try {
    if (isOnline.value) {
      await axios.post('/pos/stock/movement', {
        product_id:        product.id,
        type,
        quantity:          m.boxMode ? null : effectiveQty,
        reason,
        entry_mode:        (type === 'in' && m.boxMode) ? 'box' : 'unit',
        units_per_box:     (type === 'in' && m.boxMode) ? m.unitsPerBox : undefined,
        boxes_count:       (type === 'in' && m.boxMode) ? m.boxesCount  : undefined,
        acquisition_price: type === 'in' ? (m.acquisitionPrice || undefined) : undefined,
        expiry_date:       (type === 'in' && m.expiryDate) ? m.expiryDate : undefined,
      })
    } else {
      const mov = {
        local_id:     `mov_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`,
        product_id:   product.id,
        product_name: product.name,
        type, quantity: effectiveQty,
        reason:       reason || '',
        created_at:   new Date().toISOString(),
      }
      await savePendingMovement(mov)
      pendingMovements.value.unshift(mov)
      await refreshPendingCount()
    }

    // Actualizar stock localmente + cache
    const p = products.value.find(x => x.id === product.id)
    if (p && p.stock) {
      if (isWeighable) {
        const wq = p.stock.weight_quantity ?? 0
        if (type === 'in')         p.stock.weight_quantity = wq + effectiveQty
        else if (type === 'out')   p.stock.weight_quantity = Math.max(0, wq - effectiveQty)
        else                       p.stock.weight_quantity = effectiveQty
      } else {
        if (type === 'in')         p.stock.quantity += effectiveQty
        else if (type === 'out')   p.stock.quantity  = Math.max(0, p.stock.quantity - effectiveQty)
        else                       p.stock.quantity  = effectiveQty
      }
      await updateCachedProduct(p)
    }

    movModal.value.open = false
    if (isOnline.value) loadHistory()

  } catch (e) {
    movModal.value.error = e.response?.data?.message ?? 'Erro ao registar.'
  } finally {
    movModal.value.loading = false
  }
}

async function loadHistory() {
  loadingHistory.value = true

  if (!isOnline.value) {
    const cached = await getCachedStockHistory()
    if (cached) movements.value = cached.value
    loadingHistory.value = false
    return
  }

  const cached = await getCachedStockHistory()
  if (cached) movements.value = cached.value

  try {
    const { data } = await axios.get('/pos/stock/history')
    movements.value = data.data
    await cacheStockHistory(data.data)
  } finally {
    loadingHistory.value = false
  }
}

async function loadProducts() {
  loading.value = true

  const cached = await getCachedProducts(auth.activeStoreId ?? auth.activeStore?.id)
  if (cached.length) {
    products.value = cached
    loading.value  = false
  }

  if (isOnline.value) {
    try {
      const { data } = await axios.get('/pos/stock')
      products.value = data
      loading.value  = false
    } catch {
      // mantém cache
    }
  }

  if (!products.value.length) loading.value = false
}

async function loadPendingMovements() {
  pendingMovements.value = await getPendingMovements()
}

async function loadExpiring() {
  if (!isOnline.value) return
  loadingExpiry.value = true
  try {
    const { data } = await axios.get('/pos/stock/expiring', { params: { days: expiryDays.value } })
    expiringItems.value = data
  } catch {
    // silencioso
  } finally {
    loadingExpiry.value = false
  }
}

watch(isOnline, async (online) => {
  if (online) {
    await trySyncNow()
    await loadProducts()
    await loadHistory()
    await loadPendingMovements()
    await loadExpiring()
  }
})

watch(activeTab, (tab) => {
  if (tab === 'expiry' && isOnline.value && !expiringItems.value.length) loadExpiring()
})

onMounted(async () => {
  await loadProducts()
  await loadPendingMovements()
  if (isOnline.value) {
    loadHistory()
    loadExpiring()
  }
})
</script>
