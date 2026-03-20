<template>
	<div style="height: 100%;">
		<FullPageTable
			ref="tableRef"
			:data="tableData"
			:column-defs="columnDefs"
			:editable="false"
			:modules="gridModules"
			:context="gridContext"
			:row-drag-managed="props.editable"
			@cell-value-changed="handleCellValueChanged"
			@row-drag-end="handleRowDragEnd" />
	</div>
</template>

<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import { t } from '@/utils/l10n'
import { parseDurationHHMMSS } from '@/utils/timeFormatUtils'
import { createDurationColumn } from '@/utils/durationColumnUtils'
import { tryShowError } from '@/utils/errorHandling'
import FullPageTable from '@/components/FullPageTable.vue'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useScoresStore } from '@/stores/scoresStore'
import { useSetlistEntriesStore } from '@/stores/setlistEntriesStore'
import {
	RowDragModule,
	ClientSideRowModelModule,
} from 'ag-grid-community'
import type { ColDef, CellValueChangedEvent, RowDragEndEvent, GridApi } from 'ag-grid-community'
import type { Score, Setlist, SetlistEntry, FolderCollection } from '@/api/generated/openapi/data-contracts'
import { parseArrayValue } from '@/utils/arrayUtils'
import RowActionButton from './RowActionButton.vue'
import type { PdfColumnConfig, PdfColumnId } from '@/utils/pdf-exporter'
import { isBreakEntry, resolveScoreField } from '@/utils/setlistScoreUtils'
import type { ScoreInfoField } from '@/utils/setlistScoreUtils'
import { showError } from '@nextcloud/dialogs'

const gridModules = [
	ClientSideRowModelModule,
	RowDragModule,
]

interface Props {
	setlist: Setlist
	entries: SetlistEntry[]
	editable: boolean
	/**
	 * Map of score ID to FCV index (only for direct scores, not via score books)
	 */
	fcvScoresMap?: Map<number, number>
	/**
	 * Map of score book ID to FCV index
	 */
	fcvScoreBookIndicesMap?: Map<number, number>
	/**
	 * Folder collection information (if setlist has a FCV)
	 */
	folderCollection?: FolderCollection | null
}

const props = defineProps<Props>()

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const setlistEntriesStore = useSetlistEntriesStore()

// Ref to access FullPageTable exposed methods
type TableExportRef = { exportAsCsv?: (fileName?: string) => boolean; getGridApi?: () => GridApi | null }
const tableRef = ref<TableExportRef | null>(null)

/**
 * Extended entry type for table display with computed fields
 */
interface TableEntry extends SetlistEntry {
	startTime: number
	endTime: number
	effectiveModerationDuration: number
	effectiveDuration: number
	isEndTimeOverflow: boolean
}

/**
 * Look up score for table entry
 * @param entry - The setlist entry or null/undefined
 */
function getScoreForEntry(entry: SetlistEntry | null | undefined): Score | null {
	if (!entry?.scoreId) return null
	return scoresStore.getScoreById(entry.scoreId) ?? null
}

/**
 * Cache for cumulative times to avoid recomputation
 * Reactive - will be recomputed when entries change
 * Includes one extra entry at the end for simplified endTime calculation
 */
const cumulativeTimes = computed<number[]>(() => {
	const times: number[] = []
	let cumulativeTime = 0

	// If setlist has a start time, begin cumulative time at that offset
	if (props.setlist.startDateTime) {
		const startDate = new Date(props.setlist.startDateTime)
		// Calculate seconds from start of day
		cumulativeTime = startDate.getHours() * 3600 + startDate.getMinutes() * 60 + startDate.getSeconds()
	}

	props.entries.forEach((entry) => {
		times.push(cumulativeTime)
		const modDuration = entry.moderationDuration ?? props.setlist.defaultModerationDuration ?? 0
		const score = getScoreForEntry(entry)
		const duration = entry.breakDuration ?? score?.duration ?? 0
		cumulativeTime += modDuration + duration
	})

	// Add final cumulative time for endTime calculation
	times.push(cumulativeTime)

	return times
})

/**
 * Table data with computed start/end times
 */
const tableData = computed<TableEntry[]>(() => {
	const setlistDuration = props.setlist.duration

	return props.entries.map((entry, index) => {
		const startTime = cumulativeTimes.value[index]
		const endTime = cumulativeTimes.value[index + 1]
		const modDuration = entry.moderationDuration ?? props.setlist.defaultModerationDuration ?? 0

		const score = getScoreForEntry(entry)
		const duration = entry.breakDuration ?? score?.duration ?? 0

		return {
			...entry,
			startTime,
			endTime,
			effectiveModerationDuration: modDuration,
			effectiveDuration: duration,
			isEndTimeOverflow: setlistDuration !== null && endTime > setlistDuration,
		}
	})
})

