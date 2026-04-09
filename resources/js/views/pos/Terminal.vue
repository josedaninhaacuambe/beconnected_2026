<template>
  <div class="flex h-full" style="height: calc(100vh - 88px);">

    <!-- ── ESQUERDA: Produtos ─────────────────────────────────────────────── -->
    <div class="flex-1 flex flex-col overflow-hidden border-r border-gray-200">
      <!-- Barra de pesquisa / scan -->
      <div class="p-3 border-b border-gray-200 bg-white space-y-3">
        <div class="flex gap-2 items-start">
          <input
            ref="searchInput"
            v-model="search"
            @input="filterProducts"
            @keydown.enter="onSearchEnter"
            type="text"
            :placeholder="scanMode ? '📷 Aguardando leitura do scanner...' : '🔍 Pesquisar produto, SKU ou código de barras...'"
            class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-bc-gold"
          />
          <!-- Botão adicionar produto offline -->
          <button v-if="canAddProducts" @click="showAddProduct = true"
            class="px-3 py-2 rounded-xl border-2 border-dashed border-gray-300 text-gray-400 hover:border-bc-gold hover:text-bc-gold transition text-xs font-bold flex-shrink-0"
            title="Adicionar produto">
            ➕
          </button>
          <!-- Toggle scan mode -->
          <button @click="toggleScanMode"
            class="px-3 py-2 rounded-xl text-xs font-bold border-2 transition flex-shrink-0"
            :class="scanMode ? 'border-green-500 text-green-600 bg-green-50' : 'border-gray-200 text-gray-400 hover:border-gray-300'"
            title="Modo scanner">
            {{ scanMode ? '📷 SCAN ON' : '📷' }}
          </button>
        </div>

        <div class="flex flex-wrap gap-2 items-center text-xs">
          <span :class="isOnline ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-2 py-1 rounded-full font-semibold">
            {{ isOnline ? 'Online' : 'Offline' }}
          </span>
          <span v-if="pendingProductCount > 0" class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full font-semibold">
            {{ pendingProductCount }} produto(s) pendente(s)
          </span>
          <button v-if="isOnline && pendingCount > 0"
            @click="trySyncNow"
            :disabled="syncing"
            class="px-2 py-1 rounded-full text-xs font-semibold transition"
            :class="syncing ? 'bg-gray-200 text-gray-600 cursor-not-allowed' : 'bg-bc-gold text-white hover:bg-orange-500'">
            {{ syncing ? 'A sincronizar...' : 'Sincronizar agora' }}
          </button>
        </div>
        <div v-if="syncMessage" class="text-[11px] text-gray-600">{{ syncMessage }}</div>
      </div>

      <!-- Grid de produtos -->
      <div class="flex-1 overflow-y-auto p-3">
        <div v-if="loadingProducts" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
          <div v-for="i in 8" :key="i" class="skeleton h-28 rounded-xl"></div>
        </div>

        <div v-else-if="!filtered.length" class="flex flex-col items-center justify-center h-full text-gray-400">
          <span class="text-4xl mb-2">📦</span>
          <p class="text-sm">Nenhum produto encontrado</p>
        </div>

        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
          <button
            v-for="p in filtered" :key="p.id"
            @click="clickProduct(p)"
            class="relative flex flex-col items-center text-center p-3 bg-white rounded-xl border-2 border-transparent hover:border-bc-gold hover:shadow-md transition active:scale-95"
            :class="(p.stock?.quantity ?? 0) <= 0 ? 'opacity-40 cursor-not-allowed' : ''"
            :disabled="(p.stock?.quantity ?? 0) <= 0"
          >
            <div class="w-full h-16 rounded-lg overflow-hidden bg-gray-100 mb-2 flex items-center justify-center">
              <AppImg v-if="p.image" :src="p.image.startsWith('http') ? p.image : '/storage/' + p.image" class="w-full h-full object-cover" />
              <span v-else class="text-2xl">{{ p.is_weighable ? '⚖️' : '🛍️' }}</span>
            </div>
            <p class="text-xs font-semibold text-gray-800 line-clamp-2 leading-tight mb-1">{{ p.name }}</p>
            <p class="text-sm font-black" style="color:#F07820;">{{ fmt(p.price) }}</p>
            <span v-if="p.is_weighable" class="text-[9px] bg-blue-100 text-blue-700 font-bold px-1 py-0.5 rounded mt-0.5">
              ⚖️ por {{ p.weight_unit ?? 'kg' }}
            </span>
            <span v-if="p.stock && p.stock.quantity <= 5 && p.stock.quantity > 0"
              class="absolute top-1 right-1 text-[9px] bg-yellow-100 text-yellow-700 font-bold px-1 py-0.5 rounded">
              {{ p.stock.quantity }} restam
            </span>
            <span v-if="(p.stock?.quantity ?? 0) <= 0"
              class="absolute inset-0 flex items-center justify-center bg-white/70 rounded-xl text-xs font-bold text-red-500">
              Sem stock
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- ── DIREITA: Carrinho ──────────────────────────────────────────────── -->
    <div class="w-72 lg:w-80 flex flex-col bg-white">
      <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <p class="font-bold text-gray-800">🛒 Carrinho</p>
        <div class="flex items-center gap-2">
          <!-- Toggle IVA -->
          <button @click="applyVat = !applyVat"
            class="flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-lg border-2 transition"
            :class="applyVat ? 'border-green-500 text-green-600 bg-green-50' : 'border-gray-200 text-gray-400 hover:border-gray-300'">
            <span>IVA {{ vatRate }}%</span>
            <span class="w-3 h-3 rounded-full border-2 transition"
              :class="applyVat ? 'bg-green-500 border-green-500' : 'border-gray-300'"></span>
          </button>
          <button v-if="cart.length" @click="clearCart" class="text-xs text-red-400 hover:text-red-600">Limpar</button>
        </div>
      </div>

      <!-- Items -->
      <div class="flex-1 overflow-y-auto px-3 py-2 space-y-2">
        <div v-if="!cart.length" class="flex flex-col items-center justify-center h-full text-gray-400">
          <span class="text-3xl mb-1">🛒</span>
          <p class="text-xs">Carrinho vazio</p>
        </div>

        <div v-for="item in cart" :key="item.product_id + item._key"
          class="flex items-center gap-2 p-2 bg-gray-50 rounded-xl">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-gray-800 truncate">{{ item.product_name }}</p>
            <p class="text-xs text-gray-400">{{ fmt(item.unit_price) }}
              <span v-if="item.weight_amount">× {{ item.weight_amount }}{{ item.weight_unit }}</span>
            </p>
          </div>
          <div class="flex items-center gap-1">
            <button @click="changeQty(item, -1)" class="w-6 h-6 rounded-lg bg-gray-200 text-gray-600 text-xs font-bold hover:bg-gray-300 transition">−</button>
            <span class="w-6 text-center text-xs font-bold">{{ item.quantity }}</span>
            <button @click="changeQty(item, 1)" class="w-6 h-6 rounded-lg text-white text-xs font-bold transition" style="background:#F07820;">+</button>
          </div>
          <p class="text-xs font-bold text-gray-800 w-16 text-right">{{ fmt(item.subtotal) }}</p>
          <button @click="removeItem(item)" class="text-red-400 hover:text-red-600 ml-1">✕</button>
        </div>
      </div>

      <!-- Totais -->
      <div class="px-4 py-3 border-t border-gray-100 space-y-2">
        <div class="flex justify-between text-sm text-gray-600">
          <span>Subtotal</span><span>{{ fmt(subtotal) }}</span>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-gray-600">Desconto</span>
          <input v-model.number="discount" type="number" min="0"
            class="w-20 text-right border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:border-bc-gold" />
        </div>
        <div v-if="applyVat" class="flex justify-between text-sm text-green-600 font-semibold">
          <span>IVA ({{ vatRate }}%)</span><span>+ {{ fmt(vatAmount) }}</span>
        </div>
        <div class="flex justify-between font-black text-base border-t border-gray-100 pt-2">
          <span>TOTAL</span>
          <span style="color:#F07820;">{{ fmt(total) }}</span>
        </div>
        <p v-if="applyVat" class="text-[10px] text-gray-400 text-right -mt-1">
          IVA incluído: {{ fmt(vatAmount) }} · Base: {{ fmt(total - vatAmount) }}
        </p>

        <div class="grid grid-cols-3 gap-1.5 pt-1">
          <button v-for="m in payMethods" :key="m.value" @click="payMethod = m.value"
            class="py-1.5 rounded-xl text-xs font-bold border-2 transition"
            :class="payMethod === m.value ? 'border-bc-gold text-bc-gold bg-bc-gold/10' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
            {{ m.icon }} {{ m.label }}
          </button>
        </div>

        <!-- Valor entregue + troco (apenas dinheiro) -->
        <div v-if="payMethod === 'cash'" class="space-y-1.5">
          <div class="flex items-center gap-2">
            <label class="text-xs text-gray-500 flex-shrink-0">Entregue</label>
            <input v-model.number="amountPaid" type="number" min="0" step="0.01"
              :placeholder="fmt(total)"
              class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm font-bold focus:outline-none focus:border-bc-gold text-right" />
          </div>
          <!-- Atalhos rápidos -->
          <div class="flex gap-1">
            <button v-for="hint in changeHints" :key="hint"
              @click="amountPaid = hint"
              class="flex-1 py-1 rounded-lg text-[10px] font-bold bg-gray-100 text-gray-600 hover:bg-bc-gold/10 hover:text-bc-gold transition">
              {{ fmt(hint) }}
            </button>
          </div>
          <div v-if="amountPaid >= total && amountPaid > 0"
            class="flex justify-between items-center bg-green-50 border border-green-200 rounded-xl px-3 py-2">
            <span class="text-xs font-semibold text-green-700">💰 Troco</span>
            <span class="text-base font-black text-green-700">{{ fmt(change) }}</span>
          </div>
          <div v-else-if="amountPaid > 0 && amountPaid < total"
            class="text-xs text-red-500 text-center font-semibold">
            Faltam {{ fmt(total - amountPaid) }}
          </div>
        </div>

        <input v-model="customerName" type="text" placeholder="Nome do cliente (opcional)"
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-bc-gold" />

        <button @click="finalizeSale" :disabled="!cart.length || processing || (payMethod === 'cash' && amountPaid > 0 && amountPaid < total)"
          class="w-full py-3 rounded-xl font-black text-white text-sm transition hover:opacity-90 active:scale-95 disabled:opacity-40"
          style="background:#F07820;">
          {{ processing ? 'A registar...' : isOnline ? '✅ Confirmar Venda' : '📥 Guardar Offline' }}
        </button>
      </div>
    </div>

    <!-- ══ MODAL: Escolha de modo scan ══════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showScanChoice" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
          <h3 class="font-black text-xl text-gray-800 text-center mb-2">Como vai vender hoje?</h3>
          <p class="text-sm text-gray-400 text-center mb-6">Escolha o modo de venda para esta sessão.</p>
          <div class="grid grid-cols-2 gap-4">
            <button @click="chooseScanMode(true)"
              class="flex flex-col items-center p-5 rounded-xl border-2 border-gray-200 hover:border-bc-gold hover:bg-bc-gold/5 transition">
              <span class="text-4xl mb-2">📷</span>
              <p class="font-black text-gray-800">Com Scanner</p>
              <p class="text-xs text-gray-400 mt-1 text-center">Leitor de código de barras</p>
            </button>
            <button @click="chooseScanMode(false)"
              class="flex flex-col items-center p-5 rounded-xl border-2 border-gray-200 hover:border-bc-gold hover:bg-bc-gold/5 transition">
              <span class="text-4xl mb-2">👆</span>
              <p class="font-black text-gray-800">Sem Scanner</p>
              <p class="text-xs text-gray-400 mt-1 text-center">Pesquisa manual de produtos</p>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══ MODAL: Produto por peso ════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="weightProduct" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-6">
          <h3 class="font-black text-gray-800 mb-1">⚖️ {{ weightProduct.name }}</h3>
          <p class="text-sm text-gray-400 mb-4">Preço: {{ fmt(weightProduct.price) }} / {{ weightProduct.weight_unit ?? 'kg' }}</p>
          <div class="space-y-3">
            <!-- Unidade de medida -->
            <div>
              <label class="text-xs font-semibold text-gray-500">Unidade</label>
              <div class="flex gap-2 mt-1">
                <button v-for="u in weightUnits" :key="u"
                  @click="weightForm.unit = u"
                  class="flex-1 py-2 rounded-xl border-2 text-sm font-bold transition"
                  :class="weightForm.unit === u ? 'border-bc-gold text-bc-gold bg-bc-gold/10' : 'border-gray-200 text-gray-500'">
                  {{ u }}
                </button>
              </div>
            </div>
            <!-- Quantidade -->
            <div>
              <label class="text-xs font-semibold text-gray-500">Quantidade</label>
              <input v-model.number="weightForm.amount" type="number" step="0.001" min="0.001"
                placeholder="ex: 0.750"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mt-1 text-lg font-bold focus:outline-none focus:border-bc-gold text-center" />
            </div>
            <!-- Total calculado -->
            <div class="bg-gray-50 rounded-xl p-3 text-center">
              <p class="text-xs text-gray-400">Total a cobrar</p>
              <p class="text-2xl font-black" style="color:#F07820;">{{ fmt(weightTotal) }}</p>
              <p class="text-xs text-gray-400">{{ weightForm.amount || 0 }} {{ weightForm.unit }} × {{ fmt(weightProduct.price) }}/{{ weightProduct.weight_unit ?? 'kg' }}</p>
            </div>
          </div>
          <div class="flex gap-3 mt-4">
            <button @click="weightProduct = null" class="flex-1 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancelar</button>
            <button @click="confirmWeight" :disabled="!weightForm.amount"
              class="flex-1 py-2 rounded-xl text-white font-bold text-sm transition disabled:opacity-40"
              style="background:#F07820;">Adicionar</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══ MODAL: Adicionar produto offline ══════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showAddProduct" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 max-h-[90vh] overflow-y-auto">
          <h3 class="font-black text-gray-800 mb-4">➕ Novo Produto</h3>
          <div class="space-y-3">
            <div>
              <label class="text-xs font-semibold text-gray-500">Nome do produto *</label>
              <input v-model="newProduct.name" type="text" placeholder="Nome"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="text-xs font-semibold text-gray-500">Preço de venda *</label>
                <input v-model.number="newProduct.price" type="number" step="0.01" min="0" placeholder="0.00"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-500">Preço de custo</label>
                <input v-model.number="newProduct.cost_price" type="number" step="0.01" min="0" placeholder="0.00"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="text-xs font-semibold text-gray-500">SKU / Código</label>
                <input v-model="newProduct.sku" type="text" placeholder="SKU"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-500">Stock inicial</label>
                <input v-model.number="newProduct.initial_stock" type="number" min="0" placeholder="0"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 mt-1 text-sm focus:outline-none focus:border-bc-gold" />
              </div>
            </div>
            <!-- Produto por peso -->
            <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-200">
              <button type="button" @click="newProduct.is_weighable = !newProduct.is_weighable"
                class="w-10 h-6 rounded-full transition flex items-center px-1"
                :class="newProduct.is_weighable ? 'bg-bc-gold justify-end' : 'bg-gray-200 justify-start'">
                <span class="w-4 h-4 bg-white rounded-full shadow"></span>
              </button>
              <div>
                <p class="text-sm font-semibold text-gray-700">⚖️ Vendido por peso</p>
                <p class="text-xs text-gray-400">Cereais, legumes, frutas...</p>
              </div>
            </div>
            <!-- Apenas no POS -->
            <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-200">
              <button type="button" @click="newProduct.pos_only = !newProduct.pos_only"
                class="w-10 h-6 rounded-full transition flex items-center px-1"
                :class="newProduct.pos_only ? 'bg-blue-500 justify-end' : 'bg-gray-200 justify-start'">
                <span class="w-4 h-4 bg-white rounded-full shadow"></span>
              </button>
              <div>
                <p class="text-sm font-semibold text-gray-700">🏪 Apenas no POS</p>
                <p class="text-xs text-gray-400">Não aparece na loja online</p>
              </div>
            </div>
            <div v-if="newProduct.is_weighable">
              <label class="text-xs font-semibold text-gray-500">Unidade de medida</label>
              <div class="flex gap-2 mt-1">
                <button v-for="u in ['g','kg','l','ml']" :key="u"
                  type="button" @click="newProduct.weight_unit = u"
                  class="flex-1 py-1.5 rounded-lg border-2 text-xs font-bold transition"
                  :class="newProduct.weight_unit === u ? 'border-bc-gold text-bc-gold' : 'border-gray-200 text-gray-500'">
                  {{ u }}
                </button>
              </div>
            </div>
          </div>
          <div v-if="addProductError" class="text-red-500 text-sm mt-3">{{ addProductError }}</div>
          <div class="flex gap-3 mt-4">
            <button @click="showAddProduct = false" class="flex-1 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancelar</button>
            <button @click="saveNewProduct" :disabled="!newProduct.name || !newProduct.price"
              class="flex-1 py-2 rounded-xl text-white font-bold text-sm disabled:opacity-40"
              style="background:#F07820;">
              {{ isOnline ? 'Criar Produto' : '💾 Guardar Offline' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══ MODAL: Recibo ══════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="receipt" class="fixed inset-0 z-50 flex items-center justify-center p-2" style="background:rgba(0,0,0,0.7)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xs flex flex-col" style="max-height:95vh;">
          <!-- Cabeçalho modal -->
          <div class="px-4 pt-4 pb-2 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <span class="text-sm font-bold text-gray-700">✅ Venda Registada</span>
            <span class="text-xs text-gray-400">{{ isOnline ? 'Sincronizada' : '⚠️ Offline' }}</span>
          </div>

          <!-- Área de impressão térmica -->
          <div class="flex-1 overflow-y-auto">
            <div id="pos-receipt" class="p-4" style="font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.5;">
              <!-- Cabeçalho -->
              <div style="text-align:center; margin-bottom:8px;">
                <div style="font-size:16px; font-weight:900; letter-spacing:2px;">BECONNECT</div>
                <div style="font-size:10px; color:#666;">{{ auth.user?.store?.name ?? 'Loja' }}</div>
                <div style="font-size:10px; color:#666;">{{ formatDateTime(receipt.sale_at) }}</div>
                <div style="font-size:10px; color:#666;">{{ receipt.local_id }}</div>
              </div>

              <div style="border-top:1px dashed #ccc; margin:6px 0;"></div>

              <!-- Itens -->
              <div v-for="item in receipt.items" :key="item._key ?? item.product_name" style="margin-bottom:4px;">
                <div style="font-weight:700; font-size:11px;">{{ item.product_name }}</div>
                <div style="display:flex; justify-content:space-between; color:#444; font-size:11px;">
                  <span v-if="item.weight_amount">
                    {{ item.weight_amount }}{{ item.weight_unit }} × {{ fmtN(item.unit_price) }}
                  </span>
                  <span v-else>
                    {{ item.quantity }} × {{ fmtN(item.unit_price) }}
                  </span>
                  <span style="font-weight:700;">{{ fmtN(item.subtotal) }}</span>
                </div>
              </div>

              <div style="border-top:1px dashed #ccc; margin:6px 0;"></div>

              <!-- Totais -->
              <div style="display:flex; justify-content:space-between; font-size:11px;">
                <span>Subtotal</span><span>{{ fmtN(receipt.subtotal) }}</span>
              </div>
              <div v-if="receipt.discount > 0" style="display:flex; justify-content:space-between; font-size:11px; color:#d00;">
                <span>Desconto</span><span>- {{ fmtN(receipt.discount) }}</span>
              </div>
              <div v-if="receipt.apply_vat" style="display:flex; justify-content:space-between; font-size:11px; color:#080;">
                <span>IVA ({{ receipt.vat_rate }}%)</span><span>+ {{ fmtN(receipt.vat_amount) }}</span>
              </div>

              <div style="border-top:2px solid #000; margin:6px 0;"></div>

              <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:900;">
                <span>TOTAL</span><span>{{ fmtN(receipt.total) }}</span>
              </div>

              <div style="border-top:1px dashed #ccc; margin:6px 0;"></div>

              <!-- Pagamento -->
              <div style="display:flex; justify-content:space-between; font-size:11px;">
                <span>Forma de pagamento</span>
                <span style="font-weight:700; text-transform:uppercase;">{{ receipt.payment_method }}</span>
              </div>
              <div v-if="receipt.amount_paid > 0" style="display:flex; justify-content:space-between; font-size:11px;">
                <span>Valor entregue</span><span>{{ fmtN(receipt.amount_paid) }}</span>
              </div>
              <div v-if="receipt.change > 0" style="display:flex; justify-content:space-between; font-size:13px; font-weight:900; color:#080;">
                <span>TROCO</span><span>{{ fmtN(receipt.change) }}</span>
              </div>

              <div v-if="receipt.customer_name" style="margin-top:4px; font-size:11px;">
                Cliente: <strong>{{ receipt.customer_name }}</strong>
              </div>

              <div v-if="receipt.apply_vat" style="margin-top:6px; font-size:10px; color:#666; border-top:1px dashed #ccc; padding-top:4px;">
                Base tributável: {{ fmtN(receipt.total - receipt.vat_amount) }}
                · IVA {{ receipt.vat_rate }}%: {{ fmtN(receipt.vat_amount) }}
              </div>

              <div style="border-top:1px dashed #ccc; margin:8px 0;"></div>

              <div style="text-align:center; font-size:10px; color:#666;">
                <div>Obrigado pela sua compra!</div>
                <div>beconnect.co.mz</div>
              </div>
            </div>
          </div>

          <!-- Botões -->
          <div class="flex gap-2 p-3 border-t border-gray-100 flex-shrink-0">
            <button @click="printReceipt" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">🖨️ Imprimir</button>
            <button @click="newSale" class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm transition" style="background:#F07820;">Nova Venda</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, watch } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import {
  useOfflinePos, cacheProducts, getCachedProducts,
  savePendingSale, savePendingProduct, getPendingProducts,
} from '@/composables/useOfflinePos'

const auth = useAuthStore()
const { isOnline, pendingCount, pendingProductCount, syncing, syncMessage, trySyncNow, refreshPendingCount } = useOfflinePos()

const canAddProducts = computed(() => auth.hasPosPermission('adicionar_produtos'))

// ── Estado principal ───────────────────────────────────────────────────────
const allProducts  = ref([])
const filtered     = ref([])
const search       = ref('')
const searchInput  = ref(null)
const cart         = ref([])
const discount     = ref(0)
const applyVat     = ref(false)
const vatRate      = ref(17)
const payMethod    = ref('cash')
const customerName = ref('')
const processing   = ref(false)
const receipt      = ref(null)
const loadingProducts = ref(true)

// ── Modo scanner ────────────────────────────────────────────────────────────
const showScanChoice = ref(false)
const scanMode = ref(localStorage.getItem('pos_scan_mode') === 'true')

function chooseScanMode(mode) {
  scanMode.value = mode
  localStorage.setItem('pos_scan_mode', mode)
  showScanChoice.value = false
  if (mode && searchInput.value) searchInput.value.focus()
}

function toggleScanMode() {
  scanMode.value = !scanMode.value
  localStorage.setItem('pos_scan_mode', scanMode.value)
  if (scanMode.value && searchInput.value) searchInput.value.focus()
}

// Em modo scan, Enter adiciona o produto encontrado ao carrinho
function onSearchEnter() {
  if (!scanMode.value || !filtered.value.length) return
  const p = filtered.value[0]
  if (p && (p.stock?.quantity ?? 0) > 0) {
    clickProduct(p)
    search.value = ''
    filterProducts()
    if (searchInput.value) searchInput.value.focus()
  }
}

// ── Produto por peso ────────────────────────────────────────────────────────
const weightProduct = ref(null)
const weightForm = reactive({ amount: '', unit: 'kg' })
const weightUnits = ['g', 'kg', 'l', 'ml']

const weightTotal = computed(() => {
  if (!weightProduct.value || !weightForm.amount) return 0
  const price = parseFloat(weightProduct.value.price)
  let amount  = parseFloat(weightForm.amount)
  // Converter g → kg se o produto é por kg
  const prodUnit = weightProduct.value.weight_unit ?? 'kg'
  if (prodUnit === 'kg' && weightForm.unit === 'g') amount = amount / 1000
  if (prodUnit === 'g'  && weightForm.unit === 'kg') amount = amount * 1000
  return price * amount
})

function clickProduct(p) {
  if (p.is_weighable) {
    weightProduct.value = p
    weightForm.amount = ''
    weightForm.unit = p.weight_unit ?? 'kg'
  } else {
    addToCart(p)
  }
}

function confirmWeight() {
  if (!weightProduct.value || !weightForm.amount) return
  const p       = weightProduct.value
  const price   = parseFloat(p.price)
  const amount  = parseFloat(weightForm.amount)
  const key     = `${p.id}_${Date.now()}`
  cart.value.push({
    _key:         key,
    product_id:   p.id,
    product_name: p.name,
    product_sku:  p.sku,
    unit_price:   price,
    cost_price:   parseFloat(p.cost_price ?? 0),
    quantity:     1,
    weight_amount: amount,
    weight_unit:  weightForm.unit,
    subtotal:     parseFloat(weightTotal.value.toFixed(2)),
  })
  weightProduct.value = null
}

// ── Adicionar produto offline ───────────────────────────────────────────────
const showAddProduct  = ref(false)
const addProductError = ref('')
const newProduct = reactive({
  name: '', price: '', cost_price: '', sku: '',
  initial_stock: 0, is_weighable: false, weight_unit: 'kg', pos_only: false,
})

async function saveNewProduct() {
  addProductError.value = ''
  const localId = `prod_local_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`
  const prod = {
    local_id:     localId,
    name:         newProduct.name,
    price:        parseFloat(newProduct.price) || 0,
    cost_price:   parseFloat(newProduct.cost_price) || 0,
    sku:          newProduct.sku || null,
    initial_stock: parseInt(newProduct.initial_stock) || 0,
    is_weighable: newProduct.is_weighable,
    weight_unit:  newProduct.weight_unit,
    pos_only:     newProduct.pos_only,
  }

  try {
    if (isOnline.value) {
      await axios.post('/pos/sync-products', { products: [prod] })
      await loadProducts()
    } else {
      await savePendingProduct(prod)
      await refreshPendingCount()
      const tempId = -Date.now()
      const localProd = {
        id: tempId, local_id: localId,
        name: prod.name, price: prod.price, cost_price: prod.cost_price,
        sku: prod.sku, is_weighable: prod.is_weighable, weight_unit: prod.weight_unit,
        pos_only: prod.pos_only,
        image: null, stock: { quantity: prod.initial_stock },
      }
      allProducts.value.push(localProd)
      filterProducts()
    }
    showAddProduct.value = false
    Object.assign(newProduct, { name:'', price:'', cost_price:'', sku:'', initial_stock:0, is_weighable:false, weight_unit:'kg', pos_only:false })
  } catch (e) {
    addProductError.value = e.response?.data?.message ?? 'Erro ao criar produto.'
  }
}

// ── Carrinho ────────────────────────────────────────────────────────────────
const payMethods = [
  { value: 'cash',  icon: '💵', label: 'Dinheiro' },
  { value: 'mpesa', icon: '📱', label: 'M-Pesa' },
  { value: 'emola', icon: '📲', label: 'eMola' },
]

const amountPaid = ref(0)
const subtotal  = computed(() => cart.value.reduce((s, i) => s + i.subtotal, 0))
const afterDisc = computed(() => Math.max(0, subtotal.value - (discount.value || 0)))
const vatAmount = computed(() => applyVat.value
  ? parseFloat((afterDisc.value - afterDisc.value / (1 + vatRate.value / 100)).toFixed(2))
  : 0
)
const total  = computed(() => afterDisc.value)
const change = computed(() => payMethod.value === 'cash' && amountPaid.value > total.value
  ? parseFloat((amountPaid.value - total.value).toFixed(2))
  : 0
)

// Atalhos de valor: arredondar para cima nos múltiplos comuns
const changeHints = computed(() => {
  const t = total.value
  const hints = new Set()
  ;[50, 100, 200, 500, 1000].forEach(v => { if (v >= t) hints.add(v) })
  // Arredondamento imediato acima (5, 10, 20, 50...)
  const rounds = [5, 10, 20, 50, 100, 200, 500, 1000]
  for (const r of rounds) {
    const up = Math.ceil(t / r) * r
    if (up >= t && up <= t * 2) hints.add(up)
  }
  return [...hints].sort((a, b) => a - b).slice(0, 4)
})

const _fmt  = new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' })
const _fmtN = new Intl.NumberFormat('pt-MZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
function fmt(v)  { return _fmt.format(v ?? 0) }
function fmtN(v) { return _fmtN.format(v ?? 0) + ' MZN' }
function formatDateTime(iso) {
  return new Date(iso).toLocaleString('pt-MZ', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' })
}

function filterProducts() {
  const q = search.value.toLowerCase().trim()
  if (!q) { filtered.value = allProducts.value; return }
  filtered.value = allProducts.value.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.sku && p.sku.toLowerCase().includes(q)) ||
    (p.barcode && p.barcode.toLowerCase().includes(q))
  )
}

function addToCart(product) {
  const existing = cart.value.find(i => i.product_id === product.id && !i.weight_amount)
  if (existing) {
    existing.quantity++
    existing.subtotal = existing.unit_price * existing.quantity
  } else {
    cart.value.push({
      _key:         `${product.id}_${Date.now()}`,
      product_id:   product.id,
      product_name: product.name,
      product_sku:  product.sku,
      unit_price:   parseFloat(product.price),
      cost_price:   parseFloat(product.cost_price ?? 0),
      quantity:     1,
      weight_amount: null,
      weight_unit:  null,
      subtotal:     parseFloat(product.price),
    })
  }
}

function changeQty(item, delta) {
  if (delta > 0 && !item.weight_amount) {
    const product = allProducts.value.find(p => p.id === item.product_id)
    const maxStock = product?.stock?.quantity ?? Infinity
    if (item.quantity >= maxStock) return
  }
  item.quantity += delta
  if (item.quantity <= 0) { removeItem(item); return }
  if (!item.weight_amount) item.subtotal = item.unit_price * item.quantity
}

function removeItem(item) {
  cart.value = cart.value.filter(i => i._key !== item._key)
}

function clearCart() {
  cart.value = []
  discount.value = 0
  customerName.value = ''
  amountPaid.value = 0
}

function generateLocalId() {
  return `local_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`
}

async function finalizeSale() {
  if (!cart.value.length) return
  processing.value = true

  const sale = {
    local_id:       generateLocalId(),
    subtotal:       subtotal.value,
    discount:       discount.value || 0,
    apply_vat:      applyVat.value,
    vat_rate:       vatRate.value,
    vat_amount:     vatAmount.value,
    total:          total.value,
    amount_paid:    payMethod.value === 'cash' && amountPaid.value >= total.value ? amountPaid.value : total.value,
    change:         change.value,
    payment_method: payMethod.value,
    customer_name:  customerName.value || null,
    sale_at:        new Date().toISOString(),
    items:          cart.value.map(i => ({
      product_id:    i.product_id > 0 ? i.product_id : null, // IDs locais negativos não existem no servidor
      product_name:  i.product_name,
      product_sku:   i.product_sku,
      unit_price:    i.unit_price,
      cost_price:    i.cost_price,
      quantity:      i.quantity,
      weight_amount: i.weight_amount ?? null,
      weight_unit:   i.weight_unit ?? null,
      subtotal:      i.subtotal,
    })),
  }

  // Snapshot dos items antes de limpar o carrinho
  const soldItems = cart.value.map(i => ({ product_id: i.product_id, quantity: i.quantity, weight_amount: i.weight_amount }))

  try {
    if (isOnline.value) {
      await axios.post('/pos/sync', { sales: [sale] })
    } else {
      await savePendingSale(sale)
      pendingCount.value++
    }
    receipt.value = sale
    clearCart()
    search.value = ''
    filterProducts()
  } catch {
    await savePendingSale(sale)
    receipt.value = sale
    clearCart()
  } finally {
    processing.value = false
    // Decrementar stock local (evita reload completo)
    for (const item of soldItems) {
      if (!item.weight_amount) {
        const prod = allProducts.value.find(p => p.id === item.product_id)
        if (prod?.stock) prod.stock.quantity = Math.max(0, (prod.stock.quantity ?? 0) - item.quantity)
      }
    }
    filtered.value = [...allProducts.value]
    // Actualizar cache com novo stock
    cacheProducts(allProducts.value)
  }
}

function newSale() {
  receipt.value = null
  if (isOnline.value) trySyncNow()
  if (scanMode.value && searchInput.value) searchInput.value.focus()
}

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('pt-MZ', { hour: '2-digit', minute: '2-digit' })
}

