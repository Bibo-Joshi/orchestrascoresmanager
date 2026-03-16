import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useSetlistSidebarStore = defineStore('setlistSidebar', () => {
	const isOpen = ref(false)

	function openSidebar() {
		isOpen.value = true
	}

	function closeSidebar() {
		isOpen.value = false
	}

	function toggleSidebar() {
		isOpen.value = !isOpen.value
	}

	return {
		isOpen,
		openSidebar,
		closeSidebar,
		toggleSidebar,
	}
})
