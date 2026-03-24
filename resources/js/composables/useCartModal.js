import { reactive } from 'vue'

const state = reactive({ visible: false, product: null })

export function useCartModal() {
  function open(product) {
    state.product = product
    state.visible = true
  }
  function close() {
    state.visible = false
    state.product = null
  }
  return { state, open, close }
}
