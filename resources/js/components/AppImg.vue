<template>
  <img
    v-bind="$attrs"
    :src="currentSrc"
    @error="onError"
  />
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  src:      { type: String, default: '' },
  fallback: { type: String, default: '' },
  type:     { type: String, default: 'product' }, // 'product' | 'store' | 'banner'
})

const DEFAULTS = {
  product: '/images/Produto.png',
  store:   '/images/Lojadefault.png',
  banner:  '/images/Lojadefault.png',
}

function resolve(src) {
  return src || props.fallback || DEFAULTS[props.type] || DEFAULTS.product
}

const currentSrc = ref(resolve(props.src))
const failed = ref(false)

watch(() => props.src, (val) => {
  failed.value = false
  currentSrc.value = resolve(val)
})

function onError() {
  if (failed.value) return
  failed.value = true
  currentSrc.value = props.fallback || DEFAULTS[props.type] || DEFAULTS.product
}
</script>
