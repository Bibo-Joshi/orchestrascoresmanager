import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { apiClients } from '@/api/client'
import type { Setlist } from '@/api/generated/openapi/data-contracts'

/**
 * Pinia store for managing setlists state.
 *
 * All CRUD operations are centralized here. Components should use store
 * methods instead of calling the API directly. This ensures consistent
 * state management and reactive updates across the application.
 */
export const useSetlistsStore = defineStore('setlists', () => {
	const setlists = ref<Setlist[]>([])
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
			const initialData = loadState<Setlist[] | null>('orchestrascoresmanager', 'setlists', null)
			if (initialData && initialData.length > 0) {
				setlists.value = initialData
				isLoaded.value = true
				return
			}

			// Fallback to API if initial state is missing or empty
			const response = await apiClients.default.setlistApiGetSetlists({ filter: 'all' })
			setlists.value = response.data.ocs.data || []
			isLoaded.value = true
		} catch (error) {
			console.error('Failed to load setlists:', error)
			setlists.value = []
		} finally {
			isLoading.value = false
		}
	}

	/**
	 * Get setlists filtered by criteria
	 *
	 * @param isDraft - Filter by draft status
	 * @param isPublished - Filter by published status
	 * @param dateFilter - Filter by date: 'future', 'past', or 'unscheduled'
	 */
	function getFilteredSetlists(
		isDraft?: boolean,
		isPublished?: boolean,
		dateFilter?: 'future' | 'past' | 'unscheduled',
	): Setlist[] {
		const now = new Date()

		return setlists.value.filter(setlist => {
			// Draft filter
			if (isDraft !== undefined && setlist.isDraft !== isDraft) {
				return false
			}

			// Published filter
			if (isPublished !== undefined && setlist.isPublished !== isPublished) {
				return false
			}

			// Date filter
			if (dateFilter) {
				if (dateFilter === 'unscheduled') {
					if (setlist.startDateTime !== null) return false
				} else if (dateFilter === 'future') {
					if (setlist.startDateTime === null) return false
					const setlistDate = new Date(setlist.startDateTime)
					if (setlistDate <= now) return false
				} else if (dateFilter === 'past') {
					if (setlist.startDateTime === null) return false
					const setlistDate = new Date(setlist.startDateTime)
					if (setlistDate > now) return false
				}
			}

			return true
		})
	}

	/**
	 * Get all drafts (both published and unpublished)
	 */
	const drafts = computed(() => getFilteredSetlists(true, undefined, undefined))

	/**
	 * Get future setlists (published only, including published drafts)
	 */
	const futureSetlists = computed(() => getFilteredSetlists(undefined, true, 'future'))

	/**
	 * Get unscheduled setlists (published only, including published drafts)
	 */
	const unscheduledSetlists = computed(() => getFilteredSetlists(undefined, true, 'unscheduled'))

	/**
	 * Get past setlists (published only, including published drafts)
	 */
	const pastSetlists = computed(() => getFilteredSetlists(undefined, true, 'past'))

	/**
	 * Get a specific setlist by ID
	 *
	 * @param id - The setlist ID
	 */
	function getSetlistById(id: number): Setlist | undefined {
		return setlists.value.find(s => s.id === id)
	}

	/**
	 * Create a new setlist via API and add it to the store
	 *
	 * @param title - The title of the setlist
	 * @param description - The description (optional)
	 * @param startDateTime - The start date/time in ISO format (optional)
	 * @param isDraft - Whether it's a draft
	 * @param isPublished - Whether it's published
	 * @return The created setlist
	 * @throws Error if API call fails
	 */
	async function createSetlist(
		title: string,
		description: string | null,
		startDateTime: string | null,
		isDraft: boolean,
		isPublished: boolean,
	): Promise<Setlist> {
		const response = await apiClients.default.setlistApiPostSetlist({
			title,
			description,
			startDateTime,
			isDraft,
			isPublished,
		})
		const created = response.data.ocs.data as Setlist
		setlists.value.unshift(created)
		return created
	}

	/**
	 * Clone a setlist via API and add it to the store
	 *
	 * @param id - The setlist ID to clone
	 * @param title - The title for the cloned setlist
	 * @return The cloned setlist
	 * @throws Error if API call fails
	 */
	async function cloneSetlist(id: number, title: string): Promise<Setlist> {
		const response = await apiClients.default.setlistApiPostCloneSetlist(id, { title })
		const created = response.data.ocs.data as Setlist
		setlists.value.unshift(created)
		return created
	}

	/**
	 * Delete a setlist via API and remove from store
	 *
	 * @param id - The setlist ID to remove
	 * @throws Error if API call fails
	 */
	async function deleteSetlist(id: number): Promise<void> {
		await apiClients.default.setlistApiDeleteSetlist(id)
		setlists.value = setlists.value.filter(s => s.id !== id)
	}

	/**
	 * Update a setlist via API and update in store
	 *
	 * @param id - The setlist ID
	 * @param updates - Partial setlist data to update
	 * @return The updated setlist
	 * @throws Error if API call fails
	 */
	async function updateSetlist(
		id: number,
		updates: Partial<Omit<Setlist, 'id'>>,
	): Promise<Setlist> {
		const response = await apiClients.default.setlistApiPatchSetlist(id, updates)
		const updated = response.data.ocs.data as Setlist
		const index = setlists.value.findIndex(s => s.id === id)
		if (index !== -1) {
			setlists.value[index] = updated
		}
		return updated
	}

	return {
		setlists,
		isLoaded,
		isLoading,
		drafts,
		futureSetlists,
		unscheduledSetlists,
		pastSetlists,
		initialize,
		getSetlistById,
		createSetlist,
		cloneSetlist,
		deleteSetlist,
		updateSetlist,
	}
})
