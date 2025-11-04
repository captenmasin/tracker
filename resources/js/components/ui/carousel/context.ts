import type { ComputedRef, Ref } from 'vue'
import { createContext } from 'reka-ui'

export interface CarouselContext {
  orientation: ComputedRef<'horizontal' | 'vertical'>
  currentIndex: Ref<number>
  registerItem: (element: HTMLElement) => void
  unregisterItem: (element: HTMLElement) => void
  goTo: (index: number) => void
  next: () => void
  previous: () => void
  canScrollNext: ComputedRef<boolean>
  canScrollPrevious: ComputedRef<boolean>
  slideCount: ComputedRef<number>
}

export const [useCarousel, provideCarouselContext] =
  createContext<CarouselContext>('Carousel')

