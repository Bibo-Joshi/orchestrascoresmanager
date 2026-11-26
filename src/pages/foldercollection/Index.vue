<template>
	<Layout :title="folderCollection?.title">
		<template #content>
			<ContentStateWrapper
				:loading="loading"
				:error="loadError || !folderCollection"
				:isEmpty="scores.length === 0"
				:errorText="t('Failed to load folder collection')"
				:errorDescription="t('Please check if the folder collection exists and try again.')"
				:emptyText="t('No entries in this collection')"
				:emptyDescription="t('Add scores or score books to this folder collection to see them here.')">
				<template #emptyIcon>
					<ScoreIcon :size="64" />
				</template>
				<template #default>
					<!-- Collection Info Header -->
					<NcNoteCard v-if="folderCollection?.description" type="info">
						{{ folderCollection?.description }}
					</NcNoteCard>

					<!-- Inactive Version Warning -->
					<NcNoteCard v-if="!isSelectedVersionActive" type="warning">
						{{ t('You are viewing an inactive version. Scores and score books cannot be added or removed.') }}
					</NcNoteCard>

					<!-- Scores Table -->
					<ScoresTable
						v-if="folderCollection"
						ref="scoresTableRef"
						:editable="editable && isSelectedVersionActive"
						:isIndexedCollection="isIndexed"
						:folderCollectionScores="scores"
						:scoreBookIndexMap="scoreBookIndexMap"
						:searchText="searchText"
						:onDeleteScore="handleDeleteScore" />
				</template>
			</ContentStateWrapper>
		</template>

		<template #headerActions>
			<HeaderSearch v-model="searchText" />
			<HeaderActions :inline="editable ? 2 : 0">
				<NcActionButton
					v-if="editable && isSelectedVersionActive && folderCollection"
					closeAfterClick
					@click="showAddDialog = true">
					<template #icon>
						<AddIcon :size="20" />
					</template>
					{{ t('Add') }}
				</NcActionButton>
				<template v-if="versions.length > 0 || (editable && folderCollection)">
					<NcActionSeparator />
					<NcActionInput
						v-if="versions.length > 0"
						v-model="selectedVersionOption"
						type="multiselect"
						:label="t('Version')"
						:options="versionOptions"
						:clearable="false">
						<template #icon>
							<HistoryIcon :size="20" />
						</template>
						{{ t('Select version') }}
					</NcActionInput>
					<NcActionButton
						v-if="editable && folderCollection"
						closeAfterClick
						:disabled="!isSelectedVersionActive"
						:aria-label="t('Start new version')"
						@click="startNewVersion">
						<template #icon>
							<HistoryIcon :size="20" />
						</template>
						{{ t('New Version') }}
					</NcActionButton>
				</template>
				<NcActionSeparator />
				<NcActionButton
					closeAfterClick
					@click="exportCsv">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('Export CSV') }}
				</NcActionButton>
				<NcActionButton
					v-if="folderCollection && selectedVersion && scores.length > 0"
					closeAfterClick
					@click="exportToc">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('Export ToC & Index') }}
				</NcActionButton>
			</HeaderActions>

			<AddToCollectionDialog
				v-if="folderCollection"
				v-model:open="showAddDialog"
				:folderCollectionId="folderCollection.id"
				:isIndexed="isIndexed"
				:existingScoreIds="existingScoreIds"
				:existingScoreBookIds="existingScoreBookIds"
				:occupiedIndices="occupiedIndices" />
		</template>

		<!-- Sidebar removed - using score table sidebar instead -->
		<template #sidebar>
			<ScoreSidebar :editable="editable && isSelectedVersionActive" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import type { FolderCollectionVersion, Score, ScoreBook, ScoreBookIndexed, ScoreIndexed } from '@/api/generated/openapi/data-contracts'

import { loadState } from '@nextcloud/initial-state'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import AddToCollectionDialog from './components/AddToCollectionDialog.vue'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import HeaderActions from '@/components/HeaderActions.vue'
import HeaderSearch from '@/components/HeaderSearch.vue'
import Layout from '@/components/Layout.vue'
import ScoreSidebar from '@/components/ScoreSidebar.vue'
import ScoresTable from '@/components/ScoresTable.vue'
import { useStartNewVersion } from './composables/useStartNewVersion.ts'
import { useTocExport } from './composables/useTocExport.ts'
import { useCsvExport } from '@/composables/useCsvExport'
import { formatDateStr } from '@/composables/useDateFormatting'
import { AddIcon, DownloadIcon, HistoryIcon, ScoreIcon } from '@/icons/vue-material'
import { useFolderCollectionMembersStore } from '@/stores/folderCollectionMembersStore'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import { useFolderCollectionVersionsStore } from '@/stores/folderCollectionVersionsStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