/**
 * Check if setlist has a folder collection version that is indexed
 */
const hasFolderCollectionVersion = computed(() => {
	return props.setlist.folderCollectionVersionId !== null
})

/**
 * Check if the folder collection is indexed (not alphabetical)
 */
const isIndexedCollection = computed(() => {
	return props.folderCollection?.collectionType === 'indexed'
})

/**
 * Grid context for cell renderers
 */
const gridContext = computed(() => ({
	hasFolderCollectionVersion: hasFolderCollectionVersion.value,
}))

/**
 * Custom cell renderer for score information that spans columns for breaks
 * This is a simple approach - we'll use a single column with special formatting
 * @param field - The field to get the value for
 */
function scoreInfoValueGetter(field: ScoreInfoField) {
	return (params: { data: TableEntry }) => {
		if (!params.data) return null
		const entry = params.data as TableEntry
		const score = getScoreForEntry(entry)
		return resolveScoreField(
			field,
			entry,
			score,
			(id) => scoreBooksStore.getScoreBookById(id),
			props.fcvScoresMap,
			props.fcvScoreBookIndicesMap,
		)
	}
}

/**
 * Handle row drag end event for reordering
 * @param event - AG Grid row drag end event
 */
async function handleRowDragEnd(event: RowDragEndEvent): Promise<void> {
	const movedNode = event.node
	const overNode = event.overNode

	if (!movedNode || !overNode || !movedNode.data || !overNode.data) {
		return
	}

	// Get all nodes in the new order
	const allNodes: TableEntry[] = []
	event.api.forEachNodeAfterFilterAndSort((node) => {
		if (node.data) {
			allNodes.push(node.data as TableEntry)
		}
	})

	// Build updates for entries that need reordering
	// Only update entries whose positions have changed
	const updates: Array<{ id: number; index: number }> = []

	allNodes.forEach((entry, newIndex) => {
		// New index should be the position in the list (0-based, but we'll use entry indices)
		const newEntryIndex = newIndex * 10 // Use increments of 10 for flexibility
		if (entry.index !== newEntryIndex) {
			updates.push({ id: entry.id, index: newEntryIndex })
		}
	})

	if (updates.length === 0) {
		return
	}

	await tryShowError(
		async () => {
			await setlistEntriesStore.batchUpdateEntries(updates)
			// Overlaps with "add entry" button which is annoying. Let's skip the toast for now
			// showSuccess(t('Entries reordered successfully'))
		},
		t('Failed to reorder entries: '),
		() => {
			// Refresh the data to revert UI changes
			event.api.refreshCells()
		},
	)
}

// Define per-column edit handlers
type ColumnEditHandler = (
	id: number,
	newValue: unknown,
	oldValue: unknown,
	entry: TableEntry,
) => Promise<void>

const columnEditHandlers: Record<string, ColumnEditHandler> = {
	moderationDuration: async (id, newValue) => {
		let parsedDuration: number | null
		try {
			parsedDuration = parseDurationHHMMSS(newValue as string)
		} catch (e) {
			showError(t('Invalid duration format. Use (HH:)MM:SS'))
			return
		}
		await setlistEntriesStore.updateEntry(id, { moderationDuration: parsedDuration })
	},
	breakDuration: async (id, newValue) => {
		let parsedDuration: number | null
		try {
			parsedDuration = parseDurationHHMMSS(newValue as string)
		} catch (e) {
			showError(t('Invalid duration format. Use (HH:)MM:SS'))
			return
		}
		await setlistEntriesStore.updateEntry(id, { breakDuration: parsedDuration })
	},
	comment: async (id, newValue) => {
		const commentValue = newValue === '' ? null : (newValue as string)
		await setlistEntriesStore.updateEntry(id, { comment: commentValue })
	},
}

/**
 * Handle cell value changes
 * @param event - AG Grid cell value changed event
 */
async function handleCellValueChanged(event: CellValueChangedEvent): Promise<void> {
	const field = (event.colDef && (event.colDef.field as string)) || ''
	if (!field) return

	const entry = event.data as TableEntry
	const id = entry.id
	if (!id) return

	await tryShowError(
		async () => {
			const handler = columnEditHandlers[field]
			await handler(id, event.newValue, event.oldValue, entry)
			// Overlaps with "add entry" button which is annoying. Let's skip the toast for now
			// showSuccess(t('Changes saved'))
		},
		t('Saving changes failed: '),
		() => {
			// Revert the cell value
			event.api.refreshCells({ rowNodes: [event.node!], force: true })
		},
	)
}

/**
 * Column definitions
 */
