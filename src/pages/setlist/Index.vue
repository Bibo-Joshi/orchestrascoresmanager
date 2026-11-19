<template>
	<Layout :title="setlist?.title">
		<template #headerActions>
			<HeaderActions :inline="actionsInline">
				<NcActionButton
					closeAfterClick
					@click="handleDetailsButtonClick()">
					<template #icon>
						<InfoIcon :size="20" />
					</template>
					{{ t('Details') }}
				</NcActionButton>
				<NcActionButton
					v-if="setlist"
					closeAfterClick
					@click="showShareDialog = true">
					<template #icon>
						<ShareIcon :size="20" />
					</template>
					{{ t('Share') }}
				</NcActionButton>
				<NcActionButton
					v-if="setlist && editable"
					closeAfterClick
					@click="showAddEntryDialog = true">
					<template #icon>
						<AddIcon :size="20" />
					</template>
					{{ t('Add Entry') }}
				</NcActionButton>
				<NcActionButton
					v-if="setlist && editable"
					closeAfterClick
					@click="showCloneDialog = true">
					<template #icon>
						<CloneIcon :size="20" />
					</template>
					{{ t('Clone') }}
				</NcActionButton>
				<NcActionSeparator v-if="showExportSeparator" />
				<NcActionButton
					v-if="setlist"
					closeAfterClick
					@click="showPdfExportDialog = true">
					<template #icon>
						<PDFIcon :size="20" />
					</template>
					{{ t('PDF Export') }}
				</NcActionButton>
				<NcActionButton
					v-if="setlist && editable"
					:disabled="gemaExporting"
					@click="exportGema">
					<template #icon>
						<LoadingIcon v-if="gemaExporting" :size="20" class="spin" />
						<ExcelIcon v-else :size="20" />
					</template>
					{{ t('GEMA Report') }}
				</NcActionButton>
				<template v-if="setlistEntries.length > 0">
					<NcActionSeparator v-if="showViewModeSeparator" />
					<NcActionCaption :name="t('View Mode')" />
					<NcActionRadio
						v-model="viewMode"
						value="full"
						name="view-mode">
						{{ t('Full View') }}
					</NcActionRadio>
					<NcActionRadio
						v-model="viewMode"
						value="compact"
						name="view-mode">
						{{ t('Compact View') }}
					</NcActionRadio>
				</template>
			</HeaderActions>

			<template v-if="setlist">
				<AddSetlistEntryDialog
					v-if="editable"
					v-model:open="showAddEntryDialog"
					:setlist="setlist" />
				<PdfExportDialog
					v-model:open="showPdfExportDialog"
					:setlist="setlist"
					:entries="setlistEntries"
					:fcvScoresMap="fcvScoresMap"
					:fcvScoreBookIndicesMap="fcvScoreBookIndicesMap"
					:folderCollection="folderCollection"
					:getColumns="getPdfColumns" />
				<SetlistShareDialog
					v-model:open="showShareDialog"
					:setlist="setlist"
					:entries="setlistEntries"
					:fcvScoresMap="fcvScoresMap"
					:fcvScoreBookIndicesMap="fcvScoreBookIndicesMap"
					:folderCollection="folderCollection" />
			</template>
		</template>

		<template #content>
			<ContentStateWrapper
				:loading="loading"
				:error="loadError || !setlist"
				:isEmpty="setlistEntries.length === 0"
				:errorText="t('Failed to load setlist')">
				<template #emptyIcon>
					<SetlistIcon :size="64" />
				</template>
				<SetlistEntriesTable
					v-if="setlist && setlistEntries.length > 0"
					ref="tableRef"
					:viewMode="viewMode"
					:setlist="setlist"
					:entries="setlistEntries"
					:fcvScoresMap="fcvScoresMap"
					:fcvScoreBookIndicesMap="fcvScoreBookIndicesMap"
					:folderCollection="folderCollection"
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
		:isOpen="showCloneDialog"
		:setlistId="setlist.id"
		@update:isOpen="showCloneDialog = $event" />
</template>

<script setup lang="ts">
import type { FolderCollection, Score, ScoreBookIndexed, ScoreIndexed, Setlist } from '@/api/generated/openapi/data-contracts'
import type { PdfColumnConfig } from '@/utils/pdf-exporter'

