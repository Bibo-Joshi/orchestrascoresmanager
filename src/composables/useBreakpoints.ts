import { computed, type Ref, ref, onUnmounted, ComputedRef } from 'vue'

const useMediaQueryComposable = (query: string): Ref<boolean> => {
	const matches = ref(window.matchMedia(query).matches)
	const mq = window.matchMedia(query)

	const handler = (event: MediaQueryListEvent) => {
		matches.value = event.matches
	}

	mq.addEventListener('change', handler)
	onUnmounted(() => mq.removeEventListener('change', handler))

	return matches
}

export const useBreakpoints = () => {
	// Base OrUp refs (matchMedia)
	const isSmOrUp = useMediaQueryComposable('(min-width: 640px)')
	const isMdOrUp = useMediaQueryComposable('(min-width: 768px)')
	const isLgOrUp = useMediaQueryComposable('(min-width: 1024px)')
	const isXlOrUp = useMediaQueryComposable('(min-width: 1280px)')
	const is2xlOrUp = useMediaQueryComposable('(min-width: 1536px)')

	// OrDown (computed inverses)
	const isSmOrDown = computed(() => !isSmOrUp.value)
	const isMdOrDown = computed(() => !isMdOrUp.value)
	const isLgOrDown = computed(() => !isLgOrUp.value)
	const isXlOrDown = computed(() => !isXlOrUp.value)
	const is2xlOrDown = computed(() => !is2xlOrUp.value)

	// High level helpers
	const isMobile = computed(() => isMdOrDown.value)
	const isTablet = computed(() => isLgOrDown.value && !isMobile.value)
	const isDesktop = computed(() => isLgOrUp.value)
	const screenSizeCategory = computed(() =>
		isMobile.value ? 'mobile' : isTablet.value ? 'tablet' : 'desktop',
	)

	// Component helpers
	const columnPin: ComputedRef<false | 'left'> = computed((): false | 'left' => isMobile.value ? false : 'left')
	const buttonSize = computed(() => isDesktop.value ? 'normal' : 'small')
	function buttonText(text: string) {
		return isMobile.value ? undefined : text
	}

	return {
		// OrUp (reactive refs)
		isSmOrUp,
		isMdOrUp,
		isLgOrUp,
		isXlOrUp,
		is2xlOrUp,

		// OrDown
		isSmOrDown,
		isMdOrDown,
		isLgOrDown,
		isXlOrDown,
		is2xlOrDown,

		// High level helpers
		isMobile,
		isTablet,
		isDesktop,
		screenSizeCategory,

		// Component helpers
		columnPin,
		buttonSize,
		buttonText,
	}
}
