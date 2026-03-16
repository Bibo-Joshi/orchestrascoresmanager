<template>
	<Layout :title="setlist?.title">
		<template #header-actions>
			<AddSetlistEntryButton
				v-if="setlist && editable"
				:setlist="setlist"
				:editable="editable" />
			<ExportPdfButton
				v-if="setlist"
				:setlist="setlist"
				:entries="setlistEntries"
				:fcv-scores-map="fcvScoresMap"
				:fcv-score-book-indices-map="fcvScoreBookIndicesMap"
				:folder-collection="folderCollection"
				:get-columns="getPdfColumns" />
			<NcButton
				v-if="setlist && editable"
				variant="primary"
				@click="showCloneDialog = true">
				<template #icon>
					<CloneIcon :size="20" />
				</template>
				{{ t('Clone') }}
			</NcButton>
			<NcButton
				variant="primary"
				@click="handleDetailsButtonClick()">
				<template #icon>
					<InfoIcon :size="20" />
				</template>
				{{ t('Details') }}
			</NcButton>
		</template>

		<template #content>
			<ContentStateWrapper
				:loading="loading"
				:error="loadError || !setlist"
				:is-empty="setlistEntries.length === 0"
				:error-text="t('Failed to load setlist')">
				<template #empty-icon>
					<SetlistIcon :size="64" />
				</template>
				<SetlistEntriesTable
					v-if="setlist && setlistEntries.length > 0"
					ref="tableRef"
					:setlist="setlist"
					:entries="setlistEntries"
					:fcv-scores-map="fcvScoresMap"
					:fcv-score-book-indices-map="fcvScoreBookIndicesMap"
					:folder-collection="folderCollection"
					:editable="editable" />
			</ContentStateWrapper>
		</template>

		<template #sidebar>
			<SetlistPageSidebar :setlist="setlist" :editable="editable" />
		</template>
	</Layout>

	<!-- Clone dialog -->
	<CloneSetlistDialog
		v-if="setlist && showCloneDialog"
		:is-open="showCloneDialog"
		:setlist-id="setlist.id"
		@update:is-open="showCloneDialog = $event" />
</template>

<script setup lang="ts">
import { t } from '@/utils/l10n'
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { loadState } from '@nextcloud/initial-state'
import Layout from '@/components/Layout.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import SetlistPageSidebar from './components/SetlistPageSidebar.vue'
import SetlistEntriesTable from './components/SetlistEntriesTable.vue'
import AddSetlistEntryButton from './components/AddSetlistEntryButton.vue'
import ExportPdfButton from './components/ExportPdfButton.vue'
import CloneSetlistDialog from '@/components/CloneSetlistDialog.vue'
import { SetlistIcon, InfoIcon, CloneIcon } from '@/icons/vue-material'
import { useSetlistsStore } from '@/stores/setlistsStore'
import { useSetlistSidebarStore } from '@/stores/setlistSidebarStore'
import { useSetlistEntriesStore } from '@/stores/setlistEntriesStore'
import { useScoresStore } from '@/stores/scoresStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { apiClients } from '@/api/client'
import type { Setlist, Score, ScoreIndexed, FolderCollection, ScoreBookIndexed } from '@/api/generated/openapi/data-contracts'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import type { PdfColumnConfig } from '@/utils/pdf-exporter'

const route = useRoute()
const setlistsStore = useSetlistsStore()
const setlistSidebarStore = useSetlistSidebarStore()
const setlistEntriesStore = useSetlistEntriesStore()
const scoreSidebarStore = useScoreSidebarStore()
const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()

const loading = ref(false)
const loadError = ref(false)
const editable = ref<boolean>(!!loadState('orchestrascoresmanager', 'editable'))
const showCloneDialog = ref(false)
const fcvScoresMap = ref<Map<number, number>>(new Map())
const fcvScoreBookIndicesMap = ref<Map<number, number>>(new Map())
const folderCollection = ref<FolderCollection | null>(null)

/** Ref to the SetlistEntriesTable so ExportPdfButton can read the current column order */
const tableRef = ref<{ getPdfColumns: () => PdfColumnConfig[] } | null>(null)

/**
 * Return the currently displayed PDF columns from the table, used by ExportPdfButton.
 */