interface CollectionEntry {
	key: string
	type: 'score' | 'scorebook'
	index?: number
	score?: Score | ScoreIndexed
	scoreBook?: ScoreBook | ScoreBookIndexed
	scores?: Score[]
}

interface VersionOption {
	label: string
	value: number
	isActive: boolean
}

const route = useRoute()
const router = useRouter()
const folderCollectionsStore = useFolderCollectionsStore()
const versionsStore = useFolderCollectionVersionsStore()
const membersStore = useFolderCollectionMembersStore()
const scoreBooksStore = useScoreBooksStore()

const loading = ref(false)
const loadError = ref(false)
const editable = ref<boolean>(!!loadState('orchestrascoresmanager', 'editable'))
const searchText = ref<string | undefined>(undefined)

// Ref to access ScoresTable and its tableRef
const scoresTableRef = ref<{ tableRef: { exportAsCsv?: (fileName?: string) => boolean } | null } | null>(null)

/**
 * Get folder collection from store by route parameter
 */
const folderCollection = computed(() => {
	const id = parseInt(route.params.id as string, 10)
	if (isNaN(id)) {
		return undefined
	}
	return folderCollectionsStore.getFolderCollectionById(id)
})

/**
 * Get versions for current folder collection
 */
const versions = computed(() => {
	if (!folderCollection.value) {
		return []
	}
	return versionsStore.getVersions(folderCollection.value.id)
})

/**
 * Get selected version ID from URL query or store
 */
const selectedVersionId = computed(() => {
	const queryVersionId = route.query.versionId
	if (queryVersionId) {
		const parsed = parseInt(queryVersionId as string, 10)
		// Only use if parsed value is a valid number
		if (!isNaN(parsed) && parsed > 0) {
			return parsed
		}
	}
	if (!folderCollection.value) {
		return null
	}
	return versionsStore.getSelectedVersionId(folderCollection.value.id)
})

/**
 * Get selected version entity
 */
const selectedVersion = computed(() => {
	if (!folderCollection.value || selectedVersionId.value === null) {
		return undefined
	}
	return versions.value.find((v) => v.id === selectedVersionId.value)
})

/**
 * Check if selected version is active
 */
const isSelectedVersionActive = computed(() => {
	return versionsStore.isActiveVersion(selectedVersion.value)
})

/**
 * Version selector options
 */
const versionOptions = computed<VersionOption[]>(() => {
	return versions.value.map((v) => ({
		label: formatVersionLabel(v),
		value: v.id,
		isActive: v.validTo === null,
	}))
})

/**
 * Selected version option for NcSelect
 */
const selectedVersionOption = computed({
	get: () => versionOptions.value.find((o) => o.value === selectedVersionId.value) || null,
	set: (option: VersionOption | null) => {
		if (option && folderCollection.value) {
			handleVersionChange(option)
		}
	},
})

/**
 * Format version label for display
 *
 * @param version - The version to format
 */
function formatVersionLabel(version: FolderCollectionVersion): string {
	const fromDate = formatDateStr(version.validFrom)
	if (version.validTo === null) {
		return `${fromDate} - ${t('Active')}`
	}
	return `${fromDate} - ${formatDateStr(version.validTo)}`
}

/**
 * Check if the collection is indexed
 */
const isIndexed = computed(() => {
	return folderCollection.value?.collectionType === 'indexed'
})

/**
 * Scores of the collection, sorted.
 * For indexed collections, sort by index. Lexicographical sorting on (<index of score in collection>, <index of score in book>|null)
 * For alphabetical collections, sort by lexicographical sorting (<scorebook title if viaScoreBook>|<score title>, <index of score in book>|null)
 */
