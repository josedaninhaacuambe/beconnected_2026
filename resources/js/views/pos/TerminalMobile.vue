<template>
  <div class="min-h-screen bg-bc-dark flex flex-col">

    <!-- ═══ HEADER COM ESTADO ═══════════════════════════════════════════ -->
    <div class="sticky top-0 z-40 bg-bc-dark border-b border-bc-gold/30 px-4 py-3 space-y-2">
      <div class="flex justify-between items-center">
        <div class="flex items-center gap-2">
          <span class="text-bc-gold text-2xl">🛒</span>
          <div class="flex flex-col">
            <span class="text-bc-light font-bold text-sm">{{ auth.user?.store?.name ?? 'Loja' }} - POS</span>
            <span class="text-bc-muted text-xs">{{ auth.user?.name ?? 'Usuário' }} - {{ getUserRoleDisplay() }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span :class="isOnline ? 'text-green-400' : 'text-red-400'" class="text-xs font-semibold">
            {{ isOnline ? '● Online' : '● Offline' }}
          </span>
          <!-- Botão "Ir para loja" - só para donos e gerentes -->
          <button
            v-if="canAccessStore"
            @click="goToStore"
            class="text-bc-gold text-sm font-semibold px-2 py-1 rounded border border-bc-gold/50 hover:bg-bc-gold hover:text-bc-dark transition"
            title="Ir para loja virtual">
            🏪 Loja
          </button>
          <button @click="showMenu = !showMenu" class="text-bc-gold text-xl">⚙️</button>
        </div>
      </div>

      <!-- Menu Rápido Suspenso -->
      <div v-if="showMenu" class="grid grid-cols-3 gap-2 text-xs">
        <button @click="mode = 'search'" :class="mode === 'search' ? 'bg-bc-gold text-bc-dark' : 'bg-bc-surface text-bc-muted'" class="py-1 rounded">🔍 Buscar</button>
        <button @click="mode = 'cart'" :class="mode === 'cart' ? 'bg-bc-gold text-bc-dark' : 'bg-bc-surface text-bc-muted'" class="py-1 rounded">🛒 Carrinho</button>
        <button @click="mode = 'complete'" :class="mode === 'complete' ? 'bg-bc-gold text-bc-dark' : 'bg-bc-surface text-bc-muted'" class="py-1 rounded">✓ Finalizar</button>
      </div>
    </div>

    <!-- ═══ MODO 1: BUSCA E PRODUTOS ═════════════════════════════════════ -->
    <div v-if="mode === 'search' && currentSection === 'venda'" class="flex-1 flex flex-col overflow-hidden">
      
      <!-- Categorias de Produtos (Topo) -->
      <div v-if="categories.length > 0" class="px-4 py-3 bg-bc-surface border-b border-bc-gold/20">
        <div class="flex gap-3 overflow-x-auto whitespace-nowrap pb-2">
          <button
            @click="selectedCategory = null; filterProducts()"
            :class="selectedCategory === null ? 'bg-bc-gold text-bc-dark shadow-lg' : 'bg-bc-surface text-bc-muted border-bc-gold/30'"
            class="px-4 py-3 rounded-xl text-sm font-bold border transition-all flex-shrink-0 min-w-[80px]"
          >
            📦 Todos
          </button>
          <button
            v-for="cat in categories"
            :key="cat.id"
            @click="selectedCategory = cat.id; filterProducts()"
            :class="selectedCategory === cat.id ? 'bg-bc-gold text-bc-dark shadow-lg' : 'bg-bc-surface text-bc-muted border-bc-gold/30'"
            class="px-4 py-3 rounded-xl text-sm font-bold border transition-all flex-shrink-0 min-w-[100px]"
          >
            {{ cat.name }}
          </button>
        </div>
      </div>

      <!-- Barra de Busca (Meio) -->
      <div class="px-4 py-4 bg-bc-dark border-b border-bc-gold/20">
        <input
          ref="searchInput"
          v-model="search"
          @input="filterProducts"
          type="text"
          placeholder="🔍 Produto, SKU ou código de barras..."
          class="w-full bg-bc-surface border border-bc-gold/30 rounded-xl px-4 py-3 text-base text-bc-light focus:outline-none focus:border-bc-gold focus:ring-2 focus:ring-bc-gold/20"
          @keydown.enter="onSearchEnter"
        />
      </div>

      <!-- Grid de Produtos -->
      <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
        <div v-if="loadingProducts" class="space-y-3">
          <div v-for="i in 4" :key="i" class="bg-bc-surface rounded-xl h-28 animate-pulse"></div>
        </div>

        <div v-else-if="!filtered.length" class="flex flex-col items-center justify-center h-full text-bc-muted text-base py-8">
          <span class="text-5xl mb-4">📦</span>
          <p class="text-center">Nenhum produto encontrado</p>
          <p class="text-center text-sm mt-2">Tente ajustar sua busca ou categoria</p>
        </div>

        <button
          v-for="p in filtered"
          :key="p.id"
          @click="clickProduct(p)"
          :disabled="(p.stock?.quantity ?? 0) <= 0"
          class="w-full bg-bc-surface rounded-xl p-4 text-left border border-bc-gold/30 hover:border-bc-gold hover:shadow-lg disabled:opacity-50 transition-all active:scale-95"
        >
          <div class="flex gap-4">
            <!-- Imagem -->
            <div class="w-24 h-24 bg-bc-dark rounded-xl flex-shrink-0 overflow-hidden flex items-center justify-center shadow-inner">
              <AppImg
                :src="p.image ? (p.image.startsWith('http') ? p.image : `/storage/${p.image}`) : ''"
                type="product"
                class="w-full h-full object-cover"
              />
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0 space-y-1">
              <p class="text-bc-light font-bold text-base leading-tight">{{ p.name }}</p>
              <p class="text-bc-muted text-sm">SKU: {{ p.sku || 'N/A' }}</p>
              <p class="text-bc-gold font-bold text-xl">{{ fmt(p.price) }}</p>
              <div class="flex flex-wrap gap-2 mt-2">
                <span v-if="p.is_weighable" class="text-xs bg-blue-900/40 text-blue-300 px-2 py-1 rounded-lg font-medium">
                  ⚖️ Por {{ p.weight_unit ?? 'kg' }}
                </span>
                <span v-if="p.stock && p.stock.quantity > 0" class="text-xs bg-green-900/40 text-green-300 px-2 py-1 rounded-lg font-medium">
                  📦 {{ p.stock.quantity }} em stock
                </span>
                <span v-else class="text-xs bg-red-900/40 text-red-300 px-2 py-1 rounded-lg font-medium">
                  ❌ Sem stock
                </span>
              </div>
            </div>
          </div>
        </button>
      </div>

      <!-- Botão do Carrinho (Final) -->
      <div v-if="cart.length > 0" class="px-4 py-4 bg-bc-gold border-t border-bc-dark">
        <button @click="mode = 'cart'" class="w-full bg-bc-dark text-bc-gold text-lg font-bold py-4 rounded-lg shadow-lg hover:bg-bc-dark/90 transition-colors">
          🛒 Ver Carrinho ({{ cart.length }} itens) - {{ fmt(total) }}
        </button>
      </div>
    </div>

    <!-- ═══ MODO 2: CARRINHO ═════════════════════════════════════════════ -->
    <div v-if="mode === 'cart' && currentSection === 'venda'" class="flex-1 flex flex-col overflow-hidden bg-bc-dark">
      <!-- Resumo Rápido -->
      <div class="px-4 py-4 bg-bc-surface border-b border-bc-gold/20 space-y-3">
        <div class="grid grid-cols-3 gap-3 text-center">
          <div class="bg-bc-dark rounded-lg p-3">
            <p class="text-bc-muted text-xs">Itens</p>
            <p class="text-bc-gold font-bold text-xl">{{ cart.length }}</p>
          </div>
          <div class="bg-bc-dark rounded-lg p-3">
            <p class="text-bc-muted text-xs">Subtotal</p>
            <p class="text-bc-gold font-bold text-xl">{{ fmt(subtotal) }}</p>
          </div>
          <div class="bg-bc-dark rounded-lg p-3">
            <p class="text-bc-muted text-xs">Total</p>
            <p class="text-bc-gold font-bold text-xl">{{ fmt(total) }}</p>
          </div>
        </div>

        <button
          v-if="cart.length"
          @click="clearCart"
          class="w-full text-red-400 text-sm font-bold py-3 border border-red-400/50 rounded-lg hover:bg-red-400/10 transition-colors"
        >
          🗑️ Limpar Carrinho
        </button>
      </div>

      <!-- Itens do Carrinho -->
      <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
        <div v-if="!cart.length" class="flex flex-col items-center justify-center h-full text-bc-muted">
          <span class="text-5xl mb-3">🛒</span>
          <p class="text-base mb-4">Carrinho vazio</p>
          <button @click="mode = 'search'" class="px-6 py-3 bg-bc-gold text-bc-dark text-base font-bold rounded-lg shadow-lg">
            Adicionar Produtos
          </button>
        </div>

        <div v-for="(item, idx) in cart" :key="item._key" class="bg-bc-surface rounded-xl p-4 border border-bc-gold/30 shadow-sm">
          <div class="flex justify-between items-start mb-3">
            <div class="flex-1 min-w-0">
              <p class="text-bc-light font-bold text-base truncate">{{ item.product_name }}</p>
              <p class="text-bc-muted text-sm">{{ fmt(item.unit_price) }}</p>
            </div>
            <button @click="removeItem(idx)" class="text-red-400 text-2xl ml-3 w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-400/20 transition-colors">✕</button>
          </div>

          <!-- Controle de Quantidade -->
          <div class="flex items-center gap-3">
            <button @click="changeQty(idx, -1)" class="bg-bc-dark text-bc-gold font-bold w-12 h-12 rounded-lg text-lg shadow-sm hover:bg-bc-dark/80 transition-colors">−</button>
            <input
              v-model.number="item.quantity"
              type="number"
              min="1"
              class="flex-1 bg-bc-dark text-bc-light text-center text-base rounded-lg border border-bc-gold/30 py-2 px-3 focus:outline-none focus:border-bc-gold focus:ring-1 focus:ring-bc-gold/50"
              @change="updateItemSubtotal(idx)"
            />
            <button @click="changeQty(idx, 1)" class="bg-bc-gold text-bc-dark font-bold w-12 h-12 rounded-lg text-lg shadow-sm hover:bg-bc-gold/90 transition-colors">+</button>
          </div>

          <div class="flex justify-between items-center mt-3 pt-3 border-t border-bc-gold/20">
            <span class="text-bc-muted text-sm">Subtotal:</span>
            <span class="text-bc-gold font-bold text-lg">{{ fmt(item.subtotal) }}</span>
          </div>
        </div>
      </div>

      <!-- Ações -->
      <div class="px-4 py-4 bg-bc-surface border-t border-bc-gold/20 space-y-3">
        <button @click="mode = 'search'" class="w-full bg-bc-dark text-bc-gold text-base font-bold py-3 rounded-lg border border-bc-gold/50 hover:bg-bc-dark/80 transition-colors">
          ← Continuar Vendendo
        </button>
        <button
          @click="mode = 'complete'"
          :disabled="!cart.length"
          class="w-full bg-bc-gold text-bc-dark text-base font-bold py-3 rounded-lg disabled:opacity-50 shadow-lg hover:bg-bc-gold/90 transition-colors"
        >
          ✓ Finalizar Venda →
        </button>
      </div>
    </div>

    <!-- ═══ MODO 3: FINALIZAR VENDA ══════════════════════════════════════ -->
    <div v-if="mode === 'complete' && currentSection === 'venda'" class="flex-1 flex flex-col overflow-hidden">
      <!-- Resumo de Venda -->
      <div class="px-4 py-4 bg-bc-surface border-b border-bc-gold/20 space-y-3">
        <h3 class="text-bc-light font-bold text-lg">Resumo da Venda</h3>
        
        <div class="space-y-2 text-base">
          <div class="flex justify-between text-bc-muted">
            <span>Subtotal:</span>
            <span>{{ fmt(subtotal) }}</span>
          </div>
          <div class="flex justify-between text-bc-muted">
            <span>Desconto:</span>
            <input
              v-model.number="discount"
              type="number"
              min="0"
              class="w-24 bg-bc-dark text-bc-gold text-right rounded-lg px-3 py-2 text-sm border border-bc-gold/30 focus:outline-none focus:border-bc-gold focus:ring-1 focus:ring-bc-gold/50"
            />
          </div>
          <div v-if="applyVat" class="flex justify-between text-green-400">
            <span>IVA ({{ vatRate }}%):</span>
            <span>+ {{ fmt(vatAmount) }}</span>
          </div>
          <div class="flex justify-between text-bc-gold font-bold text-xl border-t border-bc-gold/30 pt-3 mt-3">
            <span>TOTAL:</span>
            <span>{{ fmt(total) }}</span>
          </div>
        </div>

        <!-- Toggle IVA -->
        <button
          @click="applyVat = !applyVat"
          :class="applyVat ? 'bg-green-600' : 'bg-bc-dark'"
          class="w-full text-white text-sm font-bold py-3 rounded-lg border border-bc-gold/50 hover:opacity-90 transition-opacity"
        >
          {{ applyVat ? '✓' : '' }} IVA {{ vatRate }}%
        </button>
      </div>

      <!-- Forma de Pagamento -->
      <div class="px-4 py-4 bg-bc-dark space-y-3 border-b border-bc-gold/20">
        <p class="text-bc-light font-bold text-base">Forma de Pagamento</p>
        <div class="grid grid-cols-3 gap-3">
          <button
            v-for="m in payMethods"
            :key="m.value"
            @click="payMethod = m.value"
            :class="payMethod === m.value ? 'bg-bc-gold text-bc-dark' : 'bg-bc-surface text-bc-muted'"
            class="py-4 rounded-lg text-sm font-bold border border-bc-gold/30 hover:bg-bc-gold/20 transition-colors"
          >
            {{ m.icon }}<br>{{ m.label }}
          </button>
        </div>

        <!-- Dinheiro: Montante Entregue -->
        <div v-if="payMethod === 'cash'" class="space-y-3 mt-4">
          <label class="text-bc-muted text-sm font-medium">Valor Entregue</label>
          <input
            v-model.number="amountPaid"
            type="number"
            step="0.01"
            min="0"
            placeholder="Digite o montante..."
            class="w-full bg-bc-surface border border-bc-gold/30 rounded-lg px-4 py-3 text-bc-light text-center font-bold text-lg focus:outline-none focus:border-bc-gold focus:ring-2 focus:ring-bc-gold/50"
          />

          <!-- Atalhos de Valor -->
          <div class="grid grid-cols-4 gap-2">
            <button
              v-for="hint in changeHints"
              :key="hint"
              @click="amountPaid = hint"
              class="text-sm bg-bc-surface border border-bc-gold/30 text-bc-gold font-bold py-3 rounded-lg hover:bg-bc-gold/20 transition-colors"
            >
              {{ fmt(hint) }}
            </button>
          </div>

          <!-- Troco -->
          <div v-if="amountPaid >= total" class="bg-green-900/30 border border-green-600 rounded-lg px-4 py-3 text-center">
            <p class="text-green-400 text-sm font-medium">Troco</p>
            <p class="text-green-400 font-bold text-xl">{{ fmt(change) }}</p>
          </div>
          <div v-else-if="amountPaid > 0 && amountPaid < total" class="text-red-400 text-sm text-center font-bold bg-red-900/20 rounded-lg py-2">
            ⚠️ Faltam {{ fmt(total - amountPaid) }}
          </div>
        </div>
      </div>

      <!-- Info de Cliente -->
      <div class="px-4 py-4 space-y-3">
        <label class="text-bc-light text-base font-bold">Nome do Cliente (Opcional)</label>
        <input
          v-model="customerName"
          type="text"
          placeholder="Digite o nome..."
          class="w-full bg-bc-surface border border-bc-gold/30 rounded-lg px-4 py-3 text-bc-light text-base focus:outline-none focus:border-bc-gold focus:ring-2 focus:ring-bc-gold/50"
        />
      </div>

      <!-- Ações Finais -->
      <div class="px-4 py-4 space-y-3 border-t border-bc-gold/20">
        <button
          @click="mode = 'cart'"
          class="w-full bg-bc-surface text-bc-gold text-base font-bold py-3 rounded-lg border border-bc-gold/50 hover:bg-bc-surface/80 transition-colors"
        >
          ← Voltar ao Carrinho
        </button>
        <button
          @click="finalizeSale"
          :disabled="!cart.length || processing || (payMethod === 'cash' && amountPaid < total)"
          class="w-full bg-bc-gold text-bc-dark text-base font-bold py-4 rounded-lg disabled:opacity-50 shadow-lg hover:bg-bc-gold/90 transition-colors"
        >
          {{ processing ? '⏳ Processando...' : '✓ CONFIRMAR VENDA' }}
        </button>
      </div>
    </div>

    <!-- ═══ MODAL: Produto por Peso ═════════════════════════════════════ -->
    <div v-if="weightProduct" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
      <div class="bg-bc-dark rounded-xl p-6 w-full max-w-sm space-y-4 border border-bc-gold/30">
        <h2 class="text-bc-light font-bold text-lg">⚖️ {{ weightProduct.name }}</h2>
        <p class="text-bc-muted text-sm">Preço: <span class="text-bc-gold font-bold">{{ fmt(weightProduct.price) }}</span> / {{ weightProduct.weight_unit ?? 'kg' }}</p>

        <!-- Unidade -->
        <div>
          <p class="text-bc-muted text-xs mb-2">Unidade</p>
          <div class="grid grid-cols-4 gap-2">
            <button
              v-for="u in ['g', 'kg', 'l', 'ml']"
              :key="u"
              @click="weightForm.unit = u"
              :class="weightForm.unit === u ? 'bg-bc-gold text-bc-dark' : 'bg-bc-surface text-bc-muted'"
              class="py-2 rounded text-xs font-bold border border-bc-gold/30"
            >
              {{ u }}
            </button>
          </div>
        </div>

        <!-- Quantidade -->
        <div>
          <p class="text-bc-muted text-xs mb-2">Quantidade</p>
          <input
            v-model.number="weightForm.amount"
            type="number"
            step="0.01"
            min="0"
            placeholder="ex: 0.750"
            class="w-full bg-bc-surface border border-bc-gold/30 rounded px-3 py-2 text-bc-light text-center font-bold focus:outline-none focus:border-bc-gold"
          />
        </div>

        <!-- Total -->
        <div class="bg-bc-surface rounded px-4 py-3 text-center border border-bc-gold/30">
          <p class="text-bc-muted text-xs">Total a Cobrar</p>
          <p class="text-bc-gold font-bold text-2xl">{{ fmt(weightTotal) }}</p>
        </div>

        <!-- Botões -->
        <div class="grid grid-cols-2 gap-2">
          <button @click="weightProduct = null" class="bg-bc-surface text-bc-muted text-sm font-bold py-2 rounded border border-bc-gold/30">
            Cancelar
          </button>
          <button @click="confirmWeight" :disabled="!weightForm.amount" class="bg-bc-gold text-bc-dark text-sm font-bold py-2 rounded disabled:opacity-50">
            Adicionar
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ MODAL: Recibo ════════════════════════════════════════════════ -->
    <div v-if="receipt" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
      <div class="bg-bc-dark rounded-xl w-full max-w-sm max-h-96 overflow-y-auto border border-bc-gold/30">
        <!-- Cabeçalho Modal -->
        <div class="sticky top-0 px-4 py-3 bg-bc-surface border-b border-bc-gold/20 flex justify-between items-center">
          <h2 class="text-bc-light font-bold">✅ Venda Registada</h2>
          <button @click="receipt = null" class="text-bc-muted">✕</button>
        </div>

        <!-- Conteúdo Recibo -->
        <div class="p-4 space-y-3 text-xs font-mono text-bc-light">
          <div class="text-center">
            <!-- Logo customizado -->
            <div v-if="auth.user?.store?.invoice_show_logo && auth.user?.store?.logo" class="mb-2">
              <img :src="auth.user?.store?.logo?.startsWith('http') ? auth.user?.store?.logo : `/storage/${auth.user?.store?.logo}`" class="w-10 h-10 mx-auto object-contain" />
            </div>
            <!-- Cabeçalho customizado -->
            <p v-if="auth.user?.store?.invoice_header_text" class="text-bc-gold text-xs mb-2 italic">{{ auth.user?.store?.invoice_header_text }}</p>
            <p class="text-bc-gold font-bold text-sm">BECONNECT</p>
            <p class="text-bc-muted text-xs">{{ auth.user?.store?.name ?? 'Loja' }}</p>
            <p v-if="auth.user?.store?.phone" class="text-bc-muted text-xs">{{ auth.user?.store?.phone }}</p>
            <p v-if="auth.user?.store?.address" class="text-bc-muted text-xs">{{ auth.user?.store?.address }}</p>
            <p class="text-bc-muted text-xs">{{ formatDateTime(receipt.sale_at) }}</p>
          </div>

          <div class="border-t border-bc-gold/30 pt-2">
            <div v-for="item in receipt.items" :key="item._key ?? item.product_name" class="mb-2">
              <p class="text-bc-light font-bold">{{ item.product_name }}</p>
              <div class="flex justify-between text-bc-muted text-xs">
                <span v-if="item.weight_amount">
                  {{ item.weight_amount }}{{ item.weight_unit }} × {{ fmtN(item.unit_price) }}
                </span>
                <span v-else>
                  {{ item.quantity }} × {{ fmtN(item.unit_price) }}
                </span>
                <span class="text-bc-gold font-bold">{{ fmtN(item.subtotal) }}</span>
              </div>
            </div>
          </div>

          <div class="border-t border-bc-gold/30 pt-2 space-y-1 text-bc-muted">
            <div class="flex justify-between">
              <span>Subtotal</span><span>{{ fmtN(receipt.subtotal) }}</span>
            </div>
            <div v-if="receipt.discount > 0" class="flex justify-between text-red-400">
              <span>Desconto</span><span>- {{ fmtN(receipt.discount) }}</span>
            </div>
            <div v-if="receipt.apply_vat" class="flex justify-between text-green-400">
              <span>IVA {{ receipt.vat_rate }}%</span><span>+ {{ fmtN(receipt.vat_amount) }}</span>
            </div>
          </div>

          <div class="border-t border-bc-gold/30 pt-2 border-b border-bc-gold/30 pb-2">
            <div class="flex justify-between text-bc-gold font-bold">
              <span>TOTAL</span><span class="text-lg">{{ fmtN(receipt.total) }}</span>
            </div>
          </div>

          <div class="space-y-1 text-bc-muted">
            <div class="flex justify-between">
              <span>Forma de Pagamento</span><span class="text-bc-gold font-bold uppercase">{{ receipt.payment_method }}</span>
            </div>
            <div v-if="receipt.change > 0" class="flex justify-between text-green-400 font-bold">
              <span>TROCO</span><span>{{ fmtN(receipt.change) }}</span>
            </div>
          </div>

          <p v-if="receipt.customer_name" class="text-center text-bc-muted">Cliente: <span class="text-bc-gold">{{ receipt.customer_name }}</span></p>

          <!-- Rodapé customizado -->
          <p v-if="auth.user?.store?.invoice_footer_text" class="text-center text-bc-gold text-xs pt-2 whitespace-pre-line">{{ auth.user?.store?.invoice_footer_text }}</p>
          <p v-else class="text-center text-bc-gold text-xs pt-2">Obrigado pela sua compra!</p>
        </div>

        <!-- Botões -->
        <div class="px-4 py-3 bg-bc-surface border-t border-bc-gold/20 grid grid-cols-2 gap-2">
          <button @click="printReceipt" class="bg-bc-dark text-bc-gold text-sm font-bold py-2 rounded border border-bc-gold/50">
            🖨️ Imprimir
          </button>
          <button @click="newSale" class="bg-bc-gold text-bc-dark text-sm font-bold py-2 rounded">
            ➕ Nova Venda
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ MENU FOOTER (Mobile) ═══════════════════════════════════════════ -->
    <nav class="fixed bottom-0 left-0 right-0 bg-bc-gold border-t border-bc-dark shadow-lg grid grid-cols-5 md:hidden z-50">
      <button @click="currentSection = 'venda'" :class="currentSection === 'venda' ? 'bg-bc-navy text-bc-gold' : 'text-bc-dark'" class="flex flex-col items-center justify-center py-3 px-2 text-xs gap-1 hover:bg-bc-navy hover:text-bc-gold transition-colors min-h-20">
        <span class="text-xl">🛒</span>
        <span class="font-semibold">Venda</span>
      </button>
      <button @click="currentSection = 'stock'" :class="currentSection === 'stock' ? 'bg-bc-navy text-bc-gold' : 'text-bc-dark'" class="flex flex-col items-center justify-center py-3 px-2 text-xs gap-1 hover:bg-bc-navy hover:text-bc-gold transition-colors min-h-20">
        <span class="text-xl">📦</span>
        <span class="font-semibold">Stock</span>
      </button>
      <button @click="currentSection = 'produtos'" :class="currentSection === 'produtos' ? 'bg-bc-navy text-bc-gold' : 'text-bc-dark'" class="flex flex-col items-center justify-center py-3 px-2 text-xs gap-1 hover:bg-bc-navy hover:text-bc-gold transition-colors min-h-20">
        <span class="text-xl">🛍️</span>
        <span class="font-semibold">Produtos</span>
      </button>
      <button @click="currentSection = 'relatorios'" :class="currentSection === 'relatorios' ? 'bg-bc-navy text-bc-gold' : 'text-bc-dark'" class="flex flex-col items-center justify-center py-3 px-2 text-xs gap-1 hover:bg-bc-navy hover:text-bc-gold transition-colors min-h-20">
        <span class="text-xl">📊</span>
        <span class="font-semibold">Relatórios</span>
      </button>
      <button @click="currentSection = 'equipe'" :class="currentSection === 'equipe' ? 'bg-bc-navy text-bc-gold' : 'text-bc-dark'" class="flex flex-col items-center justify-center py-3 px-2 text-xs gap-1 hover:bg-bc-navy hover:text-bc-gold transition-colors min-h-20">
        <span class="text-xl">👥</span>
        <span class="font-semibold">Equipe</span>
      </button>
    </nav>

    <!-- ═══ SEÇÃO STOCK ═════════════════════════════════════════════ -->
    <div v-if="currentSection === 'stock'" class="flex-1 flex flex-col overflow-hidden bg-bc-dark">
      <div class="flex-1 flex items-center justify-center">
        <div class="text-center text-bc-muted">
          <span class="text-6xl mb-4 block">📦</span>
          <h3 class="text-xl font-bold text-bc-light mb-2">Gestão de Stock</h3>
          <p class="text-sm">Em desenvolvimento</p>
        </div>
      </div>
    </div>

    <!-- ═══ SEÇÃO PRODUTOS ═════════════════════════════════════════════ -->
    <div v-if="currentSection === 'produtos'" class="flex-1 flex flex-col overflow-hidden bg-bc-dark">
      <div class="flex-1 flex items-center justify-center">
        <div class="text-center text-bc-muted">
          <span class="text-6xl mb-4 block">🛍️</span>
          <h3 class="text-xl font-bold text-bc-light mb-2">Gestão de Produtos</h3>
          <p class="text-sm">Em desenvolvimento</p>
        </div>
      </div>
    </div>

    <!-- ═══ SEÇÃO RELATÓRIOS ═════════════════════════════════════════════ -->
    <div v-if="currentSection === 'relatorios'" class="flex-1 flex flex-col overflow-hidden bg-bc-dark">
      <div class="flex-1 flex items-center justify-center">
        <div class="text-center text-bc-muted">
          <span class="text-6xl mb-4 block">📊</span>
          <h3 class="text-xl font-bold text-bc-light mb-2">Relatórios</h3>
          <p class="text-sm">Em desenvolvimento</p>
        </div>
      </div>
    </div>

    <!-- ═══ SEÇÃO EQUIPE ═════════════════════════════════════════════ -->
    <div v-if="currentSection === 'equipe'" class="flex-1 flex flex-col overflow-hidden bg-bc-dark">
      <div class="flex-1 flex items-center justify-center">
        <div class="text-center text-bc-muted">
          <span class="text-6xl mb-4 block">👥</span>
          <h3 class="text-xl font-bold text-bc-light mb-2">Gestão de Equipe</h3>
          <p class="text-sm">Em desenvolvimento</p>
        </div>
      </div>
    </div>

    <!-- Espaço para o menu footer (mobile only) -->
    <div class="h-24 md:hidden"></div>
  </div>
</template>

<script setup>
import {ref, computed, reactive, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useOfflinePos } from '@/composables/useOfflinePos'

const auth = useAuthStore()
const { isOnline, syncing, trySyncNow } = useOfflinePos()

const mode = ref('search') // 'search', 'cart', 'complete'
const showMenu = ref(false)
const currentSection = ref('venda') // 'venda', 'stock', 'produtos', 'relatorios', 'equipe'
const searchInput = ref(null)

// Produtos
const allProducts = ref([])
const filtered = ref([])
const search = ref('')
const loadingProducts = ref(true)
const categories = ref([])
const selectedCategory = ref(null)
const canAddProducts = computed(() => auth.hasPosPermission('fazer_vendas'))

// Carrinho
const cart = ref([])
const discount = ref(0)
const applyVat = ref(false)
const vatRate = ref(17)
const customerName = ref('')
const processing = ref(false)

// Pagamento
const payMethod = ref('cash')
const amountPaid = ref(0)
const payMethods = [
  { value: 'cash', icon: '💵', label: 'Dinheiro' },
  { value: 'mpesa', icon: '📱', label: 'M-Pesa' },
  { value: 'emola', icon: '📲', label: 'eMola' },
]

// Peso
const weightProduct = ref(null)
const weightForm = reactive({ amount: '', unit: 'kg' })

// Recibo
const receipt = ref(null)
const showAddProduct = ref(false)

// Scanner
const scanMode = ref(localStorage.getItem('pos_scan_mode') === 'true')

// Computed
const subtotal = computed(() => cart.value.reduce((s, i) => s + i.subtotal, 0))
const vatAmount = computed(() => applyVat.value
  ? Math.max(0, subtotal.value - subtotal.value / (1 + vatRate.value / 100))
  : 0
)
const total = computed(() => {
  let t = Math.max(0, subtotal.value - (discount.value || 0))
  if (applyVat.value) t = t + vatAmount.value
  return t
})
const change = computed(() => payMethod.value === 'cash' && amountPaid.value > total.value
  ? Math.max(0, amountPaid.value - total.value)
  : 0
)
const weightTotal = computed(() => {
  if (!weightProduct.value || !weightForm.amount) return 0
  const price = parseFloat(weightProduct.value.price)
  let amount = parseFloat(weightForm.amount)
  const prodUnit = weightProduct.value.weight_unit ?? 'kg'
  if (prodUnit === 'kg' && weightForm.unit === 'g') amount = amount / 1000
  if (prodUnit === 'ml' && weightForm.unit === 'l') amount = amount * 1000
  return price * amount
})
const changeHints = computed(() => {
  const t = total.value
  const hints = new Set()
  ;[50, 100, 200, 500, 1000].forEach(v => { if (v >= t) hints.add(v) })
  const rounds = [5, 10, 20, 50, 100, 200, 500, 1000]
  for (const r of rounds) {
    const up = Math.ceil(t / r) * r
    if (up >= t && up <= t * 2) hints.add(up)
  }
  return [...hints].sort((a, b) => a - b).slice(0, 4)
})

// Formatação
const _fmt = new Intl.NumberFormat('pt-MZ', { style: 'currency', currency: 'MZN' })
const _fmtN = new Intl.NumberFormat('pt-MZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
function fmt(v) { return _fmt.format(v ?? 0) }
function fmtN(v) { return _fmtN.format(v ?? 0) + ' MZN' }
function formatDateTime(iso) {
  return new Date(iso).toLocaleString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// Métodos
async function loadCategories() {
  try {
    const response = await axios.get('/api/store/categories')
    categories.value = response.data.data || response.data
  } catch (error) {
    console.error('Erro ao carregar categorias:', error)
  }
}

async function loadProducts() {
  try {
    const response = await axios.get('/api/store/products')
    allProducts.value = response.data.data || response.data
    filterProducts()
  } catch (error) {
    console.error('Erro ao carregar produtos:', error)
  } finally {
    loadingProducts.value = false
  }
}

function filterProducts() {
  let result = allProducts.value
  
  // Filtro por categoria
  if (selectedCategory.value) {
    result = result.filter(p => p.category_id === selectedCategory.value)
  }
  
  // Filtro por busca
  const q = search.value.toLowerCase().trim()
  if (q) {
    result = result.filter(p =>
      (p.name && p.name.toLowerCase().includes(q)) ||
      (p.sku && p.sku.toLowerCase().includes(q)) ||
      (p.barcode && p.barcode.toLowerCase().includes(q))
    )
  }
  
  filtered.value = result
}

function toggleScanMode() {
  scanMode.value = !scanMode.value
  localStorage.setItem('pos_scan_mode', scanMode.value)
  if (scanMode.value && searchInput.value) searchInput.value.focus()
}

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

function clickProduct(p) {
  if (p.is_weighable) {
    weightProduct.value = p
    weightForm.amount = ''
    weightForm.unit = p.weight_unit ?? 'kg'
  } else {
    addToCart(p)
  }
}

function addToCart(product) {
  const existing = cart.value.find(i => i.product_id === product.id && !i.weight_amount)
  if (existing) {
    existing.quantity++
    updateItemSubtotal(cart.value.indexOf(existing))
  } else {
    cart.value.push({
      _key: `${product.id}_${Date.now()}`,
      product_id: product.id,
      product_name: product.name,
      product_sku: product.sku,
      unit_price: parseFloat(product.price),
      cost_price: parseFloat(product.cost_price ?? 0),
      quantity: 1,
      subtotal: parseFloat(product.price),
    })
  }
  mode.value = 'cart'
}

function confirmWeight() {
  if (!weightProduct.value || !weightForm.amount) return
  const p = weightProduct.value
  const price = parseFloat(p.price)
  const amount = parseFloat(weightForm.amount)
  cart.value.push({
    _key: `${p.id}_${Date.now()}`,
    product_id: p.id,
    product_name: p.name,
    unit_price: price,
    cost_price: parseFloat(p.cost_price ?? 0),
    quantity: 1,
    weight_amount: amount,
    weight_unit: weightForm.unit,
    subtotal: parseFloat(weightTotal.value.toFixed(2)),
  })
  weightProduct.value = null
  mode.value = 'cart'
}

function changeQty(idx, delta) {
  cart.value[idx].quantity = Math.max(1, cart.value[idx].quantity + delta)
  updateItemSubtotal(idx)
}

function updateItemSubtotal(idx) {
  const item = cart.value[idx]
  item.subtotal = parseFloat((item.unit_price * item.quantity).toFixed(2))
}

function removeItem(idx) {
  cart.value.splice(idx, 1)
}

function clearCart() {
  cart.value = []
}

async function finalizeSale() {
  if (!cart.length || processing.value) return
  processing.value = true
  // Implementar chamada de API aqui
  /*
  try {
    const response = await axios.post('/api/pos/sales', {
      items: cart.value,
      discount: discount.value,
      apply_vat: applyVat.value,
      vat_rate: vatRate.value,
      payment_method: payMethod.value,
      amount_paid: payMethod.value === 'cash' ? amountPaid.value : total.value,
      customer_name: customerName.value,
    })
    receipt.value = response.data.receipt
    cart.value = []
    mode.value = 'search'
  } catch (error) {
    console.error('Erro ao finalizar venda:', error)
  }
  */
  processing.value = false
}

function newSale() {
  receipt.value = null
  cart.value = []
  discount.value = 0
  customerName.value = ''
  amountPaid.value = 0
  mode.value = 'search'
  search.value = ''
  filterProducts()
}

function printReceipt() {
  if (!receipt.value) return
  
  const store = auth.user?.store
  let width = '80mm'
  
  // Get format from store settings
  if (store?.invoice_format === '100mm') width = '100mm'
  else if (store?.invoice_format === 'A4') width = '100%'
  
  // Build receipt HTML with customizations
  const receiptHTML = buildReceiptHTML()
  
  const win = window.open('', '_blank', 'width=400,height=600')
  win.document.write(`
    <!DOCTYPE html><html><head>
    <meta charset="utf-8">
    <title>Recibo</title>
    <style>
      body { margin:0; padding:4mm; font-family:'Courier New',monospace; font-size:12px; line-height:1.5; color:black; width:${width}; }
      @media print { body { width:${width}; margin:0; padding:4mm; } }
    </style>
    </head><body>${receiptHTML}</body></html>
  `)
  win.document.close()
  win.focus()
  setTimeout(() => { win.print(); win.close() }, 300)
}

function buildReceiptHTML() {
  if (!receipt.value) return ''
  
  const store = auth.user?.store
  const r = receipt.value
  const fmtPrice = (val) => new Intl.NumberFormat('pt-MZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val)
  
  let html = '<div style="text-align:center; margin-bottom:8px;">'
  
  // Logo
  if (store?.invoice_show_logo && store?.logo) {
    const logoUrl = store.logo.startsWith('http') ? store.logo : `/storage/${store.logo}`
    html += `<img src="${logoUrl}" style="width:40px; height:40px; margin:0 auto; object-fit:contain; margin-bottom:6px;" />`
  }
  
  // Header text
  if (store?.invoice_header_text) {
    html += `<div style="font-size:11px; margin-bottom:4px; font-style:italic;">${store.invoice_header_text}</div>`
  }
  
  html += '<div style="font-size:16px; font-weight:900; letter-spacing:2px;">BECONNECT</div>'
  html += `<div style="font-size:10px; color:#666;">${store?.name ?? 'Loja'}</div>`
  
  if (store?.address) html += `<div style="font-size:9px; color:#666;">${store.address}</div>`
  if (store?.phone) html += `<div style="font-size:9px; color:#666;">${store.phone}</div>`
  
  html += `<div style="font-size:10px; color:#666;">${new Intl.DateTimeFormat('pt-MZ', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(r.sale_at))}</div>`
  html += `<div style="font-size:10px; color:#666;">${r.local_id}</div></div>`
  
  html += '<div style="border-top:1px dashed #ccc; margin:6px 0;"></div>'
  
  // Items
  r.items?.forEach(item => {
    html += `<div style="margin-bottom:4px;">`
    html += `<div style="font-weight:700; font-size:11px;">${item.product_name}</div>`
    html += '<div style="display:flex; justify-content:space-between; color:#444; font-size:11px;">'
    
    if (item.weight_amount) {
      html += `<span>${item.weight_amount}${item.weight_unit} × ${fmtPrice(item.unit_price)}</span>`
    } else {
      html += `<span>${item.quantity} × ${fmtPrice(item.unit_price)}</span>`
    }
    html += `<span style="font-weight:700;">${fmtPrice(item.subtotal)}</span></div></div>`
  })
  
  html += '<div style="border-top:1px dashed #ccc; margin:6px 0;"></div>'
  
  // Totals
  html += `<div style="display:flex; justify-content:space-between; font-size:11px;"><span>Subtotal</span><span>${fmtPrice(r.subtotal)}</span></div>`
  if (r.discount > 0) {
    html += `<div style="display:flex; justify-content:space-between; font-size:11px; color:#d00;"><span>Desconto</span><span>- ${fmtPrice(r.discount)}</span></div>`
  }
  if (r.apply_vat) {
    html += `<div style="display:flex; justify-content:space-between; font-size:11px; color:#080;"><span>IVA (${r.vat_rate}%)</span><span>+ ${fmtPrice(r.vat_amount)}</span></div>`
  }
  
  html += '<div style="border-top:2px solid #000; margin:6px 0;"></div>'
  html += `<div style="display:flex; justify-content:space-between; font-size:14px; font-weight:900;"><span>TOTAL</span><span>${fmtPrice(r.total)}</span></div>`
  
  html += '<div style="border-top:1px dashed #ccc; margin:6px 0;"></div>'
  
  // Payment
  html += `<div style="display:flex; justify-content:space-between; font-size:11px;"><span>Forma de pagamento</span><span style="font-weight:700; text-transform:uppercase;">${r.payment_method}</span></div>`
  if (r.amount_paid > 0) {
    html += `<div style="display:flex; justify-content:space-between; font-size:11px;"><span>Valor entregue</span><span>${fmtPrice(r.amount_paid)}</span></div>`
  }
  if (r.change > 0) {
    html += `<div style="display:flex; justify-content:space-between; font-size:13px; font-weight:900; color:#080;"><span>TROCO</span><span>${fmtPrice(r.change)}</span></div>`
  }
  
  if (r.customer_name) {
    html += `<div style="margin-top:4px; font-size:11px;">Cliente: <strong>${r.customer_name}</strong></div>`
  }
  
  if (r.apply_vat) {
    html += `<div style="margin-top:6px; font-size:10px; color:#666; border-top:1px dashed #ccc; padding-top:4px;">Base tributável: ${fmtPrice(r.total - r.vat_amount)} · IVA ${r.vat_rate}%: ${fmtPrice(r.vat_amount)}</div>`
  }
  
  html += '<div style="border-top:1px dashed #ccc; margin:8px 0;"></div>'
  
  // Footer
  if (store?.invoice_footer_text) {
    html += `<div style="text-align:center; font-size:10px; color:#666; white-space:pre-line;">${store.invoice_footer_text}</div>`
  } else {
    html += '<div style="text-align:center; font-size:10px; color:#666;"><div>Obrigado pela sua compra!</div><div>beconnect.co.mz</div></div>'
  }
  
  return html
}

// ─── Funções do Header ──────────────────────────────────────────────────
function getUserRoleDisplay() {
  const role = auth.posRole
  switch (role) {
    case 'owner': return 'Dono'
    case 'admin': return 'Administrador'
    case 'manager': return 'Gerente'
    case 'cashier': return 'Caixa'
    default: return 'Funcionário'
  }
}

const canAccessStore = computed(() => {
  const role = auth.posRole
  return role === 'owner' || role === 'admin' || role === 'manager'
})

function goToStore() {
  // Redirecionar para a loja virtual
  const storeSlug = auth.user?.store?.slug
  if (storeSlug) {
    window.location.href = `/lojas/${storeSlug}`
  }
}

onMounted(() => {
  loadProducts()
  loadCategories()
  if (scanMode.value && searchInput.value) {
    setTimeout(() => searchInput.value?.focus(), 100)
  }
})
</script>

<style scoped>
::-webkit-scrollbar {
  width: 4px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: #f07820;
  border-radius: 4px;
}
</style>
