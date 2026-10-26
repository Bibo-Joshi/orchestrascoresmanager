import type { Setlist } from '@/api/generated/openapi/data-contracts'

import { ref } from 'vue'
import { apiClients } from '@/api/client'

/**
 * Composable for loading the setlists a score appears in, page by page.
 */
export function useScoreSetlists() {
	const setlists = ref<Setlist[]>([])
	const total = ref(0)
	const nextCursor = ref<string | null>(null)
	const loading = ref(false)
	const loadingMore = ref(false)
	const loadError = ref(false)

	// Guards against responses of an outdated score overwriting newer state
	let requestToken = 0

	/**
	 * Load the first page of setlists for a score, discarding previously loaded data.
	 *
	 * @param scoreId - ID of the score
	 */
	async function load(scoreId: number) {
		const token = ++requestToken
		setlists.value = []
		total.value = 0
		nextCursor.value = null
		loadError.value = false
		loadingMore.value = false
		loading.value = true

		try {
			const response = await apiClients.default.scoreApiGetScoreSetlists(scoreId)
			if (token !== requestToken) {
				return
			}
			const data = response.data.ocs.data
			setlists.value = data.setlists
			total.value = data.pagination.total
			nextCursor.value = data.pagination.nextCursor
		} catch (error) {
			if (token !== requestToken) {
				return
			}
			console.error('Failed to load setlists for score', error)
			loadError.value = true
		} finally {
			if (token === requestToken) {
				loading.value = false
			}
		}
	}

	/**
	 * Load the next page of setlists for a score and append it to the list.
	 *
	 * @param scoreId - ID of the score
	 */
	async function loadMore(scoreId: number) {
		const cursor = nextCursor.value
		if (cursor === null || loadingMore.value || loading.value) {
			return
		}
		const token = requestToken
		loadingMore.value = true

		try {
			const response = await apiClients.default.scoreApiGetScoreSetlists(scoreId, { cursor })
			if (token !== requestToken) {
				return
			}
			const data = response.data.ocs.data
			setlists.value = [...setlists.value, ...data.setlists]
			total.value = data.pagination.total
			nextCursor.value = data.pagination.nextCursor
		} catch (error) {
			if (token !== requestToken) {
				return
			}
			console.error('Failed to load more setlists for score', error)
			loadError.value = true
		} finally {
			if (token === requestToken) {
				loadingMore.value = false
			}
		}
	}

	return {
		setlists,
		total,
		nextCursor,
		loading,
		loadingMore,
		loadError,
		load,
		loadMore,
	}
}
