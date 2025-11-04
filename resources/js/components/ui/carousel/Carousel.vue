<script setup lang="ts">
import { cn } from '@/lib/utils'
import { computed, ref, watch, type HTMLAttributes } from 'vue'
import { provideCarouselContext } from './context'

const props = withDefaults(
  defineProps<{
    orientation?: 'horizontal' | 'vertical'
    loop?: boolean
    class?: HTMLAttributes['class']
  }>(),
  {
    orientation: 'horizontal',
    loop: false,
  },
)

const items = ref<HTMLElement[]>([])
const currentIndex = ref(0)

const orientation = computed(() => props.orientation)
const loop = computed(() => props.loop)

function registerItem(element: HTMLElement) {
  if (!items.value.includes(element)) {
    items.value = [...items.value, element]
  }
}

function unregisterItem(element: HTMLElement) {
  items.value = items.value.filter((item) => item !== element)
}

function normalizeIndex(index: number) {
  const count = items.value.length

  if (count === 0) {
    return 0
  }

  if (loop.value) {
    return ((index % count) + count) % count
  }

  return Math.min(Math.max(index, 0), count - 1)
}

function goTo(index: number) {
  currentIndex.value = normalizeIndex(index)
}

function next() {
  if (!items.value.length) {
    return
  }

  if (loop.value || currentIndex.value < items.value.length - 1) {
    goTo(currentIndex.value + 1)
  }
}

function previous() {
  if (!items.value.length) {
    return
  }

  if (loop.value || currentIndex.value > 0) {
    goTo(currentIndex.value - 1)
  }
}

const canScrollNext = computed(() => {
  if (!items.value.length) {
    return false
  }

  return loop.value || currentIndex.value < items.value.length - 1
})

const canScrollPrevious = computed(() => {
  if (!items.value.length) {
    return false
  }

  return loop.value || currentIndex.value > 0
})

const slideCount = computed(() => items.value.length)

watch(items, (value) => {
  if (!value.length) {
    currentIndex.value = 0
    return
  }

  if (currentIndex.value > value.length - 1) {
    currentIndex.value = value.length - 1
  }
})

provideCarouselContext({
  orientation,
  currentIndex,
  registerItem,
  unregisterItem,
  goTo,
  next,
  previous,
  canScrollNext,
  canScrollPrevious,
  slideCount,
})
</script>

<template>
  <div
    data-slot="carousel"
    :class="cn('relative w-full', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </div>
</template>