function printReceipt() {
  const el = document.getElementById('pos-receipt')
  if (!el) return
  const html = el.innerHTML
  const win = window.open('', '_blank', 'width=400,height=600')
  win.document.write(`
    <!DOCTYPE html><html><head>
    <meta charset="utf-8">
    <title>Recibo</title>
    <style>
      body { margin:0; padding:4mm; font-family:'Courier New',monospace; font-size:12px; line-height:1.5; color:black; width:80mm; }
      @media print { body { width:80mm; margin:0; padding:4mm; } }
    </style>
    </head><body>${html}</body></html>
  `)
  win.document.close()
  win.focus()
  setTimeout(() => { win.print(); win.close() }, 300)
}

async function loadProducts() {
  loadingProducts.value = true

  // 1. Mostrar cache imediatamente (sem esperar servidor)
  const cached = await getCachedProducts()
  if (cached.length) {
    allProducts.value = cached
    filtered.value = cached
    loadingProducts.value = false
  }

  // 2. Actualizar do servidor em background (ou primeiro load se sem cache)
  if (isOnline.value) {
    try {
      const { data } = await axios.get('/pos/products')
      // Só substitui se a API devolveu produtos — evita apagar o cache
      // quando o servidor ainda não terminou a migration ou tem cache vazio
      if (data && data.length > 0) {
        allProducts.value = data
        await cacheProducts(data)
      } else if (!cached.length) {
        // Sem cache local e API retornou vazio → loja sem produtos
        allProducts.value = []
      }
      // Se API retornou vazio mas temos cache: mantemos o cache
    } catch {
      // Mantém cache se servidor falhar
    }
  }

  // 3. Adicionar produtos offline pendentes ao grid
  const pending = await getPendingProducts()
  for (const p of pending) {
    if (!allProducts.value.find(x => x.local_id === p.local_id)) {
      allProducts.value.push({
        id: -Date.now(), local_id: p.local_id, name: p.name, price: p.price,
        cost_price: p.cost_price, sku: p.sku, is_weighable: p.is_weighable,
        weight_unit: p.weight_unit, image: null,
        stock: { quantity: p.initial_stock ?? 0 },
      })
    }
  }
  filtered.value = allProducts.value
  loadingProducts.value = false
}

onMounted(async () => {
  await loadProducts()
  // Mostrar escolha de modo scan se for a primeira vez nesta sessão
  const sessionKey = `pos_scan_shown_${new Date().toDateString()}`
  if (!localStorage.getItem(sessionKey)) {
    showScanChoice.value = true
    localStorage.setItem(sessionKey, '1')
  }
  if (scanMode.value && searchInput.value) searchInput.value.focus()
})

// Recarregar produtos quando ficar online (sincroniza stock online → POS)
watch(isOnline, (online) => {
  if (online) loadProducts()
})
</script>

<style>
@media print {
  /* Esconder tudo excepto o recibo */
  body > * { display: none !important; }
  body > div#pos-receipt,
  #pos-receipt { display: block !important; }

  /* Formato térmico 80mm */
  body { margin: 0; padding: 0; background: white; }
  #pos-receipt {
    width: 80mm;
    max-width: 80mm;
    margin: 0 auto;
    padding: 4mm;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.4;
    color: black;
  }
}
</style>
