<script setup lang="ts">
import { cn } from '@/lib/utils'
import { computed, type HTMLAttributes } from 'vue'
import { useCarousel } from './context'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const carousel = useCarousel()

const classes = computed(() =>
  cn(
    'flex gap-4 transition-transform duration-300 ease-out will-change-transform',
    carousel.orientation.value === 'vertical' ? 'flex-col' : 'flex-row',
    props.class,
  ),
)

const transform = computed(() => {
  const axis = carousel.orientation.value === 'vertical' ? 'Y' : 'X'
  return `translate${axis}(-${carousel.currentIndex.value * 100}%)`
})
</script>

<template>
  <div data-slot="carousel-viewport" class="overflow-hidden">
    <div
      :class="classes"
      :style="{ transform }"
    >
      <slot />
    </div>
  </div>
</template>