import { loadState } from '@nextcloud/initial-state'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCaption from '@nextcloud/vue/components/NcActionCaption'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import AddSetlistEntryDialog from './components/AddSetlistEntryDialog.vue'
import PdfExportDialog from './components/PdfExportDialog.vue'
import SetlistEntriesTable from './components/SetlistEntriesTable.vue'
import SetlistPageSidebar from './components/SetlistPageSidebar.vue'
import SetlistShareDialog from './components/SetlistShareDialog.vue'
import CloneSetlistDialog from '@/components/CloneSetlistDialog.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import HeaderActions from '@/components/HeaderActions.vue'
import Layout from '@/components/Layout.vue'
import { useGemaExport } from './composables/useGemaExport.ts'
import { apiClients } from '@/api/client'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { AddIcon, CloneIcon, ExcelIcon, InfoIcon, LoadingIcon, PDFIcon, SetlistIcon, ShareIcon } from '@/icons/vue-material'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import { useScoresStore } from '@/stores/scoresStore'
import { useSetlistEntriesStore } from '@/stores/setlistEntriesStore'
import { useSetlistSidebarStore } from '@/stores/setlistSidebarStore'
import { useSetlistsStore } from '@/stores/setlistsStore'
import { t } from '@/utils/l10n'

const route = useRoute()
const setlistsStore = useSetlistsStore()
const setlistSidebarStore = useSetlistSidebarStore()
const setlistEntriesStore = useSetlistEntriesStore()
const scoreSidebarStore = useScoreSidebarStore()
const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const { isDesktop } = useBreakpoints()

const loading = ref(false)
const loadError = ref(false)
const editable = ref<boolean>(!!loadState('orchestrascoresmanager', 'editable'))
const viewMode = ref<'full' | 'compact'>(isDesktop.value ? 'full' : 'compact')
const showCloneDialog = ref(false)
const showAddEntryDialog = ref(false)
const showPdfExportDialog = ref(false)
const showShareDialog = ref(false)
const fcvScoresMap = ref<Map<number, number>>(new Map())
const fcvScoreBookIndicesMap = ref<Map<number, number>>(new Map())
const folderCollection = ref<FolderCollection | null>(null)

/** Ref to the SetlistEntriesTable so PdfExportDialog can read the current column order */
const tableRef = ref<{ getPdfColumns: () => PdfColumnConfig[] } | null>(null)

/**
 * Return the currently displayed PDF columns from the table, used by PdfExportDialog.
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

/**
 * Whether separators between the groups of header actions are needed. Inline actions
 * (taken from the buttons in order) are not part of the menu, so a separator is only
 * shown if the menu has entries on both of its sides.
 */
const actionsInline = computed(() => editable.value ? 3 : 2)
const headerGroupSizes = computed(() => {
	const hasSetlist = !!setlist.value
	const manage = hasSetlist && editable.value ? 4 : 2
	const exportSetlist = hasSetlist ? (editable.value ? 2 : 1) : 0
	const viewMode = setlistEntries.value.length > 0 ? 1 : 0
	return { manage, exportSetlist, viewMode }
})
const showExportSeparator = computed(() => {
	const { manage, exportSetlist } = headerGroupSizes.value
	return manage - actionsInline.value > 0 && exportSetlist - Math.max(0, actionsInline.value - manage) > 0
})
const showViewModeSeparator = computed(() => {
	const { manage, exportSetlist } = headerGroupSizes.value
	return manage + exportSetlist - actionsInline.value > 0
})

const { gemaExporting, exportGema } = useGemaExport(() => setlist.value, () => setlistEntries.value)

function handleDetailsButtonClick(): void {
	if (scoreSidebarStore.isOpen) {
		scoreSidebarStore.closeSidebar()
		setlistSidebarStore.openSidebar()
		return
	}
	setlistSidebarStore.toggleSidebar()
}

async function loadSetlistEntries(): Promise<void> {
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
					const versionResponse = await apiClients.default.folderCollectionVersionApiGetFolderCollectionVersion(setlist.value.folderCollectionVersionId)
					const version = versionResponse.data.ocs.data

					// Load the folder collection to get its type (alphabetical vs indexed)
					const fcResponse = await apiClients.default.folderCollectionApiGetFolderCollection(version.folderCollectionId)
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

<style lang="scss" scoped>
.spin {
	animation: spin 1s linear infinite;
}

@keyframes spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}
</style>
