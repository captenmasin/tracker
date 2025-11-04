<script setup lang="ts">
import { cn } from '@/lib/utils'
import { computed, type HTMLAttributes } from 'vue'
import { ChevronRight } from 'lucide-vue-next'
import { useCarousel } from './context'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const carousel = useCarousel()

const classes = computed(() =>
  cn(
    'absolute right-2 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50',
    props.class,
  ),
)
</script>

<template>
  <button
    type="button"
    :class="classes"
    @click="carousel.next()"
    :disabled="!carousel.canScrollNext"
    aria-label="Next slide"
  >
    <slot>
      <ChevronRight class="size-4" />
    </slot>
  </button>
</template>

