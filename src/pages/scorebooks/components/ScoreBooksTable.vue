<template>
	<FullPageTable
		ref="tableRef"
		:data="scoreBooksStore.scoreBooks"
		:column-defs="columnDefs"
		:editable="editable"
		@cell-value-changed="handleCellValueChanged" />
</template>

<script setup lang="ts">
import { markRaw, ref } from 'vue'
import { t } from '@/utils/l10n'
import { parseArrayValue } from '@/utils/arrayUtils'
import { showSuccess } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import FullPageTable from '@/components/FullPageTable.vue'
import RowActionButton from '@/components/RowActionButton.vue'
import { OpenExternalIcon } from '@/icons/vue-material'
import type { ColDef, CellValueChangedEvent } from 'ag-grid-community'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useTagsStore } from '@/stores/tagsStore'
import type { ScoreBook } from '@/api/generated/openapi/data-contracts'

interface Props {
	editable: boolean
}

const props = defineProps<Props>()

const scoreBooksStore = useScoreBooksStore()
const tagsStore = useTagsStore()

// Ref to access FullPageTable exposed methods
type TableExportRef = { exportAsCsv?: (fileName?: string) => boolean }
const tableRef = ref<TableExportRef | null>(null)

// Define per-column edit handlers
type ColumnEditHandler = (
	id: number,
	newValue: unknown,
	oldValue: unknown
) => Promise<void>

const columnEditHandlers: Record<string, ColumnEditHandler> = {
	tags: async (id, newValue) => {
		const names = newValue as string[] | null

		if (!names || names.length === 0) {
			// Clear all tags
			await scoreBooksStore.updateScoreBookTags(id, [])
			return
		}

		const tagIds = await tagsStore.namesToIds(names)
		await scoreBooksStore.updateScoreBookTags(id, tagIds)
	},
}

// Wrapper function that handles try-catch and table updates
async function handleCellValueChanged(event: CellValueChangedEvent) {
	const field = (event.colDef && (event.colDef.field as string)) || ''
	if (!field) return

	const id = event.data && event.data.id
	if (!id) return

	await tryShowError(
		async () => {
			const handler = columnEditHandlers[field]

			if (handler) {
				// Use custom handler
				await handler(id, event.newValue, event.oldValue)
			} else {
				// Use store method for default handler
				await scoreBooksStore.updateScoreBookFieldApi(id, field, event.newValue)
			}

			showSuccess(t('Changes saved'))
		},
		t('Saving changes failed: '),
	)
}

// Column definitions
const columnDefs = ref<ColDef[]>([
	{
		headerName: '',
		pinned: 'left' as const,
		filter: false,
		editable: false,
		cellRenderer: markRaw(RowActionButton),
		cellRendererParams: {
			type: 'scorebook',
			showDeleteButton: props.editable,
			customActions: [
				{
					name: t('Open'),
					icon: markRaw(OpenExternalIcon),
					to: (data: ScoreBook) => ({ name: 'scorebook', params: { id: data.id } }),
				},
			],
		},
		width: 60,
		resizable: false,
		suppressMovable: true,
	},
	{ field: 'title', headerName: t('Title') },
	{ field: 'titleShort', headerName: t('Short Title') },
	{ field: 'composer', headerName: t('Composer') },
	{ field: 'arranger', headerName: t('Arranger') },
	{ field: 'editor', headerName: t('Editor') },
	{ field: 'publisher', headerName: t('Publisher') },
	{ field: 'year', headerName: t('Year'), filter: 'agNumberColumnFilter', cellEditor: 'agNumberCellEditor' },
	{ field: 'difficulty', headerName: t('Difficulty'), filter: 'agNumberColumnFilter', cellEditor: 'agNumberCellEditor' },
	{ field: 'defects', headerName: t('Defects') },
	{ field: 'physicalCopiesStatus', headerName: t('Physical Copies') },
	// custom valueParser to enable editing of array-type data
	{ field: 'tags', headerName: t('Tags'), valueParser: params => parseArrayValue(params.newValue) },
])

// Expose tableRef for export functionality
defineExpose({ tableRef })
</script>
