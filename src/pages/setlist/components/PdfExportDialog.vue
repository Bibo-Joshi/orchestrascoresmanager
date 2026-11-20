<template>
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
			<NcButton variant="primary" :disabled="loadingTemplates || pdfExporting" @click="onPdfExport">
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
import { computed, ref, watch } from 'vue'
import { VueDraggableNext as draggable } from 'vue-draggable-next'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import { apiClients } from '@/api/client'
import { CancelIcon, DownloadIcon, DragVerticalIcon } from '@/icons/vue-material'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'
import { exportSetlistToPdf } from '@/utils/pdf-exporter'
import { fetchPdfTemplates } from '@/utils/pdfTemplatesApi'

interface Props {
	setlist: Setlist
	entries: SetlistEntry[]
	fcvScoresMap: Map<number, number>
	fcvScoreBookIndicesMap: Map<number, number>
	folderCollection: FolderCollection | null
	/** Returns the current columns from SetlistEntriesTable in display order */
	getColumns: () => PdfColumnConfig[]
}

/** Whether the dialog is open */
const dialogOpen = defineModel<boolean>('open', { default: false })

const props = defineProps<Props>()

const scoreBooksStore = useScoreBooksStore()

interface DialogColumn extends PdfColumnConfig {
	enabled: boolean
}

const dialogColumns = ref<DialogColumn[]>([])
const hasUserCustomizedColumns = ref(false)
const selectedPdfTemplateId = ref<string | null>(null)
const availablePdfTemplates = ref<PdfTemplate[]>([])
const loadingTemplates = ref(false)
const pdfExporting = ref(false)

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
 * Populate the dialog with the current column order from the table.
 */
function populateColumns(): void {
	const currentColumns = props.getColumns()
		.filter((col) => col.label.trim() !== '')

	if (!hasUserCustomizedColumns.value) {
		dialogColumns.value = currentColumns.map((col) => ({
			...col,
			enabled: DEFAULT_ENABLED_IDS.value.has(col.id),
		}))
	}
}

watch(dialogOpen, async (isOpen) => {
	if (!isOpen) {
		return
	}
	populateColumns()
	const setlistId = props.setlist.id
	selectedPdfTemplateId.value = null
	availablePdfTemplates.value = []
	loadingTemplates.value = true
	try {
		await tryShowError(async () => {
			availablePdfTemplates.value = await fetchPdfTemplates()
		}, t('Failed to load PDF templates'))
	} finally {
		loadingTemplates.value = false
	}
	if (!dialogOpen.value || setlistId !== props.setlist.id) {
		return
	}
	selectedPdfTemplateId.value = availablePdfTemplates.value
		.find(({ id }) => id === props.setlist.defaultTemplateId)?.id ?? null
})

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
	if (loadingTemplates.value || pdfExporting.value) {
		return
	}
	dialogOpen.value = false
	pdfExporting.value = true
	const templateId = selectedPdfTemplateId.value
	const { setlist, entries, fcvScoresMap, fcvScoreBookIndicesMap, folderCollection } = props
	const selectedColumns = dialogColumns.value
		.filter((col) => col.enabled)
		.map(({ id, label }) => ({ id, label }))

	try {
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
	} finally {
		pdfExporting.value = false
	}
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
</style>
