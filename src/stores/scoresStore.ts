import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { apiClients } from '@/api/client'
import type { Score } from '@/api/generated/openapi/data-contracts'

/**
 * Pinia store for managing scores state.
 *
 * All CRUD operations are centralized here. Components should use store
 * methods instead of calling the API directly. This ensures consistent
 * state management and reactive updates across the application.
 */
export const useScoresStore = defineStore('scores', () => {
	const scores = ref<Score[]>([])
	const isLoaded = ref(false)
	const isLoading = ref(false)

	/**
	 * Get scores sorted by title
	 */
	const scoresSorted = computed(() => {
		return [...scores.value].sort((a, b) =>
			a.title.localeCompare(b.title),
		)
	})

	/**
	 * Initialize the store by loading data from initial state or API
	 */
	async function initialize(): Promise<void> {
		if (isLoaded.value || isLoading.value) return

		isLoading.value = true

		try {
			// Try to load from initial state first
			const initialData = loadState<Score[] | null>('orchestrascoresmanager', 'scores', null)
			if (initialData && initialData.length > 0) {
				scores.value = initialData
				isLoaded.value = true
				return
			}

			// Fallback to API if initial state is missing or empty
			const response = await apiClients.default.scoreApiGetScores()
			scores.value = response.data.ocs.data || []
			isLoaded.value = true
		} catch (error) {
			console.error('Failed to load scores:', error)
			scores.value = []
		} finally {
			isLoading.value = false
		}
	}

	/**
	 * Get a specific score by ID
	 *
	 * @param id - The score ID
	 */
	function getScoreById(id: number): Score | undefined {
		return scores.value.find(s => s.id === id)
	}

	/**
	 * Create a new score via API and add it to the store
	 *
	 * @param title - The title of the new score
	 * @return The created score
	 * @throws Error if API call fails
	 */
	async function createScore(title: string): Promise<Score> {
		const response = await apiClients.default.scoreApiPostScore({ title })
		const created = response.data.ocs.data as Score
		scores.value.unshift(created)
		return created
	}

	/**
	 * Update a score field via API and update local state
	 *
	 * @param id - The score ID
	 * @param field - The field to update
	 * @param value - The new value
	 * @throws Error if API call fails (local state is reverted)
	 */
	async function updateScoreFieldApi(id: number, field: string, value: unknown): Promise<void> {
		const index = scores.value.findIndex(s => s.id === id)
		if (index === -1) return

		const oldValue = scores.value[index][field as keyof Score]

		// Optimistically update local state
		scores.value[index] = { ...scores.value[index], [field]: value }

		try {
			await apiClients.default.scoreApiPatchScore(id, { [field]: value })
		} catch (error) {
			// Revert on error
			scores.value[index] = { ...scores.value[index], [field]: oldValue }
			throw error
		}
	}

	/**
	 * Update the scoreBook assignment for a score (local state only)
	 *
	 * @param id - The score ID
	 * @param scoreBook - The new scoreBook object or null to remove
	 */
	function updateScoreBookAssignment(id: number, scoreBook: { id: number; index: number } | null): void {
		const index = scores.value.findIndex(s => s.id === id)
		if (index !== -1) {
			scores.value[index] = { ...scores.value[index], scoreBook }
		}
	}

	/**
	 * Update the scoreBook assignment for a score via API
	 *
	 * @param id - The score ID
	 * @param scoreBook - The new scoreBook object or null to remove
	 * @throws Error if API call fails
	 */
	async function updateScoreBookAssignmentApi(id: number, scoreBook: { id: number; index: number } | null): Promise<void> {
		await apiClients.default.scoreApiPatchScore(id, { scoreBook })
		updateScoreBookAssignment(id, scoreBook)
	}

	/**
	 * Update tags for a score via API
	 *
	 * @param id - The score ID
	 * @param tagIds - Array of tag IDs to set
	 * @throws Error if API call fails
	 */
	async function updateScoreTags(id: number, tagIds: number[]): Promise<void> {
		await apiClients.default.scoreApiPatchScore(id, { tagIds })
	}

	/**
	 * Delete a score via API and remove from store
	 *
	 * @param id - The score ID to remove
	 * @throws Error if API call fails
	 */
	async function deleteScore(id: number): Promise<void> {
		await apiClients.default.scoreApiDeleteScore(id)
		scores.value = scores.value.filter(s => s.id !== id)
	}

	return {
		scores,
		scoresSorted,
		isLoaded,
		isLoading,
		initialize,
		getScoreById,
		createScore,
		updateScoreFieldApi,
		updateScoreBookAssignment,
		updateScoreBookAssignmentApi,
		updateScoreTags,
		deleteScore,
	}
})
