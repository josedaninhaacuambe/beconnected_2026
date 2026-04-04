import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useCartStore = defineStore('cart', () => {
    const itemsByStore = ref([])
    const loading = ref(false)
    // Contador local de itens — actualizado optimisticamente sem esperar pelo servidor
    const localCount = ref(0)

    const totalItems = computed(() =>
        localCount.value || itemsByStore.value.reduce((sum, s) => sum + s.items.length, 0)
    )

    const subtotal = computed(() =>
        itemsByStore.value.reduce((sum, s) => sum + s.store_subtotal, 0)
    )

    async function fetchCart() {
        loading.value = true
        try {
            const { data } = await axios.get('/cart')
            itemsByStore.value = data.items_by_store ?? []
            localCount.value = data.total_items ?? itemsByStore.value.reduce((sum, s) => sum + s.items.length, 0)
        } finally {
            loading.value = false
        }
    }

    // addItem com optimistic update:
    // 1. Incrementa o contador local imediatamente (feedback visual instantâneo)
    // 2. Envia POST em background
    // 3. Sincroniza o carrinho completo sem bloquear o utilizador
    async function addItem(productId, quantity = 1, productInfo = null) {
        // Optimistic update — mostrar feedback imediato
        localCount.value += quantity

        if (productInfo) {
            _applyOptimisticAdd(productId, quantity, productInfo)
        }

        try {
            await axios.post('/cart/items', { product_id: productId, quantity })
        } catch (e) {
            // Reverter em caso de erro
            localCount.value = Math.max(0, localCount.value - quantity)
            if (productInfo) {
                _revertOptimisticAdd(productId, quantity, productInfo)
            }
            throw e
        }

        // Sincronizar em background — sem bloquear o utilizador
        fetchCart().catch(() => {})
    }

    function _applyOptimisticAdd(productId, quantity, productInfo) {
        const storeGroup = itemsByStore.value.find(s => s.store.id === productInfo.store?.id)
        if (storeGroup) {
            const existing = storeGroup.items.find(i => i.product.id === productId)
            if (existing) {
                existing.quantity += quantity
                existing.subtotal = existing.unit_price * existing.quantity
                storeGroup.store_subtotal += productInfo.price * quantity
            } else {
                storeGroup.items.push({
                    id: `temp_${productId}`,
                    product: { id: productId, name: productInfo.name, images: productInfo.images, in_stock: true, available_stock: productInfo.stock?.quantity ?? 99 },
                    quantity,
                    unit_price: productInfo.price,
                    subtotal: productInfo.price * quantity,
                })
                storeGroup.store_subtotal += productInfo.price * quantity
            }
        } else if (productInfo.store) {
            itemsByStore.value.push({
                store: productInfo.store,
                items: [{
                    id: `temp_${productId}`,
                    product: { id: productId, name: productInfo.name, images: productInfo.images, in_stock: true, available_stock: productInfo.stock?.quantity ?? 99 },
                    quantity,
                    unit_price: productInfo.price,
                    subtotal: productInfo.price * quantity,
                }],
                store_subtotal: productInfo.price * quantity,
            })
        }
    }

    function _revertOptimisticAdd(productId, quantity, productInfo) {
        const storeGroup = itemsByStore.value.find(s => s.store.id === productInfo.store?.id)
        if (!storeGroup) return
        const existing = storeGroup.items.find(i => i.product.id === productId)
        if (!existing) return
        existing.quantity -= quantity
        storeGroup.store_subtotal -= productInfo.price * quantity
        if (existing.quantity <= 0) {
            storeGroup.items = storeGroup.items.filter(i => i.product.id !== productId)
        }
        if (!storeGroup.items.length) {
            itemsByStore.value = itemsByStore.value.filter(s => s.store.id !== productInfo.store?.id)
        }
    }

    async function updateItem(itemId, quantity) {
        await axios.put(`/cart/items/${itemId}`, { quantity })
        fetchCart().catch(() => {})
    }

    async function removeItem(itemId) {
        // Optimistic: remover localmente imediato
        for (const storeGroup of itemsByStore.value) {
            const idx = storeGroup.items.findIndex(i => i.id === itemId)
            if (idx !== -1) {
                const item = storeGroup.items[idx]
                storeGroup.store_subtotal -= item.subtotal
                storeGroup.items.splice(idx, 1)
                localCount.value = Math.max(0, localCount.value - 1)
                break
            }
        }
        itemsByStore.value = itemsByStore.value.filter(s => s.items.length > 0)

        await axios.delete(`/cart/items/${itemId}`)
        fetchCart().catch(() => {})
    }

    async function clearCart() {
        itemsByStore.value = []
        localCount.value = 0
        await axios.delete('/cart')
    }

    return { itemsByStore, loading, totalItems, subtotal, fetchCart, addItem, updateItem, removeItem, clearCart }
})
