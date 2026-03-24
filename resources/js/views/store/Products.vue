<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-bc-light">Produtos</h1>
      <RouterLink to="/loja/produtos/novo" class="btn-gold px-4 py-2 text-sm">+ Novo Produto</RouterLink>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl"></div>
    </div>

    <div v-else-if="products.length === 0" class="text-center py-16 text-bc-muted">
      Nenhum produto ainda. <RouterLink to="/loja/produtos/novo" class="text-bc-gold hover:underline">Adicionar o primeiro</RouterLink>
    </div>

    <div v-else class="card-african overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-bc-gold/20 text-bc-muted text-xs uppercase">
            <th class="text-left p-4">Produto</th>
            <th class="text-left p-4">Preço</th>
            <th class="text-left p-4">Stock</th>
            <th class="text-left p-4">Estado</th>
            <th class="text-right p-4">Acções</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.id" class="border-b border-bc-gold/10 hover:bg-bc-gold/5">
            <td class="p-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg overflow-hidden bg-bc-surface-2 flex-shrink-0 flex items-center justify-center">
                  <img v-if="p.images?.[0]"
                    :src="p.images[0].startsWith('http') ? p.images[0] : `/storage/${p.images[0]}`"
                    class="w-full h-full object-cover" />
                  <span v-else class="text-base">📦</span>
                </div>
                <div>
                  <p class="text-bc-light font-medium">{{ p.name }}</p>
                  <p class="text-bc-muted text-xs font-mono">{{ p.sku || '—' }}</p>
                </div>
              </div>
            </td>
            <td class="p-4 text-bc-gold font-semibold">{{ formatMZN(p.price) }}</td>
            <td class="p-4">
              <span :class="(p.stock?.quantity ?? 0) > 0 ? 'text-green-400' : 'text-red-400'" class="font-medium">
                {{ p.stock?.quantity ?? 0 }}
              </span>
            </td>
            <td class="p-4">
              <span :class="p.is_active ? 'text-green-400' : 'text-bc-muted'">
                {{ p.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="p-4 text-right">
              <RouterLink :to="`/loja/produtos/${p.slug}/editar`" class="text-bc-gold hover:underline text-xs mr-3">Editar</RouterLink>
              <button @click="deleteProduct(p)" class="text-red-400 hover:text-red-300 text-xs">Remover</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const products = ref([])
const loading = ref(true)

function formatMZN(v) {
  return (v ?? 0).toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN', minimumFractionDigits: 2 })
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/store/products')
    products.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function deleteProduct(p) {
  if (!confirm(`Remover "${p.name}"?`)) return
  await axios.delete(`/store/products/${p.id}`)
  products.value = products.value.filter(x => x.id !== p.id)
}

onMounted(load)
</script>
