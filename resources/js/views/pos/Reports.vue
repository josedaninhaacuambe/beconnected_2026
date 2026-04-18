<template>
  <div class="overflow-y-auto h-full p-4 space-y-4">

    <!-- Banner offline -->
    <div v-if="!isOnline" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-xl">
      <span>📵</span>
      <span>Modo offline{{ isFromCache ? ` — dados guardados ${cacheAge}` : ' — sem dados em cache para este período' }}</span>
    </div>
    <div v-else-if="isFromCache" class="flex items-center gap-2 px-4 py-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-xl">
      <span>🔄</span>
      <span>A actualizar… (cache {{ cacheAge }})</span>
    </div>

    <!-- ── Cards de período fixo (sempre visíveis) ────────────────────────── -->
    <div v-if="loadingPeriods" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div v-for="i in 4" :key="i" class="skeleton h-32 rounded-xl"></div>
    </div>

    <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div v-for="p in periodCards" :key="p.key"
        class="bg-white rounded-xl border border-gray-100 p-4 space-y-1">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">{{ p.label }}</p>
        <p class="text-xl font-black" style="color:#F07820;">{{ fmt(periods[p.key]?.totalRevenue) }}</p>
        <p class="text-xs text-gray-500">{{ periods[p.key]?.totalSales ?? 0 }} vendas</p>
        <div class="border-t border-gray-100 pt-2 mt-1 flex items-center justify-between">
          <span class="text-xs text-gray-400">Lucro</span>
          <span class="text-xs font-bold"
            :class="(periods[p.key]?.grossProfit ?? 0) >= 0 ? 'text-green-600' : 'text-red-500'">
            {{ fmt(periods[p.key]?.grossProfit) }}
          </span>
        </div>
      </div>
    </div>

    <!-- ── Filtros de data + toggle canal ────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-gray-100 p-4 flex flex-wrap items-end gap-3">
      <div>
        <label class="text-xs font-semibold text-gray-500">De</label>
        <input v-model="from" type="date" class="block border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
      </div>
      <div>
        <label class="text-xs font-semibold text-gray-500">Até</label>
        <input v-model="to" type="date" class="block border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-bc-gold" />
      </div>
      <button @click="load" class="px-5 py-2 rounded-xl text-white font-bold text-sm transition hover:opacity-90" style="background:#F07820;">
        📊 Ver Relatório
      </button>
      <div class="flex gap-2 flex-wrap">
        <button @click="quickRange('today')"  class="px-3 py-2 rounded-xl border text-xs font-semibold text-gray-600 hover:border-bc-gold hover:text-bc-gold transition">Hoje</button>
        <button @click="quickRange('week')"   class="px-3 py-2 rounded-xl border text-xs font-semibold text-gray-600 hover:border-bc-gold hover:text-bc-gold transition">Esta semana</button>
        <button @click="quickRange('month')"  class="px-3 py-2 rounded-xl border text-xs font-semibold text-gray-600 hover:border-bc-gold hover:text-bc-gold transition">Este mês</button>
        <button @click="quickRange('year')"   class="px-3 py-2 rounded-xl border text-xs font-semibold text-gray-600 hover:border-bc-gold hover:text-bc-gold transition">Este ano</button>
      </div>
      <!-- Toggle canal -->
      <div class="ml-auto flex rounded-xl border border-gray-200 overflow-hidden text-xs font-bold">
        <button @click="canal = 'all'"    :class="canal === 'all'    ? 'bg-bc-gold text-white' : 'text-gray-500 hover:bg-gray-50'" class="px-3 py-2 transition">Tudo</button>
        <button @click="canal = 'pos'"    :class="canal === 'pos'    ? 'bg-bc-gold text-white' : 'text-gray-500 hover:bg-gray-50'" class="px-3 py-2 border-l border-r border-gray-200 transition">🖥️ POS</button>
        <button @click="canal = 'online'" :class="canal === 'online' ? 'bg-bc-gold text-white' : 'text-gray-500 hover:bg-gray-50'" class="px-3 py-2 transition">🌐 Online</button>
      </div>
    </div>

    <div v-if="loading" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div v-for="i in 4" :key="i" class="skeleton h-24 rounded-xl"></div>
    </div>

    <template v-else-if="data">
      <!-- Cards de resumo do período personalizado -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Receita -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-1">Receita Total</p>
          <p class="text-xl font-black" style="color:#F07820;">{{ fmt(summary.totalRevenue) }}</p>
          <p class="text-xs text-gray-400 mt-1">{{ summary.totalSales }} vendas</p>
        </div>
        <!-- Lucro Bruto -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-1">Lucro Bruto</p>
          <p class="text-xl font-black" :class="summary.grossProfit >= 0 ? 'text-green-600' : 'text-red-500'">
            {{ fmt(summary.grossProfit) }}
          </p>
          <p class="text-xs text-gray-400 mt-1">Margem {{ margin }}%</p>
        </div>
        <!-- Ticket Médio -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-1">Ticket Médio</p>
          <p class="text-xl font-black text-gray-800">{{ fmt(summary.avgTicket) }}</p>
        </div>
        <!-- IVA + POS vs Online -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-2">IVA Cobrado (POS)</p>
          <p class="text-base font-black text-green-600 mb-1">{{ fmt(summary.vatCollected) }}</p>
          <div class="flex gap-2 text-[11px] border-t border-gray-100 pt-2">
            <div class="flex-1 text-center">
              <p class="font-bold text-gray-700">{{ fmt(summary.posRevenue) }}</p>
              <p class="text-gray-400">🖥️ POS</p>
            </div>
            <div class="w-px bg-gray-100"></div>
            <div class="flex-1 text-center">
              <p class="font-bold text-gray-700">{{ fmt(summary.onlineRevenue) }}</p>
              <p class="text-gray-400">🌐 Online</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Segunda linha: Custo + Lucro breakdown -->
      <div class="bg-white rounded-xl border border-gray-100 p-4">
        <h3 class="font-bold text-sm text-gray-700 mb-3">💰 Análise de Rentabilidade</h3>
        <div class="grid grid-cols-3 gap-4">
          <div class="text-center p-3 bg-gray-50 rounded-xl">
            <p class="text-xs text-gray-400 mb-1">Receita</p>
            <p class="text-lg font-black" style="color:#F07820;">{{ fmt(summary.totalRevenue) }}</p>
          </div>
          <div class="text-center p-3 bg-red-50 rounded-xl">
            <p class="text-xs text-gray-400 mb-1">Custo dos Produtos</p>
            <p class="text-lg font-black text-red-500">{{ fmt(summary.totalCost) }}</p>
          </div>
          <div class="text-center p-3 rounded-xl" :class="summary.grossProfit >= 0 ? 'bg-green-50' : 'bg-red-50'">
            <p class="text-xs text-gray-400 mb-1">Lucro Bruto</p>
            <p class="text-lg font-black" :class="summary.grossProfit >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ fmt(summary.grossProfit) }}
            </p>
          </div>
        </div>
        <!-- Barra de margem -->
        <div class="mt-3">
          <div class="flex justify-between text-xs text-gray-400 mb-1">
            <span>Margem de Lucro</span><span class="font-bold text-gray-700">{{ margin }}%</span>
          </div>
          <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all"
              :style="{ width: Math.min(100, Math.max(0, parseFloat(margin))) + '%', background: parseFloat(margin) >= 20 ? '#22c55e' : parseFloat(margin) >= 10 ? '#F07820' : '#ef4444' }"></div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Métodos de pagamento -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <h3 class="font-bold text-sm text-gray-700 mb-3">💳 Métodos de Pagamento</h3>
          <div class="space-y-2">
            <div v-for="m in data.by_payment" :key="m.method" class="flex items-center gap-3">
              <span class="text-lg">{{ methodIcon(m.method) }}</span>
              <div class="flex-1">
                <div class="flex justify-between text-sm mb-0.5">
                  <span class="font-semibold capitalize">{{ m.method }}</span>
                  <span class="font-bold" style="color:#F07820;">{{ fmt(m.total) }}</span>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full transition-all" style="background:#F07820;"
                    :style="{ width: (summary.totalRevenue > 0 ? m.total / summary.totalRevenue * 100 : 0).toFixed(0) + '%' }"></div>
                </div>
              </div>
              <span class="text-xs text-gray-400">{{ m.count }} vendas</span>
            </div>
            <p v-if="!data.by_payment.length" class="text-center text-gray-400 text-sm py-4">Sem vendas no período.</p>
          </div>
        </div>

        <!-- Top produtos com lucro -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <h3 class="font-bold text-sm text-gray-700 mb-3">🏆 Top Produtos</h3>
          <div class="space-y-2">
            <div v-for="(p, i) in data.top_products" :key="i" class="flex items-center gap-2">
              <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black text-white flex-shrink-0"
                :style="{ background: i === 0 ? '#F07820' : i === 1 ? '#94A3B8' : '#CBD5E1' }">{{ i + 1 }}</span>
              <p class="flex-1 text-sm text-gray-700 truncate">{{ p.product_name }}</p>
              <span class="text-xs text-gray-400">{{ p.qty }} un.</span>
              <div class="text-right">
                <p class="text-xs font-bold" style="color:#F07820;">{{ fmt(p.revenue) }}</p>
                <p class="text-[10px]" :class="p.profit >= 0 ? 'text-green-600' : 'text-red-500'">
                  lucro: {{ fmt(p.profit) }}
                </p>
              </div>
            </div>
            <p v-if="!data.top_products.length" class="text-center text-gray-400 text-sm py-4">Sem vendas no período.</p>
          </div>
        </div>

        <!-- Por vendedor -->
        <div v-if="canal !== 'online'" class="bg-white rounded-xl border border-gray-100 p-4">
          <h3 class="font-bold text-sm text-gray-700 mb-3">👤 Por Vendedor (POS)</h3>
          <div class="space-y-2">
            <div v-for="s in data.by_seller" :key="s.name"
              @click="openSellerDetail(s)"
              class="flex items-center justify-between p-2 rounded-xl cursor-pointer hover:bg-gray-50 transition group">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background:#1C2B3C;">{{ s.name[0] }}</div>
                <div>
                  <span class="text-sm text-gray-700 font-semibold group-hover:text-bc-gold transition">{{ s.name }}</span>
                  <p class="text-[10px] text-gray-400">{{ s.count }} venda{{ s.count !== 1 ? 's' : '' }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <div class="text-right">
                  <p class="text-sm font-bold" style="color:#F07820;">{{ fmt(s.total) }}</p>
                </div>
                <span class="text-gray-300 group-hover:text-bc-gold transition text-xs">›</span>
              </div>
            </div>
            <p v-if="!data.by_seller.length" class="text-center text-gray-400 text-sm py-2">Sem vendas POS no período.</p>
          </div>
        </div>

        <!-- Vendas por dia -->
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <h3 class="font-bold text-sm text-gray-700 mb-3">📅 Vendas por Dia</h3>
          <div class="space-y-2">
            <div v-for="d in filteredByDay.slice(-14)" :key="d.date" class="flex items-center gap-2">
              <span class="text-xs text-gray-400 w-12 flex-shrink-0">{{ d.date }}</span>
              <div class="flex-1 h-4 bg-gray-100 rounded-full overflow-hidden flex">
                <div v-if="d.pos > 0" class="h-full transition-all" style="background:#F07820;"
                  :style="{ width: Math.max(2, d.pos / maxDayTotal * 100).toFixed(0) + '%' }"
                  :title="'POS: ' + fmt(d.pos)"></div>
                <div v-if="d.online > 0" class="h-full transition-all" style="background:#1C2B3C;"
                  :style="{ width: Math.max(2, d.online / maxDayTotal * 100).toFixed(0) + '%' }"
                  :title="'Online: ' + fmt(d.online)"></div>
              </div>
              <span class="text-xs font-bold text-gray-700 w-24 text-right">{{ fmt(d.total) }}</span>
            </div>
            <div class="flex gap-3 text-[10px] text-gray-400 mt-1">
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm inline-block" style="background:#F07820;"></span>POS</span>
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm inline-block" style="background:#1C2B3C;"></span>Online</span>
            </div>
            <p v-if="!filteredByDay.length" class="text-center text-gray-400 text-sm py-4">Sem dados no período.</p>
          </div>
        </div>
      </div>

      <!-- Tabela unificada de vendas -->
      <div class="bg-white rounded-xl border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-bold text-sm text-gray-700">🧾 Histórico de Vendas</h3>
          <div class="flex gap-1 text-xs">
            <span class="px-2 py-0.5 rounded-full" style="background:#F07820;color:white;">🖥️ POS</span>
            <span class="px-2 py-0.5 rounded-full" style="background:#1C2B3C;color:white;">🌐 Online</span>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                <th class="pb-2">Canal</th>
                <th class="pb-2">Data/Hora</th>
                <th class="pb-2">Cliente</th>
                <th class="pb-2">Itens</th>
                <th class="pb-2">Pagamento</th>
                <th class="pb-2">IVA</th>
                <th class="pb-2 text-right">Total</th>
                <th class="pb-2 text-center">Acções</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="row in unifiedSales" :key="row.id + row.canal" class="hover:bg-gray-50">
                <td class="py-2">
                  <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full text-white"
                    :style="row.canal === 'POS' ? 'background:#F07820' : 'background:#1C2B3C'">
                    {{ row.canal }}
                  </span>
                </td>
                <td class="py-2 text-xs text-gray-500">{{ formatTime(row.date) }}</td>
                <td class="py-2 text-xs text-gray-700">{{ row.customer ?? '—' }}</td>
                <td class="py-2 text-xs text-gray-500">{{ row.items }} item(s)</td>
                <td class="py-2 text-xs">{{ methodIcon(row.payment) }} {{ row.payment }}</td>
                <td class="py-2 text-xs">
                  <span v-if="row.vat_amount > 0" class="text-green-600 font-semibold">{{ fmt(row.vat_amount) }}</span>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <td class="py-2 text-xs font-bold text-right" style="color:#F07820;">{{ fmt(row.total) }}</td>
                <td class="py-2 text-center">
                  <button 
                    v-if="row.canal === 'POS'"
                    @click="deleteSale(row.id, row.customer)"
                    :disabled="deleting === row.id"
                    class="text-xs px-2 py-1 rounded text-red-600 hover:bg-red-50 transition disabled:opacity-50 disabled:cursor-not-allowed font-semibold"
                    title="Apagar venda">
                    {{ deleting === row.id ? '⏳' : '🗑️' }}
                  </button>
                  <span v-else class="text-gray-300">—</span>
                </td>
              </tr>
              <tr v-if="!unifiedSales.length">
                <td colspan="8" class="text-center py-8 text-gray-400 text-sm">Sem vendas no período.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <div v-else-if="!loadingPeriods" class="flex flex-col items-center justify-center py-16 text-gray-400">
      <span class="text-4xl mb-3">📊</span>
      <p>Selecciona um período e clica em "Ver Relatório"</p>
    </div>
  </div>

  <!-- ══ MODAL: Detalhe por Vendedor ══════════════════════════════════════ -->
  <Teleport to="body">
    <div v-if="selectedSeller" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" style="background:rgba(0,0,0,0.55);" @click.self="selectedSeller = null">
      <div class="bg-white w-full sm:max-w-2xl sm:rounded-2xl shadow-2xl flex flex-col" style="max-height:95vh; border-radius:1.25rem 1.25rem 0 0;">

        <!-- Cabeçalho -->
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 flex-shrink-0">
          <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-black flex-shrink-0" style="background:#1C2B3C;">{{ selectedSeller.name[0] }}</div>
          <div class="flex-1 min-w-0">
            <h2 class="font-black text-gray-800 text-base">{{ selectedSeller.name }}</h2>
            <p class="text-xs text-gray-400">{{ sellerSales.length }} venda{{ sellerSales.length !== 1 ? 's' : '' }} no período · Total: <strong style="color:#F07820;">{{ fmt(selectedSeller.total) }}</strong></p>
          </div>
          <button @click="selectedSeller = null" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 text-sm flex-shrink-0">✕</button>
        </div>

        <!-- Filtros de data e pesquisa -->
        <div class="flex flex-wrap gap-2 px-5 py-3 border-b border-gray-100 flex-shrink-0 bg-gray-50">
          <div class="flex items-center gap-2">
            <label class="text-xs text-gray-500 font-semibold">Filtrar dia:</label>
            <input v-model="sellerDayFilter" type="date" class="border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:border-bc-gold" />
            <button v-if="sellerDayFilter" @click="sellerDayFilter = ''" class="text-xs text-gray-400 hover:text-red-500 transition">✕ limpar</button>
          </div>
          <div class="flex gap-1 ml-auto flex-wrap">
            <button @click="sellerDayFilter = todayStr" class="px-2 py-1 rounded-lg border text-xs font-semibold text-gray-600 hover:border-bc-gold hover:text-bc-gold transition">Hoje</button>
            <button @click="sellerDayFilter = yesterdayStr" class="px-2 py-1 rounded-lg border text-xs font-semibold text-gray-600 hover:border-bc-gold hover:text-bc-gold transition">Ontem</button>
          </div>
        </div>

        <!-- Sumário do filtro activo -->
        <div v-if="sellerDayFilter" class="flex items-center gap-4 px-5 py-2 bg-bc-gold/10 text-xs text-gray-700 border-b border-bc-gold/20 flex-shrink-0">
          <span>📅 {{ sellerDayFilter }}</span>
          <span class="font-bold">{{ sellerSalesFiltered.length }} vendas</span>
          <span style="color:#F07820;" class="font-bold">{{ fmt(sellerSalesFiltered.reduce((s,x) => s + parseFloat(x.total), 0)) }}</span>
        </div>

        <!-- Lista de vendas -->
        <div class="flex-1 overflow-y-auto px-5 py-3 space-y-3">
          <div v-if="!sellerSalesFiltered.length" class="flex flex-col items-center justify-center py-12 text-gray-400">
            <span class="text-3xl mb-2">🧾</span>
            <p class="text-sm">Nenhuma venda encontrada para este filtro.</p>
          </div>

          <div v-for="sale in sellerSalesFiltered" :key="sale.id"
            class="border border-gray-100 rounded-xl overflow-hidden">
            <!-- Cabeçalho da venda -->
            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 cursor-pointer select-none"
              @click="toggleSaleExpand(sale.id)">
              <div class="flex items-center gap-3">
                <span class="text-xs font-bold px-1.5 py-0.5 rounded-full text-white" style="background:#F07820;">POS</span>
                <div>
                  <p class="text-sm font-semibold text-gray-800">{{ formatTime(sale.sale_at) }}</p>
                  <p class="text-[10px] text-gray-400">
                    {{ methodIcon(sale.payment_method) }} {{ sale.payment_method?.toUpperCase() }}
                    <span v-if="sale.customer_name"> · {{ sale.customer_name }}</span>
                    · {{ sale.items?.length ?? 0 }} item(s)
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <p class="text-sm font-black" style="color:#F07820;">{{ fmt(sale.total) }}</p>
                <span class="text-gray-400 text-xs transition-transform" :class="expandedSales.has(sale.id) ? 'rotate-90' : ''">›</span>
              </div>
            </div>

            <!-- Items expandidos -->
            <div v-if="expandedSales.has(sale.id)" class="px-4 py-3 space-y-1.5 border-t border-gray-100">
              <div v-for="item in sale.items" :key="item.id ?? item.product_name"
                class="flex justify-between text-xs text-gray-700">
                <div class="flex-1 min-w-0">
                  <span class="font-semibold">{{ item.product_name }}</span>
                  <span class="text-gray-400 ml-1.5">
                    <template v-if="item.weight_amount && item.scale_value">
                      ⚖️ {{ item.weight_amount }}{{ item.weight_unit }} · balança
                    </template>
                    <template v-else-if="item.weight_amount">
                      ⚖️ {{ item.weight_amount }}{{ item.weight_unit }} × {{ fmtCompact(item.unit_price) }}/{{ item.weight_unit }}
                    </template>
                    <template v-else>
                      {{ item.quantity }}× {{ fmtCompact(item.unit_price) }}
                    </template>
                  </span>
                </div>
                <span class="font-bold ml-2 flex-shrink-0">{{ fmtCompact(item.subtotal) }}</span>
              </div>
              <div class="border-t border-dashed border-gray-200 pt-1.5 mt-1 flex justify-between text-xs">
                <span class="text-gray-500">
                  <span v-if="sale.discount > 0">Desconto: -{{ fmtCompact(sale.discount) }} · </span>
                  <span v-if="sale.apply_vat">IVA {{ sale.vat_rate }}%: {{ fmtCompact(sale.vat_amount) }} · </span>
                  Total
                </span>
                <span class="font-black text-sm" style="color:#F07820;">{{ fmt(sale.total) }}</span>
              </div>
              <div v-if="sale.amount_paid > sale.total" class="flex justify-between text-xs text-gray-500">
                <span>Valor entregue</span><span>{{ fmtCompact(sale.amount_paid) }}</span>
              </div>
              <div v-if="(sale.amount_paid - sale.total) > 0.01" class="flex justify-between text-xs text-green-600 font-bold">
                <span>Troco</span><span>{{ fmtCompact(sale.amount_paid - sale.total) }}</span>
              </div>
              <!-- Apagar venda — apenas dono da loja -->
              <div v-if="isStoreOwner" class="flex justify-end pt-1 border-t border-dashed border-gray-100 mt-1">
                <button
                  @click.stop="deleteSellerSale(sale.id, sale.customer_name)"
                  :disabled="deletingSellerSale === sale.id"
                  class="flex items-center gap-1.5 text-xs text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded-lg transition disabled:opacity-50">
                  <span>{{ deletingSellerSale === sale.id ? '⏳' : '🗑️' }}</span>
                  {{ deletingSellerSale === sale.id ? 'A apagar...' : 'Apagar venda' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Rodapé com totais -->
        <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50 flex-shrink-0">
          <div class="flex gap-4 text-xs text-gray-500">
            <span>{{ sellerSalesFiltered.length }} vendas</span>
            <span>Ticket médio: <strong>{{ fmt(sellerSalesFiltered.length ? sellerSalesFiltered.reduce((s,x) => s + parseFloat(x.total), 0) / sellerSalesFiltered.length : 0) }}</strong></span>
          </div>
          <div class="text-right">
            <p class="text-xs text-gray-500">Total {{ sellerDayFilter ? 'do dia' : 'do período' }}</p>
            <p class="text-lg font-black" style="color:#F07820;">{{ fmt(sellerSalesFiltered.reduce((s,x) => s + parseFloat(x.total), 0)) }}</p>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useOfflinePos, cacheReports, getCachedReports, fmtCacheAge } from '@/composables/useOfflinePos'
import { useAuthStore } from '@/stores/auth.js'

const { isOnline } = useOfflinePos()
const auth = useAuthStore()
const isStoreOwner = computed(() => auth.isStoreOwner)

const from   = ref(new Date(new Date().setDate(1)).toISOString().slice(0, 10))
const to     = ref(new Date().toISOString().slice(0, 10))
const data   = ref(null)
const loading = ref(false)
const loadingPeriods = ref(true)
const canal   = ref('all')
const periods = ref({})
const deleting = ref(null)
const isFromCache = ref(false)
const cacheAge    = ref('')

// ── Detalhe por vendedor ──────────────────────────────────────────────────────
const selectedSeller    = ref(null)
const sellerDayFilter   = ref('')
const expandedSales     = ref(new Set())
const deletingSellerSale = ref(null)

const todayStr     = new Date().toISOString().slice(0, 10)
const yesterdayStr = new Date(Date.now() - 86400000).toISOString().slice(0, 10)

function openSellerDetail(seller) {
  selectedSeller.value  = seller
  sellerDayFilter.value = ''
  expandedSales.value   = new Set()
}

function toggleSaleExpand(id) {
  const s = expandedSales.value
  if (s.has(id)) s.delete(id)
  else s.add(id)
  expandedSales.value = new Set(s) // trigger reactivity
}

const sellerSales = computed(() => {
  if (!selectedSeller.value || !data.value) return []
  return (data.value.pos_sales ?? []).filter(s =>
    s.user_id === selectedSeller.value.user_id ||
    (s.user?.name ?? '') === selectedSeller.value.name
  )
})

const sellerSalesFiltered = computed(() => {
  if (!sellerDayFilter.value) return sellerSales.value
  return sellerSales.value.filter(s =>
    (s.sale_at ?? '').slice(0, 10) === sellerDayFilter.value
  )
})

function fmtCompact(v) {
  return new Intl.NumberFormat('pt-MZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0)
}

async function deleteSellerSale(saleId, customerName) {
  const label = customerName ? ` de ${customerName}` : ''
  if (!confirm(`Apagar esta venda${label}? O stock será revertido.`)) return
  deletingSellerSale.value = saleId
  try {
    await axios.delete(`/pos/sales/${saleId}`)
    await load()
    // Actualizar lista do vendedor sem fechar o modal
    if (selectedSeller.value) {
      const updated = (data.value?.by_seller ?? []).find(s => s.user_id === selectedSeller.value.user_id)
      if (updated) selectedSeller.value = updated
    }
  } catch (err) {
    alert(err.response?.data?.message ?? 'Erro ao apagar venda.')
  } finally {
    deletingSellerSale.value = null
  }
}

const periodCards = [
  { key: 'today', label: 'Hoje' },
  { key: 'week',  label: 'Esta semana' },
  { key: 'month', label: 'Este mês' },
  { key: 'year',  label: 'Este ano' },
]

// Resumo filtrado por canal
const summary = computed(() => {
  if (!data.value) return {}
  const custom = periods.value.custom ?? data.value.summary ?? {}
  if (canal.value === 'pos') {
    const sales = data.value.pos_sales ?? []
    const total = sales.reduce((s, x) => s + parseFloat(x.total), 0)
    const cost  = sales.reduce((s, x) => s + (x.items ?? []).reduce((ss, i) => ss + (i.cost_price ?? 0) * i.quantity, 0), 0)
    const vat   = sales.filter(x => x.apply_vat).reduce((s, x) => s + parseFloat(x.vat_amount ?? 0), 0)
    return {
      totalRevenue: total, totalSales: sales.length, avgTicket: sales.length ? total / sales.length : 0,
      posRevenue: total, onlineRevenue: 0, totalCost: cost, grossProfit: total - cost, vatCollected: vat,
    }
  }
  if (canal.value === 'online') {
    const orders = data.value.online_orders ?? []
    const total  = orders.reduce((s, x) => s + parseFloat(x.subtotal), 0)
    return {
      totalRevenue: total, totalSales: orders.length, avgTicket: orders.length ? total / orders.length : 0,
      posRevenue: 0, onlineRevenue: total, totalCost: 0, grossProfit: total, vatCollected: 0,
    }
  }
  return custom
})

const margin = computed(() => {
  const r = summary.value?.totalRevenue ?? 0
  const p = summary.value?.grossProfit  ?? 0
  return r > 0 ? ((p / r) * 100).toFixed(1) : '0.0'
})

const filteredByDay = computed(() => {
  if (!data.value) return []
  return data.value.by_day.map(d => ({
    ...d,
    total: canal.value === 'pos' ? d.pos : canal.value === 'online' ? d.online : d.total,
  }))
})

const maxDayTotal = computed(() => Math.max(1, ...(data.value?.by_day?.map(d => d.total) ?? [1])))

const unifiedSales = computed(() => {
  if (!data.value) return []
  const rows = []
  if (canal.value !== 'online') {
    for (const s of data.value.pos_sales ?? []) {
      rows.push({
        id: 'pos_' + s.id, canal: 'POS', date: s.sale_at,
        customer: s.customer_name, items: s.items?.length ?? 0,
        payment: s.payment_method, vat_amount: s.vat_amount ?? 0, total: s.total,
      })
    }
  }
  if (canal.value !== 'pos') {
    for (const o of data.value.online_orders ?? []) {
      rows.push({
        id: 'online_' + o.id, canal: 'Online', date: o.created_at,
        customer: null, items: o.items?.length ?? 0,
        payment: o.order?.payment_method ?? 'online', vat_amount: 0, total: o.subtotal,
      })
    }
  }
  return rows.sort((a, b) => new Date(b.date) - new Date(a.date)).slice(0, 50)
})

function fmt(v) {
  return new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' }).format(v ?? 0)
}
function methodIcon(m) {
  return { cash: '💵', mpesa: '📱', emola: '📲', credit: '🏦', online: '🌐' }[m] ?? '💳'
}
function formatTime(d) {
  return new Date(d).toLocaleString('pt-MZ', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}
function quickRange(range) {
  const now = new Date()
  if (range === 'today') {
    from.value = to.value = now.toISOString().slice(0, 10)
  } else if (range === 'week') {
    const d = new Date(now); d.setDate(d.getDate() - 6)
    from.value = d.toISOString().slice(0, 10); to.value = now.toISOString().slice(0, 10)
  } else if (range === 'month') {
    from.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
    to.value   = now.toISOString().slice(0, 10)
  } else if (range === 'year') {
    from.value = new Date(now.getFullYear(), 0, 1).toISOString().slice(0, 10)
    to.value   = now.toISOString().slice(0, 10)
  }
  load()
}

async function load() {
  loading.value     = true
  isFromCache.value = false
  cacheAge.value    = ''

  // Mostrar cache imediatamente enquanto tenta rede
  const cached = await getCachedReports(from.value, to.value)
  if (cached) {
    data.value        = cached.value
    periods.value     = cached.value.periods ?? {}
    isFromCache.value = true
    cacheAge.value    = fmtCacheAge(cached.saved_at)
    loading.value     = false
  }

  if (isOnline.value) {
    try {
      const { data: d } = await axios.get('/pos/reports', { params: { from: from.value, to: to.value } })
      data.value        = d
      periods.value     = d.periods ?? {}
      isFromCache.value = false
      cacheAge.value    = ''
      await cacheReports(from.value, to.value, d)
    } catch {
      // mantém cache se falhar
    }
  }

  loading.value = false
}

// Carregar os sumários de período ao montar
async function loadPeriods() {
  loadingPeriods.value = true
  const today     = new Date().toISOString().slice(0, 10)
  const yearStart = new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10)
  const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)

  // Mostrar cache dos cards de período imediatamente
  const cachedYear = await getCachedReports(yearStart, today)
  if (cachedYear) {
    periods.value = cachedYear.value.periods ?? {}
    data.value    = cachedYear.value
    from.value    = monthStart
    to.value      = today
  }

  if (isOnline.value) {
    try {
      const { data: d } = await axios.get('/pos/reports', { params: { from: yearStart, to: today } })
      periods.value = d.periods ?? {}
      data.value    = d
      from.value    = monthStart
      to.value      = today
      await cacheReports(yearStart, today, d)
    } catch {
      // mantém cache
    }
  }

  loadingPeriods.value = false
}

onMounted(loadPeriods)

// Deletar uma venda (apenas POS)
async function deleteSale(rowId, customerName) {
  // Extrair o ID real removendo o prefixo 'pos_'
  const saleId = rowId.replace('pos_', '')
  const customerDisplay = customerName ? ` de ${customerName}` : ''
  
  // Confirmação do utilizador
  if (!confirm(`Tem a certeza que pretende apagar esta venda${customerDisplay}? O stock será revertido automaticamente.`)) {
    return
  }
  
  deleting.value = rowId
  try {
    await axios.delete(`/pos/sales/${saleId}`)
    // Recarregar dados após deletar com sucesso
    await load()
    // Mostrar mensagem de sucesso
    alert('Venda deletada com sucesso! Stock revertido.')
  } catch (err) {
    const msg = err.response?.data?.message || 'Erro ao deletar venda'
    alert(msg)
  } finally {
    deleting.value = null
  }
}
</script>
