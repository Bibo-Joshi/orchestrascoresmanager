import { ref, computed } from 'vue'
import { apiClients } from '@/api/client'
import type {
	FolderCollectionScore,
	FolderCollectionScoreBook,
	FolderCollection,
	FolderCollectionVersion,
} from '@/api/generated/openapi/data-contracts'

export interface VersionEntry {
	version: FolderCollectionVersion
	index: number | null
}

export interface FolderCollectionGroup {
	folderCollection: FolderCollection
	versions: VersionEntry[]
	hasActiveVersion: boolean
	activeIndex: number | null
}

/**
 * Composable for loading and organizing folder collection version history
 * for scores or score books.
 *
 * @param type - 'score' or 'scorebook'
 */
export function useVersionHistory(type: 'score' | 'scorebook') {
	const rawData = ref<(FolderCollectionScore | FolderCollectionScoreBook)[]>([])
	const loading = ref(false)
	const loadError = ref(false)
	const expandedGroups = ref<Set<number>>(new Set())

	/**
	 * Group folder collections and organize versions
	 */
	const groupedFolderCollections = computed<FolderCollectionGroup[]>(() => {
		const groups = new Map<number, FolderCollectionGroup>()

		for (const item of rawData.value) {
			const fcId = item.folderCollection.id
			if (!groups.has(fcId)) {
				groups.set(fcId, {
					folderCollection: item.folderCollection,
					versions: [],
					hasActiveVersion: false,
					activeIndex: null,
				})
			}

			const group = groups.get(fcId)!
			const isActive = item.version.validTo === null

			group.versions.push({
				version: item.version,
				index: item.index,
			})

			if (isActive) {
				group.hasActiveVersion = true
				group.activeIndex = item.index
			}
		}

		// Sort versions within each group (newest first)
		for (const group of groups.values()) {
			group.versions.sort((a, b) => b.version.validFrom.localeCompare(a.version.validFrom))
		}

		// Convert to array and sort groups by folder collection title
		return Array.from(groups.values()).sort((a, b) =>
			a.folderCollection.title.localeCompare(b.folderCollection.title),
		)
	})

	/**
	 * Load folder collection data for a score or score book
	 *
	 * @param id - Score ID or Score Book ID
	 */
	async function load(id: number) {
		if (!id) return

		loading.value = true
		loadError.value = false

		try {
			if (type === 'score') {
				const response = await apiClients.default.scoreApiGetScoreFolderCollections(id)
				rawData.value = response.data.ocs.data || []
			} else {
				const response = await apiClients.default.scoreBookApiGetScoreBookFolderCollections(id)
				rawData.value = response.data.ocs.data || []
			}
		} catch (error) {
			console.error(`Failed to load folder collections for ${type}`, error)
			rawData.value = []
			loadError.value = true
		} finally {
			loading.value = false
		}
	}

	/**
	 * Toggle group expansion
	 *
	 * @param fcId - Folder collection ID
	 */
	function toggleGroup(fcId: number) {
		if (expandedGroups.value.has(fcId)) {
			expandedGroups.value.delete(fcId)
		} else {
			expandedGroups.value.add(fcId)
		}
	}

	/**
	 * Check if a group is expanded
	 *
	 * @param fcId - Folder collection ID
	 */
	function isExpanded(fcId: number): boolean {
		return expandedGroups.value.has(fcId)
	}

	return {
		loading,
		loadError,
		groupedFolderCollections,
		load,
		toggleGroup,
		isExpanded,
	}
}
