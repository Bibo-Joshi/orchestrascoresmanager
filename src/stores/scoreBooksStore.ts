import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { apiClients } from '@/api/client'
import type { Score, ScoreBook } from '@/api/generated/openapi/data-contracts'

/**
 * Pinia store for managing score books state.
 *
 * All CRUD operations are centralized here. Components should use store
 * methods instead of calling the API directly. This ensures consistent
 * state management and reactive updates across the application.
 */
export const useScoreBooksStore = defineStore('scoreBooks', () => {
	const scoreBooks = ref<ScoreBook[]>([])
	const isLoaded = ref(false)
	const isLoading = ref(false)

	// Cache for scores within each score book (keyed by score book ID)
	const scoreBookScores = ref<Map<number, Score[]>>(new Map())

	/**
	 * Initialize the store by loading data from initial state or API
	 */
	async function initialize(): Promise<void> {
		if (isLoaded.value || isLoading.value) return

		isLoading.value = true

		try {
			// Try to load from initial state first
			const initialData = loadState<ScoreBook[] | null>('orchestrascoresmanager', 'scoreBooks', null)
			if (initialData && initialData.length > 0) {
				scoreBooks.value = initialData
				isLoaded.value = true
				return
			}

			// Fallback to API if initial state is missing or empty
			const response = await apiClients.default.scoreBookApiGetScoreBooks()
			scoreBooks.value = response.data.ocs.data || []
			isLoaded.value = true
		} catch (error) {
			console.error('Failed to load score books:', error)
			scoreBooks.value = []
		} finally {
			isLoading.value = false
		}
	}

	/**
	 * Get score books sorted by title
	 */
	const scoreBooksSorted = computed(() => {
		return [...scoreBooks.value].sort((a, b) =>
			a.title.localeCompare(b.title),
		)
	})

	/**
	 * Get a specific score book by ID
	 *
	 * @param id - The score book ID
	 */
	function getScoreBookById(id: number): ScoreBook | undefined {
		return scoreBooks.value.find(sb => sb.id === id)
	}

	/**
	 * Create a new score book via API and add it to the store
	 *
	 * @param title - The title of the new score book
	 * @return The created score book
	 * @throws Error if API call fails
	 */
	async function createScoreBook(title: string): Promise<ScoreBook> {
		const response = await apiClients.default.scoreBookApiPostScoreBook({ title })
		const created = response.data.ocs.data as ScoreBook
		scoreBooks.value.unshift(created)
		return created
	}

	/**
	 * Update a score book field via API and update local state
	 *
	 * @param id - The score book ID
	 * @param field - The field to update
	 * @param value - The new value
	 * @throws Error if API call fails (local state is reverted)
	 */
	async function updateScoreBookFieldApi(id: number, field: string, value: unknown): Promise<void> {
		const index = scoreBooks.value.findIndex(sb => sb.id === id)
		if (index === -1) return

		const oldValue = scoreBooks.value[index][field as keyof ScoreBook]

		// Optimistically update local state
		scoreBooks.value[index] = { ...scoreBooks.value[index], [field]: value }

		try {
			await apiClients.default.scoreBookApiPatchScoreBook(id, { [field]: value })
		} catch (error) {
			// Revert on error
			scoreBooks.value[index] = { ...scoreBooks.value[index], [field]: oldValue }
			throw error
		}
	}

	/**
	 * Delete a score book via API and remove from store
	 *
	 * @param id - The score book ID to remove
	 * @throws Error if API call fails
	 */
	async function deleteScoreBook(id: number): Promise<void> {
		await apiClients.default.scoreBookApiDeleteScoreBook(id)
		scoreBooks.value = scoreBooks.value.filter(sb => sb.id !== id)
	}

	/**
	 * Update tags for a score book via API
	 *
	 * @param id - The score book ID
	 * @param tagIds - Array of tag IDs to set
	 * @throws Error if API call fails
	 */
	async function updateScoreBookTags(id: number, tagIds: number[]): Promise<void> {
		await apiClients.default.scoreBookApiPatchScoreBook(id, { tagIds })
	}

	/**
	 * Increment the score count for a score book
	 *
	 * @param id - The score book ID
	 */
	function incrementScoreCount(id: number): void {
		const sb = scoreBooks.value.find(sb => sb.id === id)
		if (sb) {
			sb.scoreCount = sb.scoreCount + 1
		}
	}

	/**
	 * Decrement the score count for a score book
	 *
	 * @param id - The score book ID
	 */
	function decrementScoreCount(id: number): void {
		const sb = scoreBooks.value.find(sb => sb.id === id)
		if (sb && sb.scoreCount > 0) {
			sb.scoreCount = sb.scoreCount - 1
		}
	}

	/**
	 * Load scores for a specific score book
	 *
	 * @param scoreBookId - The score book ID
	 * @return Array of scores in the book
	 */
	async function loadScoreBookScores(scoreBookId: number): Promise<Score[]> {
		const response = await apiClients.default.scoreBookApiGetScoreBookScores(scoreBookId)
		const scores = response.data.ocs.data || []
		scoreBookScores.value.set(scoreBookId, scores)
		return scores
	}

	/**
	 * Get cached scores for a score book
	 *
	 * @param scoreBookId - The score book ID
	 */
	function getScoreBookScores(scoreBookId: number): Score[] {
		return scoreBookScores.value.get(scoreBookId) || []
	}

	/**
	 * Add a score to a score book via API
	 *
	 * @param scoreBookId - The score book ID
	 * @param scoreId - The score ID
	 * @param index - The index position in the book
	 * @return The updated score with scoreBook info
	 */
	async function addScoreToBook(scoreBookId: number, scoreId: number, index: number): Promise<Score> {
		await apiClients.default.scoreBookApiPostScoreBookScore(scoreBookId, { scoreId, index })

		// Update cached scores if loaded
		const cached = scoreBookScores.value.get(scoreBookId)
		if (cached) {
			// Find the score to add from a fresh load
			const response = await apiClients.default.scoreBookApiGetScoreBookScores(scoreBookId)
			scoreBookScores.value.set(scoreBookId, response.data.ocs.data || [])
		}

		// Increment the score count
		incrementScoreCount(scoreBookId)

		// Return a score object with the updated scoreBook info
		// The caller should provide the score entity
		return { id: scoreId, scoreBook: { id: scoreBookId, index } } as Score
	}

	/**
	 * Remove a score from a score book via API
	 *
	 * @param scoreBookId - The score book ID
	 * @param scoreId - The score ID
	 */
	async function removeScoreFromBook(scoreBookId: number, scoreId: number): Promise<void> {
		await apiClients.default.scoreBookApiDeleteScoreBookScore(scoreBookId, scoreId)

		// Update cached scores if loaded
		const cached = scoreBookScores.value.get(scoreBookId)
		if (cached) {
			scoreBookScores.value.set(
				scoreBookId,
				cached.filter(s => s.id !== scoreId),
			)
		}

		// Decrement the score count
		decrementScoreCount(scoreBookId)
	}

	/**
	 * Get occupied indices in a score book (for validation)
	 * Note: This relies on cached data. Call loadScoreBookScores() first
	 * to ensure accurate results.
	 *
	 * @param scoreBookId - The score book ID
	 * @param excludeScoreId - Optional score ID to exclude from the result
	 * @return Set of occupied indices, empty if scores not loaded
	 */
	function getOccupiedIndices(scoreBookId: number, excludeScoreId?: number): Set<number> {
		const scores = getScoreBookScores(scoreBookId)
		const indices = new Set<number>()
		for (const score of scores) {
			if (score.scoreBook?.index !== undefined && score.id !== excludeScoreId) {
				indices.add(score.scoreBook.index)
			}
		}
		return indices
	}

	return {
		scoreBooks,
		scoreBooksSorted,
		scoreBookScores,
		isLoaded,
		isLoading,
		initialize,
		getScoreBookById,
		createScoreBook,
		updateScoreBookFieldApi,
		updateScoreBookTags,
		deleteScoreBook,
		incrementScoreCount,
		decrementScoreCount,
		loadScoreBookScores,
		getScoreBookScores,
		addScoreToBook,
		removeScoreFromBook,
		getOccupiedIndices,
	}
})
