<template>
	<Layout :title="folderCollection?.title">
		<template #content>
			<ContentStateWrapper
				:loading="loading"
				:error="loadError || !folderCollection"
				:is-empty="scores.length === 0"
				:error-text="t('Failed to load folder collection')"
				:error-description="t('Please check if the folder collection exists and try again.')"
				:empty-text="t('No entries in this collection')"
				:empty-description="t('Add scores or score books to this folder collection to see them here.')">
				<template #empty-icon>
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
						:is-indexed-collection="isIndexed"
						:folder-collection-scores="scores"
						:score-book-index-map="scoreBookIndexMap"
						:on-delete-score="handleDeleteScore" />
				</template>
			</ContentStateWrapper>
		</template>

		<template #header-actions>
			<!-- Version Selector -->
			<NcSelect
				v-if="versions.length > 0"
				v-model="selectedVersionOption"
				:options="versionOptions"
				:placeholder="t('Select version')"
				:clearable="false"
				class="version-selector"
				@input="handleVersionChange" />

			<!-- Start New Version Button -->
			<StartNewVersionButton
				v-if="editable && folderCollection"
				:folder-collection-id="folderCollection.id"
				:disabled="!isSelectedVersionActive"
				@version-created="handleVersionCreated" />

			<AddToCollectionButton
				v-if="folderCollection"
				:editable="editable && isSelectedVersionActive"
				:folder-collection-id="folderCollection.id"
				:is-indexed="isIndexed"
				:existing-score-ids="existingScoreIds"
				:existing-score-book-ids="existingScoreBookIds"
				:occupied-indices="occupiedIndices"
				@score-added="handleScoreAdded"
				@scorebook-added="handleScoreBookAdded" />

			<!-- Export CSV Button -->
			<ExportCsvButton :table-ref="scoresTableRef?.tableRef ?? null" />

			<!-- Export ToC & Index Button -->
			<ExportTocButton
				v-if="folderCollection && selectedVersion && scores.length > 0"
				:folder-collection="folderCollection"
				:version="selectedVersion"
				:entries="allEntries" />
		</template>

		<!-- Sidebar removed - using score table sidebar instead -->
		<template #sidebar>
			<ScoreSidebar :editable="editable && isSelectedVersionActive" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { loadState } from '@nextcloud/initial-state'
import { tryShowError } from '@/utils/errorHandling'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import Layout from '@/components/Layout.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { ScoreIcon } from '@/icons/vue-material'
import ScoresTable from '@/components/ScoresTable.vue'
import ExportCsvButton from '@/components/ExportCsvButton.vue'
import ScoreSidebar from '@/components/ScoreSidebar.vue'
import AddToCollectionButton from './components/AddToCollectionButton.vue'
import StartNewVersionButton from './components/StartNewVersionButton.vue'
import ExportTocButton from './components/ExportTocButton.vue'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import { t } from '@/utils/l10n'
import { formatDateStr } from '@/composables/useDateFormatting'
import { apiClients } from '@/api/client'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import { useFolderCollectionVersionsStore } from '@/stores/folderCollectionVersionsStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import type { Score, ScoreIndexed, ScoreBook, ScoreBookIndexed, FolderCollectionVersion } from '@/api/generated/openapi/data-contracts'

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
const scoreBooksStore = useScoreBooksStore()

const loading = ref(false)
const loadError = ref(false)
const scores = ref<(Score | ScoreIndexed)[]>([])
const scoreBooks = ref<(ScoreBook | ScoreBookIndexed)[]>([])
const scoreBookScoresMap = ref<Map<number, Score[]>>(new Map())
const editable = ref<boolean>(!!loadState('orchestrascoresmanager', 'editable'))

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
	if (!folderCollection.value) return []
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
	if (!folderCollection.value) return null
	return versionsStore.getSelectedVersionId(folderCollection.value.id)
})

/**
 * Get selected version entity
 */
const selectedVersion = computed(() => {
	if (!folderCollection.value || selectedVersionId.value === null) return undefined
	return versions.value.find(v => v.id === selectedVersionId.value)
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
	return versions.value.map(v => ({
		label: formatVersionLabel(v),
		value: v.id,
		isActive: v.validTo === null,
	}))
})

/**
 * Selected version option for NcSelect
 */
