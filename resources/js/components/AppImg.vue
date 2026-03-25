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
  src: { type: String, default: '' },
  fallback: { type: String, default: '' },
})

const PLACEHOLDER = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\' viewBox=\'0 0 200 200\'%3E%3Crect width=\'200\' height=\'200\' fill=\'%231e293b\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-size=\'48\' fill=\'%23475569\'%3E📦%3C/text%3E%3C/svg%3E'

const currentSrc = ref(props.src || PLACEHOLDER)
const failed = ref(false)

watch(() => props.src, (val) => {
  failed.value = false
  currentSrc.value = val || PLACEHOLDER
})

function onError() {
  if (failed.value) return
  failed.value = true
  currentSrc.value = props.fallback || PLACEHOLDER
}
</script>
