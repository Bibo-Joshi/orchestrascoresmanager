<template>
	<NcActions
		:menu-name="buttonText(t('Export'))"
		:size="buttonSize"
		:primary="true"
		variant="primary"
		:force-name="true">
		<template #icon>
			<DownloadIcon :size="20" />
		</template>
		<NcActionButton
			:close-after-click="true"
			@click="openDialog">
			<template #icon>
				<PDFIcon :size="20" />
			</template>
			{{ editable ? t('PDF Export') : buttonText(t('PDF Export')) }}
		</NcActionButton>
		<NcActionButton
			v-if="editable"
			:close-after-click="false"
			:name="t('GEMA Report')"
			:disabled="gemaExporting"
			@click="onGemaExport">
			<template #icon>
				<LoadingIcon v-if="gemaExporting" :size="20" class="spin" />
				<ExcelIcon v-else :size="20" />
			</template>
		</NcActionButton>
	</NcActions>

	<NcDialog
		:name="t('Export PDF')"
		:open="dialogOpen"
		@update:open="dialogOpen = $event">
		<p>{{ t('Select and reorder the columns to include in the PDF export.') }}</p>
		<draggable
			:list="dialogColumns"
			class="column-list"
			item-key="id"
			tag="ul">
			<NcListItem
				v-for="column in dialogColumns"
				:key="column.id"
				:name="column.label">
				<template #icon>
					<DragVerticalIcon class="drag-handle" :size="20" />
				</template>
				<template #indicator>
					<NcCheckboxRadioSwitch
						v-model="column.enabled"
						type="checkbox"
						:aria-label="column.label" />
				</template>
			</NcListItem>
		</draggable>
		<template #actions>
			<NcButton @click="dialogOpen = false">
				<template #icon>
					<CancelIcon />
				</template>
				{{ t('Cancel') }}
			</NcButton>
			<NcButton variant="primary" @click="onPdfExport">
				<template #icon>
					<DownloadIcon />
				</template>
				{{ t('Export') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/utils/l10n'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { VueDraggableNext as draggable } from 'vue-draggable-next'
import { DownloadIcon, CancelIcon, DragVerticalIcon, ExcelIcon, LoadingIcon, PDFIcon } from '@/icons/vue-material'
import { tryShowError } from '@/utils/errorHandling'
import { exportSetlistToPdf } from '@/utils/pdf-exporter'
import type { PdfColumnConfig, PdfColumnId } from '@/utils/pdf-exporter'
import { exportSetlistToGemaXlsx } from '@/utils/setlist-xlsx-exporter'
import { useScoresStore } from '@/stores/scoresStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import type { Setlist, SetlistEntry, FolderCollection } from '@/api/generated/openapi/data-contracts'
import { useBreakpoints } from '@/composables/useBreakpoints'

interface Props {
	setlist: Setlist
	entries: SetlistEntry[]
	editable: boolean
	fcvScoresMap: Map<number, number>
	fcvScoreBookIndicesMap: Map<number, number>
	folderCollection: FolderCollection | null
	/** Returns the current columns from SetlistEntriesTable in display order */
	getColumns: () => PdfColumnConfig[]
}

const props = defineProps<Props>()

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const { buttonSize, buttonText } = useBreakpoints()

/** Column IDs that are enabled by default in the export dialog */
const DEFAULT_ENABLED_IDS: ReadonlySet<PdfColumnId> = new Set([
	'startTime',
	'duration',
	'fcvIndex',
	'title',
	'comment',
])

interface DialogColumn extends PdfColumnConfig {
	enabled: boolean
}

const dialogOpen = ref(false)
const dialogColumns = ref<DialogColumn[]>([])
const initialOpen = ref(true)
const gemaExporting = ref(false)

/**
 * Open the PDF export configuration dialog, populating it with the current
 * column order from the table.
 */
function openDialog(): void {
	const currentColumns = props.getColumns()

	if (initialOpen.value) {
		dialogColumns.value = currentColumns.map(col => ({
			...col,
			enabled: DEFAULT_ENABLED_IDS.has(col.id),
		}))
	}

	initialOpen.value = false
	dialogOpen.value = true
}

/**
 * Export the PDF using the currently selected columns, then close the dialog.
 */
async function onPdfExport(): Promise<void> {
	dialogOpen.value = false
	const selectedColumns = dialogColumns.value
		.filter(col => col.enabled)
		.map(({ id, label }) => ({ id, label }))

	await tryShowError(
		async () => {
			exportSetlistToPdf({
				setlist: props.setlist,
				entries: props.entries,
				getScoreById: (id) => scoresStore.getScoreById(id),
				getScoreBookById: (id) => scoreBooksStore.getScoreBookById(id),
				fcvScoresMap: props.fcvScoresMap,
				fcvScoreBookIndicesMap: props.fcvScoreBookIndicesMap,
				folderCollection: props.folderCollection,
				columnConfigs: selectedColumns,
			})
		},
		t('Export failed: '),
	)
}

/**
 * Export the GEMA report as an XLSX file.
 */
async function onGemaExport(): Promise<void> {
	gemaExporting.value = true
	await tryShowError(
		async () => {
			await exportSetlistToGemaXlsx({
				setlist: props.setlist,
				entries: props.entries,
				getScoreById: (id) => scoresStore.getScoreById(id),
			})
		},
		t('Export failed: '),
	)
	gemaExporting.value = false
}
</script>

<style lang="scss" scoped>
.column-list {
	padding: 1ex 0;
}

.spin {
	animation: spin 1s linear infinite;
}

@keyframes spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}
</style>