const scores = computed<(Score | ScoreIndexed)[]>(() => {
	if (!folderCollection.value) {
		return []
	}
	const collectionIndex = (s: Score | ScoreIndexed): number | null => {
		return (s as ScoreIndexed).index ?? null
	}

	const indexInBook = (s: Score | ScoreIndexed): number | null => {
		if (s.viaScoreBook) {
			return (s as ScoreIndexed).scoreBook!.index ?? null
		}
		return null
	}

	const sorted = membersStore.getScores(folderCollection.value.id)
	if (isIndexed.value) {
		sorted.sort((a, b) => {
			const aCol = collectionIndex(a) ?? Number.MAX_SAFE_INTEGER
			const bCol = collectionIndex(b) ?? Number.MAX_SAFE_INTEGER
			if (aCol !== bCol) {
				return aCol - bCol
			}

			const aInner = indexInBook(a) ?? Number.MAX_SAFE_INTEGER
			const bInner = indexInBook(b) ?? Number.MAX_SAFE_INTEGER
			return aInner - bInner
		})
	} else {
		const sortTitle = (s: Score | ScoreIndexed): string => {
			return s.viaScoreBook && s.scoreBook ? (scoreBooksStore.getScoreBookById(s.scoreBook.id)?.title ?? s.title) : s.title
		}
		sorted.sort((a, b) => {
			const cmp = sortTitle(a).localeCompare(sortTitle(b))
			if (cmp !== 0) {
				return cmp
			}

			const aInner = indexInBook(a) ?? Number.MAX_SAFE_INTEGER
			const bInner = indexInBook(b) ?? Number.MAX_SAFE_INTEGER
			return aInner - bInner
		})
	}
	return sorted
})

/**
 * Score books of the collection
 */
const scoreBooks = computed<(ScoreBook | ScoreBookIndexed)[]>(() => {
	return folderCollection.value ? membersStore.getScoreBooks(folderCollection.value.id) : []
})

/**
 * Map of score book ID to its scores within the collection
 */
const scoreBookScoresMap = computed<Map<number, Score[]>>(() => {
	const map = new Map<number, Score[]>()
	for (const score of scores.value) {
		if (score.viaScoreBook && score.scoreBook?.id) {
			const scoresOfBook = map.get(score.scoreBook.id) ?? []
			scoresOfBook.push(score)
			map.set(score.scoreBook.id, scoresOfBook)
		}
	}
	return map
})

/**
 * Combined and sorted entries (scores + score books)
 */
const allEntries = computed<CollectionEntry[]>(() => {
	const entries: CollectionEntry[] = []

	// Add direct scores
	for (const score of scores.value) {
		const indexedScore = score as ScoreIndexed
		entries.push({
			key: `score-${score.id}`,
			type: 'score',
			index: indexedScore.index,
			score,
		})
	}

	// Add score books
	for (const scoreBook of scoreBooks.value) {
		const indexedBook = scoreBook as ScoreBookIndexed
		entries.push({
			key: `scorebook-${scoreBook.id}`,
			type: 'scorebook',
			index: indexedBook.index,
			scoreBook,
			scores: scoreBookScoresMap.value.get(scoreBook.id) || [],
		})
	}

	// Sort entries
	if (isIndexed.value) {
		// Sort by index for indexed collections
		entries.sort((a, b) => (a.index ?? 0) - (b.index ?? 0))
	} else {
		// Sort alphabetically for alphabetical collections
		entries.sort((a, b) => {
			const titleA = a.type === 'score' ? a.score!.title : a.scoreBook!.title
			const titleB = b.type === 'score' ? b.score!.title : b.scoreBook!.title
			return titleA.localeCompare(titleB)
		})
	}

	return entries
})

/**
 * Map of score book ID to its collection index
 */
const scoreBookIndexMap = computed<Map<number, number>>(() => {
	const map = new Map<number, number>()
	for (const book of scoreBooks.value as ScoreBookIndexed[]) {
		if (book.index !== undefined) {
			map.set(book.id, book.index)
		}
	}
	return map
})

/**
 * Handle version change from selector
 *
 * @param option - The selected version option
 */
function handleVersionChange(option: VersionOption) {
	if (!folderCollection.value) {
		return
	}
	versionsStore.setSelectedVersion(folderCollection.value.id, option.value)
	// Update URL query parameter
	router.replace({
		query: { ...route.query, versionId: String(option.value) },
	})
	// Reload data for new version
	loadData()
}

/**
 * Handle version created by the start new version action
 *
 * @param newVersion - The newly created version
 */
function handleVersionCreated(newVersion: FolderCollectionVersion) {
	// Update URL to new version
	router.replace({
		query: { ...route.query, versionId: String(newVersion.id) },
	})
	// Reload data
	loadData()
}

const showAddDialog = ref(false)
const { exportCsv } = useCsvExport(() => scoresTableRef.value?.tableRef ?? null)
const { exportToc } = useTocExport({
	folderCollection,
	version: selectedVersion,
	entries: allEntries,
})
const { startNewVersion } = useStartNewVersion(() => folderCollection.value?.id, handleVersionCreated)

/**
 * Load all data for the collection
 */
