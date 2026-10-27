import type { Setlist } from '@/api/generated/openapi/data-contracts'

import { loadState } from '@nextcloud/initial-state'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { apiClients } from '@/api/client'

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
		if (isLoaded.value || isLoading.value) {
			return
		}

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
	 * Get a setlist's scheduled end time.
	 *
	 * @param setlist - The setlist to inspect
	 * @return The end time in milliseconds, or null when no start date is set
	 */
	function getEndTime(setlist: Setlist): number | null {
		if (!setlist.startDateTime) {
			return null
		}

		return Date.parse(setlist.startDateTime) + (setlist.duration ?? 0) * 1000
	}

	/**
	 * Check whether a setlist's scheduled duration has elapsed.
	 *
	 * @param setlist - The setlist to inspect
	 * @param now - The current time in milliseconds
	 * @return Whether the setlist has ended
	 */
	function isPast(setlist: Setlist, now: number): boolean {
		const endTime = getEndTime(setlist)
		return endTime !== null && endTime <= now
	}

	/**
	 * Sort setlists by descending start date, with undated setlists first.
	 *
	 * @param items - The setlists to sort
	 * @return A newly sorted setlist array
	 */
	function sortSetlists(items: Setlist[]): Setlist[] {
		return [...items].sort((a, b) => {
			if (!a.startDateTime && b.startDateTime) {
				return -1
			}
			if (a.startDateTime && !b.startDateTime) {
				return 1
			}

			const dateDifference = Date.parse(b.startDateTime ?? '') - Date.parse(a.startDateTime ?? '')
			return dateDifference || b.id - a.id
		})
	}

	/**
	 * Get drafts that have not yet passed
	 */
	const drafts = computed(() => {
		const now = Date.now()
		return sortSetlists(setlists.value.filter((setlist) => setlist.isDraft && !isPast(setlist, now)))
	})

	/**
	 * Get setlists that have not yet passed
	 */
	const futureSetlists = computed(() => {
		const now = Date.now()
		return sortSetlists(setlists.value.filter((setlist) => {
			const endTime = getEndTime(setlist)
			return endTime !== null && endTime > now
		}))
	})

	/**
	 * Get setlists without a start date
	 */
	const unscheduledSetlists = computed(() => {
		const unscheduled = setlists.value.filter((setlist) => !setlist.startDateTime)
		return sortSetlists(unscheduled)
	})

	/**
	 * Get setlists whose scheduled duration has elapsed
	 */
	const pastSetlists = computed(() => {
		const now = Date.now()
		return sortSetlists(setlists.value.filter((setlist) => isPast(setlist, now)))
	})
	const hasPastDrafts = computed(() => pastSetlists.value.some((setlist) => setlist.isDraft))

	/**
	 * Get a specific setlist by ID
	 *
	 * @param id - The setlist ID
	 */
	function getSetlistById(id: number): Setlist | undefined {
		return setlists.value.find((s) => s.id === id)
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
	 * @throws { Error } if API call fails
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
	 * @throws { Error } if API call fails
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
	 * @throws { Error } if API call fails
	 */
	async function deleteSetlist(id: number): Promise<void> {
		await apiClients.default.setlistApiDeleteSetlist(id)
		setlists.value = setlists.value.filter((s) => s.id !== id)
	}

	/**
	 * Update a setlist via API and update in store
	 *
	 * @param id - The setlist ID
	 * @param updates - Partial setlist data to update
	 * @return The updated setlist
	 * @throws { Error } if API call fails
	 */
	async function updateSetlist(
		id: number,
		updates: Partial<Omit<Setlist, 'id'>>,
	): Promise<Setlist> {
		const response = await apiClients.default.setlistApiPatchSetlist(id, updates)
		const updated = response.data.ocs.data as Setlist
		const index = setlists.value.findIndex((s) => s.id === id)
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
		hasPastDrafts,
		initialize,
		getSetlistById,
		createSetlist,
		cloneSetlist,
		deleteSetlist,
		updateSetlist,
	}
})
