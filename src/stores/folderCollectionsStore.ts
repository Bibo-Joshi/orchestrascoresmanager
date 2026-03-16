import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { apiClients } from '@/api/client'
import type { FolderCollection } from '@/api/generated/openapi/data-contracts'

/**
 * Pinia store for managing folder collections state.
 *
 * All CRUD operations are centralized here. Components should use store
 * methods instead of calling the API directly. This ensures consistent
 * state management and reactive updates across the application.
 */
export const useFolderCollectionsStore = defineStore('folderCollections', () => {
	const folderCollections = ref<FolderCollection[]>([])
	const isLoaded = ref(false)
	const isLoading = ref(false)

	/**
	 * Initialize the store by loading data from initial state or API
	 */
	async function initialize(): Promise<void> {
		if (isLoaded.value || isLoading.value) return

		isLoading.value = true

		try {
			// Try to load from initial state first
			const initialData = loadState<FolderCollection[] | null>('orchestrascoresmanager', 'folderCollections', null)
			if (initialData && initialData.length > 0) {
				folderCollections.value = initialData
				isLoaded.value = true
				return
			}

			// Fallback to API if initial state is missing or empty
			const response = await apiClients.default.folderCollectionApiGetFolderCollections()
			folderCollections.value = response.data.ocs.data || []
			isLoaded.value = true
		} catch (error) {
			console.error('Failed to load folder collections:', error)
			folderCollections.value = []
		} finally {
			isLoading.value = false
		}
	}

	/**
	 * Get folder collections sorted by title
	 */
	const folderCollectionsSorted = computed(() => {
		return [...folderCollections.value].sort((a, b) =>
			a.title.localeCompare(b.title),
		)
	})

	/**
	 * Get a specific folder collection by ID
	 *
	 * @param id - The folder collection ID
	 */
	function getFolderCollectionById(id: number): FolderCollection | undefined {
		return folderCollections.value.find(fc => fc.id === id)
	}

	/**
	 * Create a new folder collection via API and add it to the store
	 *
	 * @param title - The title
	 * @param collectionType - The collection type ('alphabetical' or 'indexed')
	 * @param description - Optional description
	 * @return The created folder collection
	 * @throws Error if API call fails
	 */
	async function createFolderCollection(
		title: string,
		collectionType: 'alphabetical' | 'indexed',
		description?: string | null,
	): Promise<FolderCollection> {
		const response = await apiClients.default.folderCollectionApiPostFolderCollection({
			title,
			collectionType,
			description: description || null,
		})
		const created = response.data.ocs.data as FolderCollection
		// Add scoreCount: 0 since new collections have no scores
		const withCount = { ...created, scoreCount: 0 }
		folderCollections.value.unshift(withCount)
		return withCount
	}

	/**
	 * Delete a folder collection via API and remove from store
	 *
	 * @param id - The folder collection ID to remove
	 * @throws Error if API call fails
	 */
	async function deleteFolderCollection(id: number): Promise<void> {
		await apiClients.default.folderCollectionApiDeleteFolderCollection(id)
		folderCollections.value = folderCollections.value.filter(fc => fc.id !== id)
	}

	/**
	 * Increment the score count for a folder collection
	 *
	 * @param id - The folder collection ID
	 */
	function incrementScoreCount(id: number): void {
		const fc = folderCollections.value.find(fc => fc.id === id)
		if (fc) {
			fc.scoreCount = fc.scoreCount + 1
		}
	}

	/**
	 * Decrement the score count for a folder collection
	 *
	 * @param id - The folder collection ID
	 */
	function decrementScoreCount(id: number): void {
		const fc = folderCollections.value.find(fc => fc.id === id)
		if (fc && fc.scoreCount > 0) {
			fc.scoreCount = fc.scoreCount - 1
		}
	}

	/**
	 * Update the active version ID for a folder collection
	 *
	 * @param id - The folder collection ID
	 * @param activeVersionId - The new active version ID
	 */
	function updateFolderCollectionActiveVersion(id: number, activeVersionId: number | null): void {
		const fc = folderCollections.value.find(fc => fc.id === id)
		if (fc) {
			fc.activeVersionId = activeVersionId
		}
	}

	return {
		folderCollections,
		folderCollectionsSorted,
		isLoaded,
		isLoading,
		initialize,
		getFolderCollectionById,
		createFolderCollection,
		deleteFolderCollection,
		incrementScoreCount,
		decrementScoreCount,
		updateFolderCollectionActiveVersion,
	}
})