async function loadData() {
	if (!folderCollection.value) {
		loadError.value = true
		return
	}

	loading.value = true
	loadError.value = false

	try {
		// Load versions first if not already loaded
		if (versions.value.length === 0) {
			await versionsStore.loadVersions(folderCollection.value.id, folderCollection.value.activeVersionId)
		}

		await membersStore.loadMembers(folderCollection.value.id, selectedVersionId.value)
	} catch (error) {
		console.error('Failed to load folder collection data', error)
		loadError.value = false
		membersStore.clearMembers(folderCollection.value.id)
	} finally {
		loading.value = false
	}
}

// Load data when component is mounted
onMounted(() => {
	loadData()
})

// Reload data when route parameter changes
watch(() => route.params.id, () => {
	loadData()
})

// Reload when version query param changes
watch(() => route.query.versionId, () => {
	if (folderCollection.value) {
		const queryVersionId = route.query.versionId
		if (queryVersionId) {
			const parsedVersionId = parseInt(queryVersionId as string, 10)
			// Only set version if parsed value is a valid number
			if (!isNaN(parsedVersionId) && parsedVersionId > 0) {
				versionsStore.setSelectedVersion(folderCollection.value.id, parsedVersionId)
			}
		}
		loadData()
	}
})

/**
 * Set of existing score IDs in the collection (direct only)
 */
const existingScoreIds = computed<Set<number>>(() => {
	return new Set(scores.value.map((s) => s.id))
})

/**
 * Set of existing score book IDs in the collection
 */
const existingScoreBookIds = computed<Set<number>>(() => {
	return new Set(scoreBooks.value.map((sb) => sb.id))
})

/**
 * Set of occupied indices in an indexed collection
 */
const occupiedIndices = computed<Set<number>>(() => {
	if (!isIndexed.value) {
		return new Set()
	}
	const indices = new Set<number>()
	for (const score of scores.value as ScoreIndexed[]) {
		if (score.index !== undefined) {
			indices.add(score.index)
		}
	}
	for (const book of scoreBooks.value as ScoreBookIndexed[]) {
		if (book.index !== undefined) {
			indices.add(book.index)
		}
	}
	return indices
})

/**
 * Handle delete score - called from ScoresTable
 * @param scoreId - The ID of the score to delete
 * @param scoreBookId - The score book ID if the score is from a score book, null otherwise
 * @param viaScoreBook - Whether the score is part of a score book
 */
async function handleDeleteScore(scoreId: number, scoreBookId: number | null, viaScoreBook: boolean) {
	if (!folderCollection.value) {
		return
	}

	// If the score is from a scorebook, we need to remove the entire scorebook
	if (viaScoreBook) {
		const scoreBook = scoreBooks.value.find((sb) => sb.id === scoreBookId)
		if (scoreBook) {
			const bookScores = scoreBookScoresMap.value.get(scoreBookId) || []
			const result = await spawnDialog(
				ConfirmationDialog,
				{
					title: t('Remove score book from collection'),
					message: t('This score is part of score book "{title}". Removing it will remove the entire score book and all {count} of its scores from the collection. Do you want to continue?', {
						title: scoreBook.title,
						count: bookScores.length,
					}),
					countdown: null,
				},
			)

			if (result) {
				await deleteScoreBook(scoreBook)
			}
		}
	} else {
		// Direct score - just remove it
		const score = scores.value.find((s) => s.id === scoreId)
		if (score) {
			const result = await spawnDialog(
				ConfirmationDialog,
				{
					title: t('Remove score from collection'),
					message: t('Are you sure you want to remove this score from the collection?'),
					countdown: null,
				},
			)

			if (result) {
				await deleteScore(score)
			}
		}
	}
}

/**
 * Delete a score from the collection
 * @param score - The score to delete
 */
async function deleteScore(score: Score | ScoreIndexed) {
	if (!folderCollection.value) {
		return
	}

	const collectionId = folderCollection.value.id

	await tryShowError(
		async () => {
			await membersStore.removeScore(collectionId, score.id)
		},
		t('Failed to remove score from collection: '),
	)
}

/**
 * Delete a score book from the collection
 * @param scoreBook - The score book to delete
 */
async function deleteScoreBook(scoreBook: ScoreBook | ScoreBookIndexed) {
	if (!folderCollection.value) {
		return
	}

	const collectionId = folderCollection.value.id

	await tryShowError(
		async () => {
			await membersStore.removeScoreBook(collectionId, scoreBook.id)
		},
		t('Failed to remove score book from collection: '),
	)
}
</script>
