import { defineStore } from 'pinia'
import { ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { apiClients } from '@/api/client'

export interface Tag {
	id: number
	name: string
}

/**
 * Pinia store for managing tags state.
 *
 * All CRUD operations are centralized here. Components should use store
 * methods instead of calling the API directly. This ensures consistent
 * state management and reactive updates across the application.
 */
export const useTagsStore = defineStore('tags', () => {
	const tags = ref<Tag[]>([])
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
			const initialData = loadState<Tag[] | null>('orchestrascoresmanager', 'tags', null)
			if (initialData && initialData.length > 0) {
				tags.value = initialData
				isLoaded.value = true
				return
			}

			// Fallback to API if initial state is missing or empty
			const response = await apiClients.default.tagApiGetTags()
			tags.value = response.data.ocs.data || []
			isLoaded.value = true
		} catch (error) {
			console.error('Failed to load tags:', error)
			tags.value = []
		} finally {
			isLoading.value = false
		}
	}

	/**
	 * Get or create a tag by name. If a tag with the given name exists,
	 * returns its ID. Otherwise creates a new tag via API and returns the new ID.
	 *
	 * @param name - The tag name (will be normalized to lowercase)
	 * @return The tag ID
	 * @throws Error if API call fails
	 */
	async function getOrCreateTag(name: string): Promise<number> {
		const normalizedName = name.toLowerCase()
		const existing = tags.value.find(t => t.name === normalizedName)
		if (existing) {
			return existing.id
		}

		// Create new tag via API
		const response = await apiClients.default.tagApiPostTag({ name: normalizedName })
		const newTag = response.data.ocs.data
		tags.value.push(newTag)
		return newTag.id
	}

	/**
	 * Convert an array of tag names to tag IDs, creating new tags as needed
	 *
	 * @param names - Array of tag names
	 * @return Array of tag IDs
	 * @throws Error if any API call fails
	 */
	async function namesToIds(names: string[]): Promise<number[]> {
		const ids: number[] = []
		for (const name of names) {
			const id = await getOrCreateTag(name)
			ids.push(id)
		}
		return ids
	}

	return {
		tags,
		isLoaded,
		isLoading,
		initialize,
		getOrCreateTag,
		namesToIds,
	}
})
