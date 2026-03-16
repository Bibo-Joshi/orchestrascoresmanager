import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClients } from '@/api/client'
import type { SetlistEntry } from '@/api/generated/openapi/data-contracts'

/**
 * Pinia store for managing setlist entries state.
 *
 * All CRUD operations are centralized here. Components should use store
 * methods instead of calling the API directly. This ensures consistent
 * state management and reactive updates across the application.
 */
export const useSetlistEntriesStore = defineStore('setlistEntries', () => {
	const entries = ref<Map<number, SetlistEntry[]>>(new Map())

	/**
	 * Get entries for a specific setlist sorted by index
	 * @param setlistId - The setlist ID
	 */
	function getEntriesBySetlistId(setlistId: number): SetlistEntry[] {
		const setlistEntries = entries.value.get(setlistId) || []
		return [...setlistEntries].sort((a, b) => a.index - b.index)
	}

	/**
	 * Load entries for a specific setlist from API
	 * @param setlistId - The setlist ID
	 * @throws Error if API call fails
	 */
	async function loadEntries(setlistId: number): Promise<void> {
		const response = await apiClients.default.setlistApiGetSetlistEntries(setlistId)
		const loadedEntries = response.data.ocs.data || []
		entries.value.set(setlistId, loadedEntries)
	}

	/**
	 * Create a new setlist entry via API and add it to the store
	 * @param setlistId - The setlist ID
	 * @param index - The index position for the entry
	 * @param scoreId - The score ID (optional, for score entries)
	 * @param breakDuration - The break duration in seconds (optional, for break entries)
	 * @param moderationDuration - The moderation duration in seconds (optional)
	 * @param comment - Optional comment for the entry
	 * @return The created setlist entry
	 * @throws Error if API call fails
	 */
	async function createEntry(
		setlistId: number,
		index: number,
		scoreId: number | null = null,
		breakDuration: number | null = null,
		moderationDuration: number | null = null,
		comment: string | null = null,
	): Promise<SetlistEntry> {
		const response = await apiClients.default.setlistApiPostSetlistEntry(setlistId, {
			index,
			scoreId,
			breakDuration,
			moderationDuration,
			comment,
		})
		const created = response.data.ocs.data as SetlistEntry
		const setlistEntries = entries.value.get(setlistId) || []
		setlistEntries.push(created)
		entries.value.set(setlistId, setlistEntries)
		return created
	}

	/**
	 * Update a setlist entry via API and update in store
	 * @param entryId - The entry ID
	 * @param updates - Partial entry data to update
	 * @return The updated setlist entry
	 * @throws Error if API call fails
	 */
	async function updateEntry(
		entryId: number,
		updates: Partial<Omit<SetlistEntry, 'id' | 'setlistId'>>,
	): Promise<SetlistEntry> {
		const response = await apiClients.default.setlistEntryApiPatchSetlistEntry(entryId, updates)
		const updated = response.data.ocs.data as SetlistEntry

		// Update in store
		const setlistEntries = entries.value.get(updated.setlistId)
		if (setlistEntries) {
			const index = setlistEntries.findIndex(e => e.id === entryId)
			if (index !== -1) {
				setlistEntries[index] = updated
			}
		}

		return updated
	}

	/**
	 * Delete a setlist entry via API and remove from store
	 * @param entryId - The entry ID to remove
	 * @param setlistId - The setlist ID (needed to update the correct list)
	 * @throws Error if API call fails
	 */
	async function deleteEntry(entryId: number, setlistId: number): Promise<void> {
		await apiClients.default.setlistEntryApiDeleteSetlistEntry(entryId)
		const setlistEntries = entries.value.get(setlistId)
		if (setlistEntries) {
			entries.value.set(setlistId, setlistEntries.filter(e => e.id !== entryId))
		}
	}

	/**
	 * Batch update setlist entries via API
	 * @param updates - Array of entry updates with IDs
	 * @return The updated setlist entries
	 * @throws Error if API call fails
	 */
	async function batchUpdateEntries(
		updates: Array<{
			id: number
			index?: number
			comment?: string | null
			moderationDuration?: number | null
			breakDuration?: number | null
			scoreId?: number | null
		}>,
	): Promise<SetlistEntry[]> {
		const response = await apiClients.default.setlistEntryApiPostSetlistEntriesBatch({
			entries: updates,
		})
		const updated = response.data.ocs.data as SetlistEntry[]

		// Update in store
		if (updated.length > 0) {
			const setlistId = updated[0].setlistId
			const setlistEntries = entries.value.get(setlistId) || []
			updated.forEach(updatedEntry => {
				const index = setlistEntries.findIndex(e => e.id === updatedEntry.id)
				if (index !== -1) {
					setlistEntries[index] = updatedEntry
				}
			})
			entries.value.set(setlistId, setlistEntries)
		}

		return updated
	}

	return {
		entries,
		getEntriesBySetlistId,
		loadEntries,
		createEntry,
		updateEntry,
		deleteEntry,
		batchUpdateEntries,
	}
})
