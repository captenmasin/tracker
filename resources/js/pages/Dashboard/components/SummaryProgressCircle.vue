<script setup lang="ts">
import { cn } from '@/lib/utils'
import { computed, type HTMLAttributes } from 'vue'

const props = withDefaults(
  defineProps<{
    radius?: number
    percentage?: number | null
    displayValue?: string
    label?: string
    class?: HTMLAttributes['class']
  }>(),
  {
    radius: 48,
    percentage: null,
    displayValue: '--',
    label: 'kcal',
  },
)

type CircleStyle = {
  strokeDasharray: string
  strokeDashoffset: number
}

const circumference = computed(() => 2 * Math.PI * props.radius)

const clampedPercentage = computed(() => {
  if (props.percentage === null || props.percentage === undefined) {
    return null
  }

  return Math.min(Math.max(props.percentage, 0), 100)
})

const progressStyle = computed<CircleStyle>(() => {
  const circumferenceValue = circumference.value

  if (clampedPercentage.value === null) {
    return {
      strokeDasharray: `${circumferenceValue} ${circumferenceValue}`,
      strokeDashoffset: circumferenceValue,
    }
  }

  const offset = circumferenceValue - (clampedPercentage.value / 100) * circumferenceValue

  return {
    strokeDasharray: `${circumferenceValue} ${circumferenceValue}`,
    strokeDashoffset: offset,
  }
})
</script>

<template>
  <div :class="cn('relative h-40 w-40 p-2', props.class)">
    <svg class="h-full w-full" viewBox="0 0 100 100">
      <circle
        class="stroke-slate-200 text-slate-200"
        stroke-width="5"
        stroke="currentColor"
        fill="transparent"
        :r="props.radius"
        cx="50"
        cy="50"
      />
      <circle
        v-if="clampedPercentage !== null"
        class="stroke-current text-primary transition-all"
        stroke-width="5"
        stroke-linecap="round"
        stroke="currentColor"
        fill="transparent"
        :r="props.radius"
        cx="50"
        cy="50"
        :style="progressStyle"
        transform="rotate(-90 50 50)"
      />
      <text
        x="50"
        y="50"
        text-anchor="middle"
        class="text-xl font-semibold text-foreground"
      >
        {{ props.displayValue }}
      </text>
      <text
        x="50"
        y="66"
        text-anchor="middle"
        class="text-xs font-medium text-muted-foreground"
      >
        {{ props.label }}
      </text>
    </svg>
  </div>
</template>

