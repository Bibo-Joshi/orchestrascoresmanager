import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { ScoreBook } from '@/api/generated/openapi/data-contracts'

export const useScoreBookSidebarStore = defineStore('scoreBookSidebar', () => {
	const selectedScoreBook = ref<ScoreBook | null>(null)
	const isOpen = ref(false)

	function openSidebar(scoreBook: ScoreBook) {
		selectedScoreBook.value = scoreBook
		isOpen.value = true
	}

	function closeSidebar() {
		isOpen.value = false
	}

	function updateSelectedScoreBook(scoreBook: ScoreBook) {
		if (selectedScoreBook.value && selectedScoreBook.value.id === scoreBook.id) {
			selectedScoreBook.value = scoreBook
		}
	}

	return {
		selectedScoreBook,
		isOpen,
		openSidebar,
		closeSidebar,
		updateSelectedScoreBook,
	}
})
