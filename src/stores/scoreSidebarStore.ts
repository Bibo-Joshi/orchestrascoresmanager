import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Score } from '@/api/generated/openapi/data-contracts'

export const useScoreSidebarStore = defineStore('scoreSidebar', () => {
	const selectedScore = ref<Score | null>(null)
	const isOpen = ref(false)

	function openSidebar(score: Score) {
		selectedScore.value = score
		isOpen.value = true
	}

	function closeSidebar() {
		isOpen.value = false
	}

	function updateSelectedScore(score: Score) {
		if (selectedScore.value && selectedScore.value.id === score.id) {
			selectedScore.value = score
		}
	}

	return {
		selectedScore,
		isOpen,
		openSidebar,
		closeSidebar,
		updateSelectedScore,
	}
})
