import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useCartStore = defineStore('cart', () => {
    const itemsByStore = ref([])
    const loading = ref(false)

    const totalItems = computed(() =>
        itemsByStore.value.reduce((sum, s) => sum + s.items.length, 0)
    )

    const subtotal = computed(() =>
        itemsByStore.value.reduce((sum, s) => sum + s.store_subtotal, 0)
    )

    async function fetchCart() {
        loading.value = true
        try {
            const { data } = await axios.get('/cart')
            itemsByStore.value = data.items_by_store
        } finally {
            loading.value = false
        }
    }

    async function addItem(productId, quantity = 1) {
        await axios.post('/cart/items', { product_id: productId, quantity })
        await fetchCart()
    }

    async function updateItem(itemId, quantity) {
        await axios.put(`/cart/items/${itemId}`, { quantity })
        await fetchCart()
    }

    async function removeItem(itemId) {
        await axios.delete(`/cart/items/${itemId}`)
        await fetchCart()
    }

    async function clearCart() {
        await axios.delete('/cart')
        itemsByStore.value = []
    }

    return { itemsByStore, loading, totalItems, subtotal, fetchCart, addItem, updateItem, removeItem, clearCart }
})