const columnDefs = computed<ColDef[]>(() => {
	const cols: ColDef[] = [
		{
			headerName: '',
			pinned: 'left' as const,
			filter: false,
			editable: false,
			sortable: false,
			cellRenderer: markRaw(RowActionButton),
			cellRendererParams: (params: { data: TableEntry }) => {
				const entry = params.data

				return {
					score: getScoreForEntry(entry),
					setlistEntry: entry,
					showDeleteButton: props.editable,
				}
			},
			width: props.editable ? 50 : 60,
			resizable: false,
			suppressMovable: true,
			rowDrag: props.editable,
		},
		{
			...createDurationColumn('startTime', t('Start Time'), false),
			colId: 'startTime' satisfies PdfColumnId,
			editable: false,
		},
		{
			...createDurationColumn('endTime', t('End Time'), false),
			colId: 'endTime' satisfies PdfColumnId,
			cellClass: (params) => {
				const entry = params.data as TableEntry
				return entry.isEndTimeOverflow ? 'end-time-overflow' : ''
			},
			editable: false,
		},
		{
			...createDurationColumn('moderationDuration', t('Moderation'), props.editable),
			colId: 'moderation' satisfies PdfColumnId,
			// Show effective value (entry value or default)
			valueGetter: (params) => {
				const entry = params.data as TableEntry
				return entry?.moderationDuration ?? props.setlist.defaultModerationDuration ?? null
			},
			editable: props.editable,
		},
		{
			...createDurationColumn('breakDuration', t('Duration'), props.editable, false),
			colId: 'duration' satisfies PdfColumnId,
			// Show effective duration (break duration for breaks, score duration for scores)
			valueGetter: (params) => {
				const entry = params.data as TableEntry
				const score = getScoreForEntry(entry)
				return entry?.breakDuration ?? score?.duration ?? null
			},
			// Only editable for break entries
			editable: (params) => {
				const entry = params.data as TableEntry
				return props.editable && isBreakEntry(entry)
			},
		},
	]

	// Add FCV index column if setlist has an indexed folder collection version
	if (hasFolderCollectionVersion.value && isIndexedCollection.value) {
		cols.push({
			headerName: t('Index'),
			colId: 'fcvIndex' satisfies PdfColumnId,
			valueGetter: scoreInfoValueGetter('fcvIndex'),
			filter: 'agNumberColumnFilter',
			editable: false,
		})
	}

	// Score information columns
	cols.push({
		headerName: t('Title'),
		colId: 'title' satisfies PdfColumnId,
		valueGetter: scoreInfoValueGetter('title'),
		editable: false,
	})

	cols.push({
		headerName: t('Difficulty'),
		colId: 'difficulty' satisfies PdfColumnId,
		valueGetter: scoreInfoValueGetter('difficulty'),
		filter: 'agNumberColumnFilter',
		editable: false,
	})

	// Add score book columns if setlist has no folder collection version
	if (!hasFolderCollectionVersion.value) {
		cols.push({
			headerName: t('Score Book'),
			colId: 'bookName' satisfies PdfColumnId,
			valueGetter: scoreInfoValueGetter('bookName'),
			editable: false,
		})

		cols.push({
			headerName: t('Book Index'),
			colId: 'bookIndex' satisfies PdfColumnId,
			valueGetter: scoreInfoValueGetter('bookIndex'),
			filter: 'agNumberColumnFilter',
			editable: false,
		})
	}

	// comment on setlist entry
	cols.push({
		field: 'comment',
		colId: 'comment' satisfies PdfColumnId,
		headerName: t('Comment'),
		editable: props.editable,
	})

	// gema ids
	cols.push({
		headerName: t('GEMA IDs'),
		colId: 'gemaIds' satisfies PdfColumnId,
		valueGetter: scoreInfoValueGetter('gemaIds'),
		valueParser: params => parseArrayValue(params.newValue),
	})

	return cols
})

/**
 * Return the columns currently displayed in the table, in display order,
 * as PDF column configs. Uses the AG Grid API when available so that any
 * column reordering done by the user is reflected in the result.
 */
function getPdfColumns(): PdfColumnConfig[] {
	const api = tableRef.value?.getGridApi?.()
	if (api) {
		return api.getAllDisplayedColumns()
			.filter(col => !!col.getColDef().colId)
			.map(col => ({
				id: col.getColDef().colId as PdfColumnId,
				label: col.getColDef().headerName ?? '',
			}))
	}
	// Fallback: use the computed column defs in definition order
	return columnDefs.value
		.filter(col => !!col.colId)
		.map(col => ({
			id: col.colId as PdfColumnId,
			label: col.headerName ?? '',
		}))
}

// Expose tableRef for export functionality and getPdfColumns for ExportPdfButton
defineExpose({ tableRef, getPdfColumns })
</script>

<style lang="scss" scoped>
:deep(.end-time-overflow) {
	background-color: var(--color-error) !important;
	color: var(--color-main-background) !important;
}
</style>
