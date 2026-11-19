<template>
	<NcActions
		:menuName="buttonText(t('Export'))"
		:size="buttonSize"
		:primary="true"
		variant="primary"
		:forceName="true">
		<template #icon>
			<DownloadIcon :size="20" />
		</template>
		<NcActionButton
			:closeAfterClick="true"
			:disabled="loadingTemplates || pdfExporting"
			@click="openDialog">
			<template #icon>
				<PDFIcon :size="20" />
			</template>
			{{ t('PDF Export') }}
		</NcActionButton>
		<NcActionButton
			v-if="editable"
			:closeAfterClick="false"
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
		<div v-if="availablePdfTemplates.length" class="pdf-template-select">
			<label for="pdf-template-select"><strong>{{ t('PDF template') }}</strong></label>
			<select id="pdf-template-select" v-model="selectedPdfTemplateId">
				<option :value="null">
					{{ t('Standard template (built-in)') }}
				</option>
				<option v-for="template in availablePdfTemplates" :key="template.id" :value="template.id">
					{{ template.name }}
				</option>
			</select>
		</div>
		<p>{{ t('Select and reorder the columns to include in the PDF export.') }}</p>
		<draggable
			:list="dialogColumns"
			class="column-list"
			itemKey="id"
			tag="ul"
			handle=".drag-handle"
			@change="markColumnsCustomized">
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
						:aria-label="column.label"
						@update:modelValue="markColumnsCustomized" />
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
import type { FolderCollection, Setlist, SetlistEntry } from '@/api/generated/openapi/data-contracts'
import type { PdfColumnConfig, PdfColumnId } from '@/utils/pdf-exporter'
import type { PdfTemplate } from '@/utils/pdfTemplatesApi'

import { showWarning } from '@nextcloud/dialogs'
import { computed, ref } from 'vue'
import { VueDraggableNext as draggable } from 'vue-draggable-next'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import { apiClients } from '@/api/client'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { CancelIcon, DownloadIcon, DragVerticalIcon, ExcelIcon, LoadingIcon, PDFIcon } from '@/icons/vue-material'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useScoresStore } from '@/stores/scoresStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'
import { exportSetlistToPdf } from '@/utils/pdf-exporter'
import { fetchPdfTemplates } from '@/utils/pdfTemplatesApi'
import { exportSetlistToGemaXlsx } from '@/utils/setlist-xlsx-exporter'

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

interface DialogColumn extends PdfColumnConfig {
	enabled: boolean
}

const selectedPdfTemplateId = ref<string | null>(null)
const availablePdfTemplates = ref<PdfTemplate[]>([])
const loadingTemplates = ref(false)
const pdfExporting = ref(false)
const dialogOpen = ref(false)
const dialogColumns = ref<DialogColumn[]>([])
const hasUserCustomizedColumns = ref(false)
const gemaExporting = ref(false)

/**
 * indicator if any of the pieces in the setlist has a comment
 */
const hasAnyComment = computed<boolean>(() => {
	return props.entries.some((entry) => entry.comment && entry.comment.trim() !== '')
})

/** Column IDs that are enabled by default in the export dialog */
const DEFAULT_ENABLED_IDS = computed<ReadonlySet<PdfColumnId>>(() => {
	const enabledIds = new Set<PdfColumnId>([
		'startTime',
		'duration',
		'fcvIndex',
		'title',
	])
	if (hasAnyComment.value) {
		enabledIds.add('comment')
	}
	return enabledIds
})

/**
 * Open the PDF export configuration dialog, populating it with the current
 * column order from the table.
 */
async function openDialog(): Promise<void> {
	loadingTemplates.value = true
	const setlistId = props.setlist.id
	availablePdfTemplates.value = []
	await tryShowError(async () => {
		availablePdfTemplates.value = await fetchPdfTemplates()
	}, t('Failed to load PDF templates'))
	loadingTemplates.value = false
	if (setlistId !== props.setlist.id) {
		return
	}
	selectedPdfTemplateId.value = availablePdfTemplates.value.find(({ id }) => id === props.setlist.defaultTemplateId)?.id ?? null

	const currentColumns = props.getColumns()
		.filter((col) => col.label.trim() !== '')

	if (!hasUserCustomizedColumns.value) {
		dialogColumns.value = currentColumns.map((col) => ({
			...col,
			enabled: DEFAULT_ENABLED_IDS.value.has(col.id),
		}))
	}

	dialogOpen.value = true
}

/**
 * Mark export columns as user-customized so defaults stop auto-refreshing.
 */
function markColumnsCustomized(): void {
	hasUserCustomizedColumns.value = true
}

/**
 * Export the PDF using the currently selected columns, then close the dialog.
 */
async function onPdfExport(): Promise<void> {
	dialogOpen.value = false
	pdfExporting.value = true
	const templateId = selectedPdfTemplateId.value
	const { setlist, entries, fcvScoresMap, fcvScoreBookIndicesMap, folderCollection } = props
	const selectedColumns = dialogColumns.value
		.filter((col) => col.enabled)
		.map(({ id, label }) => ({ id, label }))

	await tryShowError(
		async () => {
			const [response, templates] = await Promise.all([
				apiClients.default.scoreApiGetScores(),
				templateId === null ? [] : fetchPdfTemplates(),
			])
			const freshScores = new Map(response.data.ocs.data.map((score) => [score.id, score]))
			const data = {
				setlist,
				entries,
				getScoreById: (id: number) => freshScores.get(id),
				getScoreBookById: (id: number) => scoreBooksStore.getScoreBookById(id),
				fcvScoresMap,
				fcvScoreBookIndicesMap,
				folderCollection,
				columnConfigs: selectedColumns,
			}
			const template = templates.find(({ id }) => id === templateId)
			if (template) {
				try {
					await exportSetlistToPdf(data, template)
					return
				} catch (error) {
					console.error('Failed to render PDF template', template.id, error)
					showWarning(t('PDF template “{name}” could not be applied. Using the built-in standard template.', { name: template.name }))
				}
			}
			await exportSetlistToPdf(data)
		},
		t('Export failed: '),
	)
	pdfExporting.value = false
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
.pdf-template-select {
	display: flex;
	flex-direction: column;
	gap: 0.5ex;
	margin-bottom: 1.5ex;
}

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
