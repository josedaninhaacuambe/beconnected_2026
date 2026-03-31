<template>
  <div :class="containerClass">
    <div
      v-for="n in count"
      :key="n"
      :class="computedItemClass"
      class="animate-pulse"
    >
      <slot />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  count: { type: Number, default: 4 },
  itemClass: { type: String, default: 'skeleton h-16 rounded-xl' },
  containerClass: { type: String, default: 'space-y-2' },
  inline: { type: Boolean, default: false },
})

const computedItemClass = computed(() => `${props.itemClass} ${props.inline ? 'inline-block' : 'block'}`)
</script>