const selectedVersionOption = computed({
	get: () => versionOptions.value.find(o => o.value === selectedVersionId.value) || null,
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
 * Sort sorces in place
 * For indexed collections, sort by index. Lexikographical sorting on (<index of score in collection>, <index of score in book>|null)
 * For alphabetical collections, sort by lexikographical sorting (<scrobook title if viaScoreBook>|<score title>, <index of score in book>|null)
 */
function sortScores(): void {
	if (!scores.value) return

	const collectionIndex = (s: Score | ScoreIndexed): number | null => {
		return (s as ScoreIndexed).index ?? null
	}

	const indexInBook = (s: Score | ScoreIndexed): number | null => {
		if (s.viaScoreBook) {
			return (s as ScoreIndexed).scoreBook!.index ?? null
		}
		return null
	}

	if (isIndexed.value) {
		scores.value.sort((a, b) => {
			const aCol = collectionIndex(a) ?? Number.MAX_SAFE_INTEGER
			const bCol = collectionIndex(b) ?? Number.MAX_SAFE_INTEGER
			if (aCol !== bCol) return aCol - bCol

			const aInner = indexInBook(a) ?? Number.MAX_SAFE_INTEGER
			const bInner = indexInBook(b) ?? Number.MAX_SAFE_INTEGER
			return aInner - bInner
		})
	} else {
		scores.value.sort((a, b) => {
			const titleA = a.viaScoreBook && a.scoreBook ? scoreBooksStore.getScoreBookById(a.scoreBook.id).title : a.title
			const titleB = b.viaScoreBook && b.scoreBook ? scoreBooksStore.getScoreBookById(b.scoreBook.id).title : b.title
			const cmp = titleA.localeCompare(titleB)
			if (cmp !== 0) return cmp

			const aInner = indexInBook(a) ?? Number.MAX_SAFE_INTEGER
			const bInner = indexInBook(b) ?? Number.MAX_SAFE_INTEGER
			return aInner - bInner
		})
	}
}

/**
 * Check if the collection is indexed
 */
const isIndexed = computed(() => {
	return folderCollection.value?.collectionType === 'indexed'
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
	if (!folderCollection.value) return
	versionsStore.setSelectedVersion(folderCollection.value.id, option.value)
	// Update URL query parameter
	router.replace({
		query: { ...route.query, versionId: String(option.value) },
	})
	// Reload data for new version
	loadData()
}

/**
 * Handle version created event from StartNewVersionButton
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

		// Determine which version to load data for
		const versionIdToLoad = selectedVersionId.value

		// Load all scores (direct members and those via score books)
		const scoresResponse = await apiClients.default.folderCollectionApiGetFolderCollectionScores(
			folderCollection.value.id,
			{ versionId: versionIdToLoad ?? undefined },
		)
		scores.value = (scoresResponse.data.ocs.data || []) as (Score | ScoreIndexed)[]
		sortScores()

		// Load score books
		const scoreBooksResponse = await apiClients.default.folderCollectionApiGetFolderCollectionScoreBooks(
			folderCollection.value.id,
			{ versionId: versionIdToLoad ?? undefined },
		)
		scoreBooks.value = (scoreBooksResponse.data.ocs.data || []) as (ScoreBook | ScoreBookIndexed)[]

		// Build scoreBookScoresMap from scores that have viaScoreBook flag
		const newMap = new Map<number, Score[]>()
		for (const score of scores.value) {
			if (score.viaScoreBook && score.scoreBook?.id) {
				const bookId = score.scoreBook.id
				if (!newMap.has(bookId)) {
					newMap.set(bookId, [])
				}
				newMap.get(bookId)!.push(score)
			}
		}
		scoreBookScoresMap.value = newMap
	} catch (error) {
		console.error('Failed to load folder collection data', error)
		loadError.value = false
		scores.value = []
		scoreBooks.value = []
		scoreBookScoresMap.value = new Map()
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
	return new Set(scores.value.map(s => s.id))
})

/**
 * Set of existing score book IDs in the collection
 */
const existingScoreBookIds = computed<Set<number>>(() => {
	return new Set(scoreBooks.value.map(sb => sb.id))
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
		if (score.index !== undefined) indices.add(score.index)
	}
	for (const book of scoreBooks.value as ScoreBookIndexed[]) {
		if (book.index !== undefined) indices.add(book.index)
	}
	return indices
})

/**
 * Handle score added event
 * @param newScore - The newly added score
 */
function handleScoreAdded(newScore: Score | ScoreIndexed) {
	scores.value = [...scores.value, newScore]
	sortScores()
	if (folderCollection.value) {
		folderCollectionsStore.incrementScoreCount(folderCollection.value.id)
	}
}

/**
 * Handle score book added event
 * @param newScoreBook - The newly added score book
 */
async function handleScoreBookAdded(newScoreBook: ScoreBook | ScoreBookIndexed) {
	scoreBooks.value = [...scoreBooks.value, newScoreBook]
	// Reload all scores to get the new scores from this book
	await loadData()
}

/**
 * Handle delete score - called from ScoresTable
 * @param scoreId - The ID of the score to delete
 * @param scoreBookId - The score book ID if the score is from a score book, null otherwise
 * @param viaScoreBook - Whether the score is part of a score book
 */
async function handleDeleteScore(scoreId: number, scoreBookId: number | null, viaScoreBook: boolean) {
	if (!folderCollection.value) return

	// If the score is from a scorebook, we need to remove the entire scorebook
	if (viaScoreBook) {
		const scoreBook = scoreBooks.value.find(sb => sb.id === scoreBookId)
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
		const score = scores.value.find(s => s.id === scoreId)
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
	if (!folderCollection.value) return

	const collectionId = folderCollection.value.id

	await tryShowError(
		async () => {
			await apiClients.default.folderCollectionApiDeleteFolderCollectionScore(
				collectionId,
				score.id,
			)
			scores.value = scores.value.filter((s) => s.id !== score.id)
			folderCollectionsStore.decrementScoreCount(collectionId)
		},
		t('Failed to remove score from collection: '),
	)
}

/**
 * Delete a score book from the collection
 * @param scoreBook - The score book to delete
 */
async function deleteScoreBook(scoreBook: ScoreBook | ScoreBookIndexed) {
	if (!folderCollection.value) return

	const collectionId = folderCollection.value.id
	const scoreCount = scoreBookScoresMap.value.get(scoreBook.id)?.length || 0

	await tryShowError(
		async () => {
			await apiClients.default.folderCollectionApiDeleteFolderCollectionScoreBook(
				collectionId,
				scoreBook.id,
			)
			scoreBooks.value = scoreBooks.value.filter((sb) => sb.id !== scoreBook.id)
			scores.value = scores.value.filter((s) => s.scoreBook?.id !== scoreBook.id)
			scoreBookScoresMap.value.delete(scoreBook.id)
			// Update score count
			Array.from({ length: scoreCount }).forEach(() => {
				folderCollectionsStore.decrementScoreCount(collectionId)
			})
		},
		t('Failed to remove score book from collection: '),
	)
}
</script>

<style lang="scss" scoped>
.version-selector {
	min-width: 200px;
}
</style>
