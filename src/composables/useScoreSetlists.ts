import type { Setlist } from '@/api/generated/openapi/data-contracts'

import { showError } from '@nextcloud/dialogs'
import { ref } from 'vue'
import { apiClients } from '@/api/client'
import { t } from '@/utils/l10n'

/**
 * Composable for loading the setlists a score appears in, page by page.
 */
export function useScoreSetlists() {
	const setlists = ref<Setlist[]>([])
	const total = ref(0)
	const nextCursor = ref<string | null>(null)
	const isLoading = ref(false)
	const isLoaded = ref(false)
	const isLoadingMore = ref(false)
	const isLoadedMore = ref(false)

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
		isLoaded.value = false
		isLoadedMore.value = false
		isLoadingMore.value = false
		isLoading.value = true

		try {
			const response = await apiClients.default.scoreApiGetScoreSetlists(scoreId)
			if (token !== requestToken) {
				return
			}
			const data = response.data.ocs.data
			setlists.value = data.setlists
			total.value = data.pagination.total
			nextCursor.value = data.pagination.nextCursor
			isLoaded.value = true
		} catch (error) {
			if (token !== requestToken) {
				return
			}
			console.error('Failed to load setlists for score', error)
		} finally {
			if (token === requestToken) {
				isLoading.value = false
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
		if (cursor === null || isLoadingMore.value || isLoading.value) {
			return
		}
		const token = requestToken
		isLoadingMore.value = true
		isLoadedMore.value = false

		try {
			const response = await apiClients.default.scoreApiGetScoreSetlists(scoreId, { cursor })
			if (token !== requestToken) {
				return
			}
			const data = response.data.ocs.data
			setlists.value = [...setlists.value, ...data.setlists]
			total.value = data.pagination.total
			nextCursor.value = data.pagination.nextCursor
			isLoadedMore.value = true
		} catch (error) {
			if (token !== requestToken) {
				return
			}
			console.error('Failed to load more setlists for score', error)
			showError(t('Failed to load more setlists'))
		} finally {
			if (token === requestToken) {
				isLoadingMore.value = false
			}
		}
	}

	return {
		setlists,
		total,
		nextCursor,
		isLoading,
		isLoaded,
		isLoadingMore,
		isLoadedMore,
		load,
		loadMore,
	}
}
