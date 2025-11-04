<script setup lang="ts">
import { cn } from '@/lib/utils'
import { computed, type HTMLAttributes } from 'vue'
import { ChevronLeft } from 'lucide-vue-next'
import { useCarousel } from './context'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const carousel = useCarousel()

const classes = computed(() =>
  cn(
    'absolute left-2 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50',
    props.class,
  ),
)
</script>

<template>
  <button
    type="button"
    :class="classes"
    @click="carousel.previous()"
    :disabled="!carousel.canScrollPrevious"
    aria-label="Previous slide"
  >
    <slot>
      <ChevronLeft class="size-4" />
    </slot>
  </button>
</template>