function getPdfColumns(): PdfColumnConfig[] {
	return tableRef.value?.getPdfColumns() ?? []
}

/**
 * Get setlist from store by route parameter
 */
const setlist = computed<Setlist | undefined>(() => {
	const id = parseInt(route.params.id as string, 10)
	if (isNaN(id)) {
		return undefined
	}
	return setlistsStore.getSetlistById(id)
})

/**
 * Get setlist entries with scores
 */
const setlistEntries = computed(() => {
	const id = parseInt(route.params.id as string, 10)
	if (isNaN(id)) {
		return []
	}
	return setlistEntriesStore.getEntriesBySetlistId(id)
})

function handleDetailsButtonClick(): void {
	if (scoreSidebarStore.isOpen) {
		scoreSidebarStore.closeSidebar()
		setlistSidebarStore.openSidebar()
		return
	}
	setlistSidebarStore.toggleSidebar()
}

const loadSetlistEntries = async (): Promise<void> => {
	loading.value = true
	loadError.value = false
	try {
		await setlistsStore.initialize()
		await scoresStore.initialize()
		await scoreBooksStore.initialize()

		const id = parseInt(route.params.id as string, 10)
		if (!isNaN(id)) {
			await setlistEntriesStore.loadEntries(id)

			// Load FCV scores if setlist has a folder collection version
			if (setlist.value?.folderCollectionVersionId) {
				try {
					// First, get the folder collection version to find the folder collection ID
					const versionResponse = await apiClients.default.folderCollectionVersionApiGetFolderCollectionVersion(
						setlist.value.folderCollectionVersionId,
					)
					const version = versionResponse.data.ocs.data

					// Load the folder collection to get its type (alphabetical vs indexed)
					const fcResponse = await apiClients.default.folderCollectionApiGetFolderCollection(
						version.folderCollectionId,
					)
					folderCollection.value = fcResponse.data.ocs.data

					// Now load the scores for this folder collection version
					const scoresResponse = await apiClients.default.folderCollectionApiGetFolderCollectionScores(
						version.folderCollectionId,
						{ versionId: setlist.value.folderCollectionVersionId },
					)
					const fcvScores = scoresResponse.data.ocs.data as (Score | ScoreIndexed)[]

					// Load score books to get their indices
					const scoreBooksResponse = await apiClients.default.folderCollectionApiGetFolderCollectionScoreBooks(
						version.folderCollectionId,
						{ versionId: setlist.value.folderCollectionVersionId },
					)
					const fcvScoreBooks = scoreBooksResponse.data.ocs.data as ScoreBookIndexed[]

					// Build map of score book ID to FCV index
					const scoreBookIndicesMap = new Map<number, number>()
					for (const scoreBook of fcvScoreBooks) {
						if (scoreBook.index !== undefined && scoreBook.index !== null) {
							scoreBookIndicesMap.set(scoreBook.id, scoreBook.index)
						}
					}
					fcvScoreBookIndicesMap.value = scoreBookIndicesMap

					// Build map of score ID to FCV index
					// Only include direct scores (not those via score books)
					const newMap = new Map<number, number>()
					for (const score of fcvScores) {
						// Only add direct scores, not those via score books
						const indexedScore = score as ScoreIndexed
						if (!indexedScore.viaScoreBook && indexedScore.index !== undefined && indexedScore.index !== null) {
							newMap.set(score.id, indexedScore.index)
						}
					}
					fcvScoresMap.value = newMap
				} catch (error) {
					console.error('Failed to load FCV scores:', error)
					fcvScoresMap.value = new Map()
					fcvScoreBookIndicesMap.value = new Map()
					folderCollection.value = null
				}
			} else {
				fcvScoresMap.value = new Map()
				fcvScoreBookIndicesMap.value = new Map()
				folderCollection.value = null
			}
		}
	} catch {
		loadError.value = true
	} finally {
		loading.value = false
	}
}

// Initialize stores and load setlist entries when component is mounted
onMounted(async () => {
	await loadSetlistEntries()
})

// Reload setlist entries when route parameter changes
watch(
	() => route.params.id,
	async () => {
		await loadSetlistEntries()
	},
)
</script>
