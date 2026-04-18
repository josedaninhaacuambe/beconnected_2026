<template>
  <div class="flex h-full flex-col lg:flex-row" style="height: calc(100vh - 88px);">

    <!-- ── MODAL CARRINHO (Mobile) ─────────────────────────────────────────── -->
    <div v-if="showMobileCart" class="fixed inset-0 z-40 flex items-end lg:hidden" style="background:rgba(0,0,0,0.5); max-height: calc(100vh - 44px);">
      <div class="w-full rounded-t-2xl flex flex-col overflow-hidden" style="background:white; max-height: 90vh;">
        <!-- Header -->
        <div class="sticky top-0 px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-white">
          <p class="font-bold text-gray-800">🛒 Carrinho</p>
          <button @click="showMobileCart = false" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
        </div>

        <!-- Items -->
        <div class="flex-1 overflow-y-auto px-4 py-2 space-y-2 min-h-0">
          <div v-if="!cart.length" class="flex flex-col items-center justify-center h-40 text-gray-400">
            <span class="text-3xl mb-1">🛒</span><p class="text-xs">Carrinho vazio</p>
          </div>
          <div v-for="item in cart" :key="item.product_id + item._key" class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg text-xs">
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-800 truncate">{{ item.product_name }}</p>
              <p class="text-gray-400">
                <span v-if="item.weight_amount && item.scale_value">⚖️ {{ item.weight_amount }}{{ item.weight_unit }} · balança</span>
                <span v-else-if="item.weight_amount">⚖️ {{ item.weight_amount }}{{ item.weight_unit }} · {{ fmt(item.unit_price) }}/{{ item.weight_unit }}</span>
                <span v-else>{{ fmt(item.unit_price) }}</span>
              </p>
            </div>
            <!-- Pesável: mostra valor e só ✕ (sem +/−, peso não é incrementável) -->
            <template v-if="item.weight_amount">
              <span class="text-xs font-bold text-blue-600">{{ fmt(item.subtotal) }}</span>
            </template>
            <!-- Normal: botões +/− -->
            <template v-else>
              <div class="flex items-center gap-1 shrink-0">
                <button @click="changeQty(item, -1)" class="w-5 h-5 rounded bg-gray-200 text-gray-600 text-xs font-bold hover:bg-gray-300">−</button>
                <span class="w-4 text-center font-bold">{{ item.quantity }}</span>
                <button @click="changeQty(item, 1)" class="w-5 h-5 rounded text-white text-xs font-bold" style="background:#F07820;">+</button>
              </div>
            </template>
            <button @click="removeItem(item)" class="text-red-400 hover:text-red-600">✕</button>
          </div>
        </div>

        <!-- Totais e opções de pagamento -->
        <div class="px-4 py-3 border-t border-gray-100 bg-white space-y-2 max-h-96 overflow-y-auto">
          <!-- IVA toggle -->
          <div class="flex items-center justify-between">
            <span class="text-xs text-gray-600">IVA</span>
            <button @click="applyVat = !applyVat"
              class="flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-lg border-2 transition"
              :class="applyVat ? 'border-green-500 text-green-600 bg-green-50' : 'border-gray-200 text-gray-400'">
              {{ applyVat ? '✓' : '' }} {{ vatRate }}%
            </button>
          </div>

          <!-- Subtotal -->
          <div class="flex justify-between text-xs text-gray-600">
            <span>Subtotal</span><span>{{ fmt(subtotal) }}</span>
          </div>

          <!-- Desconto -->
          <div class="flex items-center gap-2">
            <label class="text-xs text-gray-600 flex-shrink-0">Desconto</label>
            <input v-model.number="discount" type="number" min="0" step="0.01"
              class="flex-1 border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:border-bc-gold text-right" />
          </div>

          <!-- IVA -->
          <div v-if="applyVat" class="flex justify-between text-xs text-green-600 font-semibold">
            <span>IVA ({{ vatRate }}%)</span><span>+ {{ fmt(vatAmount) }}</span>
          </div>

          <!-- Total -->
          <div class="flex justify-between font-black text-sm border-t border-gray-100 pt-2" style="color:#F07820;">
            <span>TOTAL</span><span>{{ fmt(total) }}</span>
          </div>

          <!-- Método de pagamento -->
          <div class="grid grid-cols-3 gap-1 pt-1">
            <button v-for="m in payMethods" :key="m.value" @click="payMethod = m.value"
              class="py-1 rounded text-xs font-bold border-2 transition"
              :class="payMethod === m.value ? 'border-bc-gold text-bc-gold bg-bc-gold/10' : 'border-gray-200 text-gray-500'">
              {{ m.icon }} {{ m.label }}
            </button>
          </div>

          <!-- Dinheiro: Valor entregue + Troco -->
          <div v-if="payMethod === 'cash'" class="space-y-1.5 pt-1">
            <div class="flex items-center gap-2">
              <label class="text-xs text-gray-600 flex-shrink-0">Entregue</label>
              <input v-model.number="amountPaid" type="number" min="0" step="0.01"
                :placeholder="fmt(total)"
                class="flex-1 border border-gray-200 rounded-lg px-2 py-1 text-xs font-bold focus:outline-none focus:border-bc-gold text-right" />
            </div>
            <div v-if="amountPaid >= total && amountPaid > 0"
              class="flex justify-between items-center bg-green-50 border border-green-200 rounded-lg px-2 py-1 text-xs">
              <span class="font-semibold text-green-700">💰 Troco</span>
              <span class="font-black text-green-700">{{ fmt(change) }}</span>
            </div>
            <div v-else-if="amountPaid > 0 && amountPaid < total"
              class="text-xs text-red-500 text-center font-semibold">
              Faltam {{ fmt(total - amountPaid) }}
            </div>
          </div>

          <!-- Nome do cliente -->
          <input v-model="customerName" type="text" placeholder="Nome do cliente (opcional)"
            class="w-full border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:border-bc-gold" />

          <!-- Botões -->
          <div class="flex gap-2 pt-2">
            <button @click="showMobileCart = false"
              class="flex-1 px-2 py-2 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition">
              Continuar
            </button>
            <button @click="finalizeSale" :disabled="!cart.length || processing || (payMethod === 'cash' && amountPaid > 0 && amountPaid < total)"
              class="flex-1 px-2 py-2 rounded-lg font-black text-white text-xs transition disabled:opacity-40"
              style="background:#F07820;">
              {{ processing ? 'A registar...' : '✅ Confirmar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── ESQUERDA: Produtos (Full width mobile) ────────────────────────────── -->
    <div class="flex-1 flex flex-col overflow-hidden bg-white lg:border-r lg:border-gray-200">
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
          <button v-if="canAddProducts" @click="openAddProductModal"
            class="px-3 py-2 rounded-xl border-2 border-dashed border-gray-300 text-gray-400 hover:border-bc-gold hover:text-bc-gold transition text-xs font-bold flex-shrink-0"
            title="Adicionar produto">
            ➕
          </button>
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

      <!-- Categorias -->
      <div v-if="categories.length > 0" class="px-3 py-2 border-b border-gray-200 bg-white overflow-x-auto">
        <div class="flex gap-2 whitespace-nowrap">
          <button
            @click="selectedCategory = null; filterProducts()"
            :class="selectedCategory === null ? 'bg-bc-gold text-white' : 'bg-gray-100 text-gray-700'"
            class="px-4 py-2 rounded-full text-xs font-bold transition hover:shadow-sm flex-shrink-0"
          >
            📦 Todos
          </button>
          <button
            v-for="cat in categories"
            :key="cat.id"
            @click="selectedCategory = cat.id; filterProducts()"
            :class="selectedCategory === cat.id ? 'bg-bc-gold text-white' : 'bg-gray-100 text-gray-700'"
            class="px-4 py-2 rounded-full text-xs font-bold transition hover:shadow-sm flex-shrink-0"
          >
            {{ cat.name }}
          </button>
        </div>
      </div>

      <!-- Grid de produtos (Mobile: lista vertical com botão grande | Desktop: grid) -->
      <div class="flex-1 overflow-y-auto p-3">
        <div v-if="loadingProducts" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
          <div v-for="i in 8" :key="i" class="skeleton h-28 rounded-xl"></div>
        </div>

        <div v-else-if="loadError" class="flex flex-col items-center justify-center h-full text-red-400 p-4 text-center">
          <span class="text-4xl mb-2">⚠️</span>
          <p class="text-sm font-semibold">Erro ao carregar produtos</p>
          <p class="text-xs mt-1 text-red-300">{{ loadError }}</p>
          <button @click="loadProducts" class="mt-3 px-4 py-2 rounded-lg text-xs font-bold text-white" style="background:#F07820;">Tentar novamente</button>
        </div>
        <div v-else-if="!filtered.length" class="flex flex-col items-center justify-center h-full text-gray-400">
          <span class="text-4xl mb-2">📦</span>
          <p class="text-sm">Nenhum produto encontrado</p>
          <button @click="loadProducts" class="mt-3 px-4 py-2 rounded-lg text-xs font-bold text-white" style="background:#F07820;">Recarregar</button>
        </div>

        <!-- Mobile: Lista vertical com cards grandes e touch-friendly -->
        <div v-else>
          <div class="lg:hidden space-y-2">
            <button
              v-for="p in filtered" :key="p.id"
              @click="clickProduct(p)"
              class="w-full flex items-center gap-4 p-4 bg-gray-50 rounded-xl border border-gray-200 hover:border-bc-gold hover:shadow-md transition active:scale-95"
              :class="stockExplicitlyEmpty(p) ? 'opacity-40 cursor-not-allowed' : ''"
              :disabled="stockExplicitlyEmpty(p)"
            >
              <div class="w-20 h-20 bg-white rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center border border-gray-100">
                <AppImg :src="p.image ? (p.image.startsWith('http') ? p.image : '/storage/' + p.image) : ''" type="product" class="w-full h-full object-cover" />
              </div>
              <div class="flex-1 min-w-0 text-left">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ p.name }}</p>
                <p class="text-xs text-gray-500">SKU: {{ p.sku || 'N/A' }}</p>
                <p class="text-base font-black" style="color:#F07820;">{{ fmt(p.price) }}</p>
                <span v-if="stockExplicitlyEmpty(p)" class="text-xs text-red-500 font-bold">❌ Sem stock</span>
                <span v-else-if="p.stock?.quantity != null" class="text-xs text-green-600 font-semibold">✓ {{ p.stock.quantity }} unid.</span>
              </div>
              <button v-if="!stockExplicitlyEmpty(p)" class="flex-shrink-0 w-12 h-12 rounded-lg flex items-center justify-center text-xl font-bold border-2 transition" style="background:#F07820; color:white; border-color:#F07820;">
                +
              </button>
            </button>
          </div>

          <!-- Desktop: Grid como antes -->
          <div class="hidden lg:grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
            <button
              v-for="p in filtered" :key="p.id"
              @click="clickProduct(p)"
              class="relative flex flex-col items-center text-center p-3 bg-white rounded-xl border-2 border-transparent hover:border-bc-gold hover:shadow-md transition active:scale-95"
              :class="stockExplicitlyEmpty(p) ? 'opacity-40 cursor-not-allowed' : ''"
              :disabled="stockExplicitlyEmpty(p)"
            >
              <div class="w-full h-16 rounded-lg overflow-hidden bg-gray-100 mb-2 flex items-center justify-center">
                <AppImg :src="p.image ? (p.image.startsWith('http') ? p.image : '/storage/' + p.image) : ''" type="product" class="w-full h-full object-cover" />
              </div>
              <p class="text-xs font-semibold text-gray-800 line-clamp-2 leading-tight mb-1">{{ p.name }}</p>
              <p class="text-sm font-black" style="color:#F07820;">{{ fmt(p.price) }}</p>
              <span v-if="p.is_weighable" class="text-[9px] bg-blue-100 text-blue-700 font-bold px-1 py-0.5 rounded mt-0.5">
                ⚖️ por {{ p.weight_unit ?? 'kg' }}
              </span>
              <span v-if="p.stock?.quantity != null && p.stock.quantity <= 5 && p.stock.quantity > 0"
                class="absolute top-1 right-1 text-[9px] bg-yellow-100 text-yellow-700 font-bold px-1 py-0.5 rounded">
                {{ p.stock.quantity }} restam
              </span>
              <span v-if="stockExplicitlyEmpty(p)"
                class="absolute inset-0 flex items-center justify-center bg-white/70 rounded-xl text-xs font-bold text-red-500">
                Sem stock
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── BOTÃO CARRINHO FLUTUANTE (Mobile) ─────────────────────────────────── -->
    <button v-if="cart.length > 0" @click="showMobileCart = true" class="lg:hidden fixed bottom-20 right-4 w-14 h-14 rounded-full text-white font-bold text-xl shadow-2xl flex items-center justify-center z-30 active:scale-95 transition" 
      style="background:#F07820;">
      🛒<span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center">{{ cart.length }}</span>
    </button>

    <!-- ── DIREITA: Carrinho (Desktop only) ──────────────────────────────────── -->
    <div class="hidden lg:flex lg:w-72 lg:flex-col bg-white">
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
            <p class="text-xs text-gray-400 truncate">
              <span v-if="item.weight_amount">⚖️ {{ item.weight_amount }}{{ item.weight_unit }} · {{ fmt(item.unit_price) }}/{{ item.weight_unit }}</span>
              <span v-else-if="item.scale_value">⚖️ valor da balança</span>
              <span v-else>{{ fmt(item.unit_price) }}</span>
            </p>
          </div>
          <!-- Pesável: sem +/− (o peso vem da balança, não se incrementa) -->
          <template v-if="item.weight_amount">
            <div class="flex items-center gap-1 shrink-0">
              <p class="text-xs font-bold text-blue-600 min-w-[4.5rem] text-right">{{ fmt(item.subtotal) }}</p>
              <button @click="removeItem(item)" class="text-red-400 hover:text-red-600 text-sm">✕</button>
            </div>
          </template>
          <!-- Normal: +/− -->
          <template v-else>
            <div class="flex items-center gap-1 shrink-0">
              <button @click="changeQty(item, -1)" class="w-6 h-6 rounded-lg bg-gray-200 text-gray-600 text-xs font-bold hover:bg-gray-300 transition">−</button>
              <span class="w-6 text-center text-xs font-bold">{{ item.quantity }}</span>
              <button @click="changeQty(item, 1)" class="w-6 h-6 rounded-lg text-white text-xs font-bold transition" style="background:#F07820;">+</button>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <p class="text-xs font-bold text-gray-800 min-w-[4.5rem] text-right">{{ fmt(item.subtotal) }}</p>
              <button @click="removeItem(item)" class="text-red-400 hover:text-red-600 text-sm">✕</button>
            </div>
          </template>
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

    <!-- ══ MODAL: Produto por peso / balança ═══════════════════════════════ -->
    <Teleport to="body">
      <div v-if="weightProduct" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)"
        @keydown.enter.prevent="confirmWeight" @keydown.escape="weightProduct = null">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">

          <!-- Cabeçalho com nome e preço/unidade -->
          <div class="px-5 pt-5 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-2 mb-0.5">
              <span class="text-2xl">⚖️</span>
              <h3 class="font-black text-gray-800 text-lg leading-tight">{{ weightProduct.name }}</h3>
            </div>
            <p class="text-sm text-gray-400 ml-9">
              {{ fmt(weightProduct.price) }} por {{ weightProduct.weight_unit ?? 'kg' }}
            </p>
          </div>

          <div class="px-5 pt-4 pb-2 space-y-4">

            <!-- PASSO 1: Seleccionar unidade -->
            <div>
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">1. Unidade de medida</p>
              <div class="flex gap-2 flex-wrap">
                <button v-for="u in weightUnits" :key="u"
                  @click="weightForm.unit = u"
                  class="px-4 py-2 rounded-xl border-2 text-sm font-black transition"
                  :class="weightForm.unit === u
                    ? 'border-orange-400 text-orange-500 bg-orange-50'
                    : 'border-gray-200 text-gray-400 hover:border-gray-300'">
                  {{ u }}
                </button>
              </div>
            </div>

            <!-- PASSO 2: Modo de entrada — toggle compacto -->
            <div>
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">2. Como queres registar?</p>
              <div class="grid grid-cols-2 gap-2 text-xs">
                <button
                  @click="weightMode = 'weight'; nextTick(() => weightAmountInput?.focus())"
                  :class="['p-3 rounded-xl border-2 text-left transition', weightMode === 'weight'
                    ? 'border-orange-400 bg-orange-50'
                    : 'border-gray-200 hover:border-gray-300']">
                  <p class="text-base mb-0.5">⚖️</p>
                  <p :class="['font-bold', weightMode==='weight' ? 'text-orange-500' : 'text-gray-600']">Peso lido</p>
                  <p class="text-gray-400">Escrevo o peso e calcula o valor</p>
                </button>
                <button
                  @click="weightMode = 'value'; nextTick(() => weightValueInput?.focus())"
                  :class="['p-3 rounded-xl border-2 text-left transition', weightMode === 'value'
                    ? 'border-orange-400 bg-orange-50'
                    : 'border-gray-200 hover:border-gray-300']">
                  <p class="text-base mb-0.5">💰</p>
                  <p :class="['font-bold', weightMode==='value' ? 'text-orange-500' : 'text-gray-600']">Valor da balança</p>
                  <p class="text-gray-400">Copio o valor que a balança mostra</p>
                </button>
              </div>
            </div>

            <!-- PASSO 3: Input principal -->
            <div>
              <!-- MODO: Por Peso -->
              <template v-if="weightMode === 'weight'">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">3. Peso (lido na balança)</p>
                <div class="relative">
                  <input ref="weightAmountInput" v-model.number="weightForm.amount"
                    type="number" step="0.001" min="0.001" inputmode="decimal"
                    :placeholder="`0.000 ${weightForm.unit}`"
                    class="w-full border-2 border-gray-200 rounded-xl px-4 py-4 text-3xl font-black focus:outline-none focus:border-orange-400 text-center tracking-tight"
                    @keydown.enter.prevent="confirmWeight" />
                  <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-400">{{ weightForm.unit }}</span>
                </div>
                <!-- Total calculado -->
                <div class="mt-3 rounded-xl p-3 text-center" style="background:#FFF7ED;">
                  <p class="text-xs text-gray-400">Total a cobrar</p>
                  <p class="text-4xl font-black mt-0.5" style="color:#F07820;">{{ fmt(weightTotal) }}</p>
                  <p class="text-xs text-gray-400 mt-1">
                    {{ weightForm.amount || '0' }} {{ weightForm.unit }} × {{ fmt(weightProduct.price) }}/{{ weightProduct.weight_unit ?? 'kg' }}
                  </p>
                </div>
              </template>

              <!-- MODO: Valor directo da balança (peso + total) -->
              <template v-else>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">3. Peso e Valor da Balança</p>

                <!-- 3a — Peso -->
                <div class="mb-3">
                  <p class="text-xs text-gray-500 font-semibold mb-1">Peso lido na balança</p>
                  <div class="relative">
                    <input ref="weightScaleWeightInput" v-model.number="weightForm.scaleWeight"
                      type="number" step="0.001" min="0.001" inputmode="decimal"
                      :placeholder="`0.000`"
                      class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 text-2xl font-black focus:outline-none focus:border-orange-400 text-center"
                      @keydown.enter.prevent="weightValueInput?.focus()" />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-400">{{ weightForm.unit }}</span>
                  </div>
                </div>

                <!-- 3b — Valor da balança -->
                <div>
                  <p class="text-xs text-gray-500 font-semibold mb-1">Valor total mostrado na balança (MZN)</p>
                  <div class="relative">
                    <input ref="weightValueInput" v-model.number="weightForm.scaleValue"
                      type="number" step="0.01" min="0.01" inputmode="decimal"
                      placeholder="0.00"
                      class="w-full border-2 border-gray-200 rounded-xl px-4 py-4 text-3xl font-black focus:outline-none focus:border-orange-400 text-center tracking-tight"
                      @keydown.enter.prevent="confirmWeight" />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-400">MZN</span>
                  </div>
                </div>

                <!-- Resumo -->
                <div v-if="weightForm.scaleValue && weightForm.scaleWeight" class="mt-3 rounded-xl p-3 text-center" style="background:#FFF7ED;">
                  <p class="text-xs text-gray-400">Total a cobrar</p>
                  <p class="text-4xl font-black mt-0.5" style="color:#F07820;">{{ fmt(weightForm.scaleValue) }}</p>
                  <p class="text-xs text-gray-400 mt-1">{{ weightForm.scaleWeight }} {{ weightForm.unit }} · valor da balança</p>
                </div>
                <p v-else class="text-xs text-gray-400 text-center mt-2">
                  Introduza o peso e depois o valor total que a balança mostra.
                </p>
              </template>
            </div>
          </div>

          <!-- Botões -->
          <div class="flex gap-3 px-5 py-4 border-t border-gray-100 bg-gray-50">
            <button @click="weightProduct = null"
              class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-white transition">
              Cancelar
            </button>
            <button @click="confirmWeight"
              :disabled="weightMode === 'weight' ? !weightForm.amount : (!weightForm.scaleWeight || !weightForm.scaleValue)"
              class="flex-1 py-3 rounded-xl text-white font-black text-sm transition disabled:opacity-40 active:scale-95"
              style="background:#F07820;">
              ✓ Adicionar ao carrinho
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══ MODAL: Adicionar produto (formulário completo partilhado) ════════ -->
    <ProductFormModal
      v-model="showAddProduct"
      @saved="onProductSaved"
    />

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
                <!-- Logo customizado (se ativado) -->
                <div v-if="auth.user?.store?.invoice_show_logo && auth.user?.store?.logo" style="margin-bottom:6px;">
                  <img :src="auth.user?.store?.logo?.startsWith('http') ? auth.user?.store?.logo : `/storage/${auth.user?.store?.logo}`" style="width:40px; height:40px; margin:0 auto; object-fit:contain;" />
                </div>
                <!-- Texto de cabeçalho customizado -->
                <div v-if="auth.user?.store?.invoice_header_text" style="font-size:11px; margin-bottom:4px; font-style:italic;">{{ auth.user?.store?.invoice_header_text }}</div>
                <div style="font-size:16px; font-weight:900; letter-spacing:2px;">BECONNECT</div>
                <div style="font-size:10px; color:#666;">{{ auth.user?.store?.name ?? 'Loja' }}</div>
                <div v-if="auth.user?.store?.address" style="font-size:9px; color:#666;">{{ auth.user?.store?.address }}</div>
                <div v-if="auth.user?.store?.phone" style="font-size:9px; color:#666;">{{ auth.user?.store?.phone }}</div>
                <div style="font-size:10px; color:#666;">{{ formatDateTime(receipt.sale_at) }}</div>
                <div style="font-size:10px; color:#666;">{{ receipt.local_id }}</div>
                <div v-if="receipt.seller_name" style="font-size:10px; color:#666;">Vendedor: {{ receipt.seller_name }}</div>
              </div>

              <div style="border-top:1px dashed #ccc; margin:6px 0;"></div>

              <!-- Itens -->
              <div v-for="item in receipt.items" :key="item._key ?? item.product_name" style="margin-bottom:4px;">
                <div style="font-weight:700; font-size:11px;">{{ item.product_name }}</div>
                <div style="display:flex; justify-content:space-between; color:#444; font-size:11px;">
                  <span v-if="item.weight_amount && item.scale_value">
                    ⚖️ {{ item.weight_amount }}{{ item.weight_unit }} · balança
                  </span>
                  <span v-else-if="item.weight_amount">
                    {{ item.weight_amount }}{{ item.weight_unit }} × {{ fmtN(item.unit_price) }}/{{ item.weight_unit }}
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

              <!-- Rodapé customizado -->
              <div v-if="auth.user?.store?.invoice_footer_text" style="text-align:center; font-size:10px; color:#666; white-space:pre-line; margin-bottom:4px;">{{ auth.user?.store?.invoice_footer_text }}</div>
              <div v-else style="text-align:center; font-size:10px; color:#666;">
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
import { ref, computed, reactive, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import {
  useOfflinePos, cacheProducts, getCachedProducts,
  savePendingSale, getPendingProducts,
  cacheCategories, getCachedCategories,
} from '@/composables/useOfflinePos'

const auth = useAuthStore()
const { isOnline, pendingCount, pendingProductCount, syncing, syncMessage, trySyncNow } = useOfflinePos()

const canAddProducts = computed(() => auth.hasPosPermission('adicionar_produtos'))

// ── Estado principal ───────────────────────────────────────────────────────
const allProducts  = ref([])
const filtered     = ref([])
const search       = ref('')
const searchInput  = ref(null)
const categories   = ref([])
const selectedCategory = ref(null)
const cart         = ref([])
const discount     = ref(0)
const applyVat     = ref(false)
const vatRate      = ref(17)
const payMethod    = ref('cash')
const customerName = ref('')
const processing   = ref(false)
const receipt      = ref(null)
const loadingProducts = ref(true)
const loadError = ref('')
const showMobileCart = ref(false)

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

// ── Produto por peso / balança ───────────────────────────────────────────────
const weightProduct    = ref(null)
const weightMode       = ref('weight')           // 'weight' | 'value'
const weightForm       = reactive({ amount: '', unit: 'kg', scaleValue: '', scaleWeight: '' })
// Unidades disponíveis são as configuradas no produto; fallback para lista padrão
const weightUnits = computed(() =>
  weightProduct.value?.weight_units?.length
    ? weightProduct.value.weight_units
    : ['kg', 'g', 'l', 'ml']
)
const weightAmountInput      = ref(null)
const weightValueInput       = ref(null)
const weightScaleWeightInput = ref(null)

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

// Só bloqueia produto se stock for explicitamente 0 (não quando stock não está registado)
function stockExplicitlyEmpty(p) {
  if (p.stock == null) return false
  if (p.is_weighable) {
    // Produtos por peso: bloquear se weight_quantity for explicitamente 0
    return typeof p.stock.weight_quantity === 'number' && p.stock.weight_quantity <= 0
  }
  return typeof p.stock.quantity === 'number' && p.stock.quantity <= 0
}

function clickProduct(p) {
  if (p.is_weighable) {
    weightProduct.value = p
    weightMode.value    = 'weight'
    weightForm.amount      = ''
    weightForm.scaleValue  = ''
    weightForm.scaleWeight = ''
    // Usar a primeira unidade configurada no produto como unidade padrão
    const defaultUnit = p.weight_units?.[0] ?? p.weight_unit ?? 'kg'
    weightForm.unit = defaultUnit
    // Auto-focar no input após render
    nextTick(() => {
      weightAmountInput.value?.focus()
    })
  } else {
    addToCart(p)
  }
}

function confirmWeight() {
  if (!weightProduct.value) return
  const p   = weightProduct.value
  const key = `${p.id}_${Date.now()}`

  if (weightMode.value === 'value') {
    // Modo balança: o vendedor insere peso + total mostrado na balança
    if (!weightForm.scaleValue || !weightForm.scaleWeight) return
    const sw = parseFloat(weightForm.scaleWeight)
    const sv = parseFloat(weightForm.scaleValue)
    // Preço unitário derivado (para registos de custo/lucro)
    const derivedUnitPrice = sw > 0 ? parseFloat((sv / sw).toFixed(4)) : parseFloat(p.price)
    cart.value.push({
      _key:          key,
      product_id:    p.id,
      product_name:  p.name,
      product_sku:   p.sku,
      unit_price:    derivedUnitPrice,
      cost_price:    parseFloat(p.cost_price ?? 0),
      quantity:      1,
      weight_amount: sw,
      weight_unit:   weightForm.unit,
      scale_value:   true,          // flag: total veio da balança directamente
      subtotal:      parseFloat(sv.toFixed(2)),
    })
  } else {
    // Modo peso: calcula total a partir do peso inserido
    if (!weightForm.amount) return
    const amount = parseFloat(weightForm.amount)
    cart.value.push({
      _key:          key,
      product_id:    p.id,
      product_name:  p.name,
      product_sku:   p.sku,
      unit_price:    parseFloat(p.price),
      cost_price:    parseFloat(p.cost_price ?? 0),
      quantity:      1,
      weight_amount: amount,
      weight_unit:   weightForm.unit,
      scale_value:   false,
      subtotal:      parseFloat(weightTotal.value.toFixed(2)),
    })
  }
  weightProduct.value = null
}

// ── Adicionar produto ──────────────────────────────────────────────────────
const showAddProduct = ref(false)

async function onProductSaved() {
  await loadProducts()
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
  let result = allProducts.value

  // Filtro por categoria (incluindo categoria virtual "Por Peso")
  if (selectedCategory.value) {
    if (selectedCategory.value === '__weight__') {
      result = result.filter(p => p.is_weighable)
    } else {
      result = result.filter(p => p.category_id === selectedCategory.value)
    }
  }

  // Filtro por busca
  const q = search.value.toLowerCase().trim()
  if (q) {
    result = result.filter(p =>
      p.name.toLowerCase().includes(q) ||
      (p.sku && p.sku.toLowerCase().includes(q)) ||
      (p.barcode && p.barcode.toLowerCase().includes(q))
    )
  }

  filtered.value = result
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

function openAddProductModal() {
  showAddProduct.value = true
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
    seller_name:    auth.user?.name ?? null,
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
    cacheProducts(allProducts.value, auth.activeStoreId ?? auth.activeStore?.id)
  }
}

function newSale() {
  receipt.value = null
  if (isOnline.value) trySyncNow()
  if (scanMode.value && searchInput.value) searchInput.value.focus()
}

function printReceipt() {
  const el = document.getElementById('pos-receipt')
  if (!el) return
  const html = el.innerHTML
  const win = window.open('', '_blank', 'width=420,height=700')
  if (!win) { alert('Popup bloqueado. Permita popups para imprimir.'); return }
  win.document.write(`<!DOCTYPE html><html><head>
    <meta charset="utf-8">
    <title>Recibo</title>
    <style>
      @page { margin: 2mm; size: 80mm auto; }
      * { box-sizing: border-box; }
      body {
        margin: 0;
        padding: 4mm 4mm 8mm;
        font-family: 'Courier New', Courier, monospace;
        font-size: 13px;
        line-height: 1.6;
        color: #000 !important;
        background: #fff;
        width: 80mm;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      /* forçar cor preta em todos os elementos */
      * { color: #000 !important; }
      div, span, p, strong { color: #000 !important; }
      img { max-width: 100%; display: block; }
      @media print {
        body { width: 80mm; margin: 0; padding: 2mm 4mm 8mm; }
        button { display: none; }
      }
    </style>
  </head><body>${html}</body></html>`)
  win.document.close()
  win.focus()
  setTimeout(() => {
    try { win.print() } catch (e) { /* ignorar */ }
    setTimeout(() => { try { win.close() } catch (e) { /* ignorar */ } }, 1000)
  }, 500)
}

async function loadProducts() {
  loadingProducts.value = true
  loadError.value = ''
  const storeId = auth.activeStoreId ?? auth.activeStore?.id ?? auth.user?.pos_employee?.store?.id

  try {
    // 1. Mostrar cache imediatamente (sem esperar servidor) — filtrada por loja
    let cached = []
    try {
      cached = await getCachedProducts(storeId)
    } catch {
      cached = []
    }
    if (cached.length) {
      allProducts.value = cached
      filtered.value = cached
      loadingProducts.value = false
    }

    // 2. Actualizar do servidor (sempre — cache é só fallback)
    if (isOnline.value) {
      try {
        const { data } = await axios.get('/pos/products')
        const products = Array.isArray(data) ? data : []

        if (products.length > 0) {
          // Servidor devolveu produtos — actualizar estado e cache
          allProducts.value = products
          filtered.value = products
          cacheProducts(products, storeId).catch(() => {})
        } else if (products.length === 0 && cached.length > 0) {
          // Servidor devolveu lista vazia mas temos cache — manter cache
          console.warn('[POS] Servidor devolveu 0 produtos — a usar cache local')
        } else if (products.length === 0 && !cached.length) {
          // Sem produtos no servidor nem em cache — mostrar erro com detalhe
          const detail = !Array.isArray(data) ? ' (resposta inesperada do servidor)' : ''
          loadError.value = 'Nenhum produto encontrado. Adicione produtos à loja primeiro.' + detail
          console.error('[POS] Resposta da API:', data)
        }
      } catch (err) {
        const msg = err?.response?.data?.message ?? err?.message ?? 'Erro ao carregar produtos'
        console.error('[POS] Erro ao carregar produtos:', err?.response?.status, msg)
        if (!cached.length) loadError.value = msg
      }
    }

    // 3. Adicionar produtos criados offline ainda pendentes
    try {
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
    } catch { /* produtos pendentes indisponíveis — ignorar */ }

    filtered.value = allProducts.value
  } finally {
    loadingProducts.value = false
  }
}

async function loadCategories() {
  // Mostrar cache imediatamente
  const cached = await getCachedCategories()
  if (cached?.value?.length) categories.value = cached.value

  if (isOnline.value) {
    try {
      const { data } = await axios.get('/pos/categories')
      if (data?.length) {
        categories.value = data
        await cacheCategories(data)
      }
    } catch {
      // mantém cache
    }
  }
}

// Injeta categoria virtual "⚖️ Por Peso" no início se existirem produtos pesáveis
function injectWeightCategory() {
  const WEIGHT_CAT_ID = '__weight__'
  const hasWeighable = allProducts.value.some(p => p.is_weighable)
  // Remover categoria virtual anterior (evitar duplicados em recargas)
  categories.value = categories.value.filter(c => c.id !== WEIGHT_CAT_ID)
  if (hasWeighable) {
    categories.value = [{ id: WEIGHT_CAT_ID, name: '⚖️ Por Peso', virtual: true }, ...categories.value]
  }
}

onMounted(async () => {
  await loadProducts()
  await loadCategories()
  injectWeightCategory()
  // Mostrar escolha de modo scan se for a primeira vez nesta sessão
  const sessionKey = `pos_scan_shown_${new Date().toDateString()}`
  if (!localStorage.getItem(sessionKey)) {
    showScanChoice.value = true
    localStorage.setItem(sessionKey, '1')
  }
  if (scanMode.value && searchInput.value) searchInput.value.focus()
})

// ── Watchers ────────────────────────────────────────────────────────────────
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
