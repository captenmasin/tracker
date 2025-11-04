<script setup lang="ts">
import { cn } from '@/lib/utils'
import { computed, onBeforeUnmount, onMounted, ref, type HTMLAttributes } from 'vue'
import { useCarousel } from './context'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const carousel = useCarousel()
const itemRef = ref<HTMLElement | null>(null)

onMounted(() => {
  if (itemRef.value) {
    carousel.registerItem(itemRef.value)
  }
})

onBeforeUnmount(() => {
  if (itemRef.value) {
    carousel.unregisterItem(itemRef.value)
  }
})

const classes = computed(() =>
  cn(
    'min-w-0 shrink-0 grow-0 basis-full',
    carousel.orientation.value === 'vertical' ? 'basis-auto' : '',
    props.class,
  ),
)
</script>

<template>
  <div
    ref="itemRef"
    data-slot="carousel-item"
    :class="classes"
  >
    <slot />
  </div>
</template>

