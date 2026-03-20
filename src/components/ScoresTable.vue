<template>
	<div style="height: 100%;">
		<FullPageTable
			ref="tableRef"
			:data="scores"
			:column-defs="columnDefs"
			:editable="editable"
			:modules="gridModules"
			:context="gridContext"
			@cell-value-changed="handleCellValueChanged"
			@cell-double-clicked="handleCellDoubleClicked" />

		<AssignScoreBookDialog
			v-model="showAssignDialog"
			:score-id="editingScoreId"
			:current-score-book-id="editingScoreBookId"
			:current-index="editingScoreBookIndex"
			@updated="handleScoreBookUpdated" />
	</div>
</template>

<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import { t } from '@/utils/l10n'
import { parseArrayValue } from '@/utils/arrayUtils'
import { parseDurationHHMMSS } from '@/utils/timeFormatUtils'
import { createDurationColumn } from '@/utils/durationColumnUtils'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import FullPageTable from '@/components/FullPageTable.vue'
import RowActionButton from '@/components/RowActionButton.vue'
import AssignScoreBookDialog from '@/components/AssignScoreBookDialog.vue'
import type {
	CellDoubleClickedEvent,
	CellValueChangedEvent,
	ColDef,
} from 'ag-grid-community'
import {
	TooltipModule,
} from 'ag-grid-community'
import type {
	Score,
	ScoreBook,
	ScoreIndexed,
} from '@/api/generated/openapi/data-contracts'
import ScoreOrScoreBookTitleRenderer
	from '@/components/ScoreOrScoreBookTitleRenderer.vue'
import { useScoresStore } from '@/stores/scoresStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useTagsStore } from '@/stores/tagsStore'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'

const gridModules = [TooltipModule]

interface Props {
	editable: boolean
	/**
	 * Optional score book ID to filter scores
	 * If provided, only shows scores from this score book
	 */
	scoreBookId?: number | null
	/**
	 * Whether the folder collection is indexed (vs alphabetical)
	 * Only used when folderCollectionId is provided
	 */
	isIndexedCollection?: boolean
	/**
	 * Scores data for folder collection mode
	 * Pass in the prepared data from the parent component
	 */
	folderCollectionScores?: (Score | ScoreIndexed)[]
	/**
	 * Score book index map for folder collection mode
	 * Maps score book ID to its collection index
	 */
	scoreBookIndexMap?: Map<number, number>
	/**
	 * Custom delete handler for folder collection mode
	 */
	onDeleteScore?: (scoreId: number, scoreBookId: number | null, viaScoreBook: boolean) => Promise<void>
}

const props = defineProps<Props>()

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const tagsStore = useTagsStore()
const scoreSidebarStore = useScoreSidebarStore()

// Ref to access FullPageTable exposed methods
type TableExportRef = { exportAsCsv?: (fileName?: string) => boolean }
const tableRef = ref<TableExportRef | null>(null)

/**
 * Computed scores data - filtered by scoreBookId if provided, or use folderCollectionScores
 */
const scores = computed<(Score | ScoreIndexed)[]>(() => {
	if (props.folderCollectionScores) {
		// Use provided folder collection scores
		return props.folderCollectionScores
	}
	if (props.scoreBookId) {
		// Filter scores belonging to the specified score book
		return scoreBooksStore.getScoreBookScores(props.scoreBookId)
	}
	// Return all scores
	return scoresStore.scores
})

const hasScoreBooks = computed(() => {
	return props.scoreBookIndexMap && props.scoreBookIndexMap.size > 0
})

/**
 * Grid context for cell renderers
 */
const gridContext = computed(() => ({
	hasScoreBooks: (props.folderCollectionScores && hasScoreBooks.value),
}))

// Score book dialog state
const showAssignDialog = ref(false)
const editingScoreId = ref<number>(0)
const editingScoreBookId = ref<number | null>(null)
const editingScoreBookIndex = ref<number | null>(null)

/**
 * Get score book ID from score (handles the scoreBook object structure)
 * @param score - The score to get the book ID from
 */
function getScoreBookId(score: Score): number | null {
	return score.scoreBook?.id ?? null
}

/**
 * Get score book index from score (handles the scoreBook object structure)
 * @param score - The score to get the book index from
 */
function getScoreBookIndex(score: Score): number | null {
	return score.scoreBook?.index ?? null
}

/**
 * Get score book by ID from the store
 * @param scoreBookId - The ID of the score book to retrieve
 */
function getScoreBook(scoreBookId: number | null): ScoreBook | undefined {
	if (scoreBookId === null) return undefined
	return scoreBooksStore.getScoreBookById(scoreBookId)
}

/**
 * Value getter for fields that can inherit from score book
 * Returns the score's own value, or the score book's value if score has none
 * @param score - The score to get the value from
 * @param field - The field name to retrieve
 */
function getInheritableValue(score: Score, field: keyof Score): unknown {
	const scoreValue = score[field]
	if (scoreValue !== null && scoreValue !== undefined && scoreValue !== '') {
		return scoreValue
	}
	// Check if score is in a book and inherit from book
	const bookId = getScoreBookId(score)
	if (bookId !== null) {
		const book = getScoreBook(bookId)
		if (book && field in book) {
			return (book as unknown as Record<string, unknown>)[field]
		}
	}
	return scoreValue
}

/**
 * Create a value getter function for inheritable fields
 * @param field - The field name to create a getter for
 */
function createInheritableValueGetter(field: keyof Score) {
	return (params: { data: Score }) => {
		if (!params.data) return null
		return getInheritableValue(params.data, field)
	}
}

/**
 * Value getter for scoreBook.index
 * @param params - AG Grid params containing the row data
 * @param params.data - The score row data
 */
function scoreBookIndexValueGetter(params: { data: Score }) {
	if (!params.data) return null
	return getScoreBookIndex(params.data)
}

/**
 * Value getter for scoreBook.title
 * Returns the title for sorting and filtering
 * Falls back to the string representation of the ID if title is missing
 * @param params - AG Grid params containing the row data
 * @param params.data - The score row data
 */
function scoreBookTitleValueGetter(params: { data: Score }) {
	if (!params.data) return null
	const scoreBookId = getScoreBookId(params.data)
	if (scoreBookId === null) return null
	const book = getScoreBook(scoreBookId)
	return book?.title || String(scoreBookId)
}

// Define per-column edit handlers
type ColumnEditHandler = (
	id: number,
	newValue: unknown,
	oldValue: unknown,
	score: Score
) => Promise<void>

/**
 * Factory function to create handlers for inheritable fields
 * Inheritable fields set value to null if it matches the score book's value
 * @param field The field name
 */
function inheritableFieldHandlerFactory(field: string): ColumnEditHandler {
	return async (id, newValue, _oldValue, score) => {
		let valueToStore = newValue

		// If score is in a book, check if new value matches book value
		const bookId = getScoreBookId(score)
		if (bookId !== null) {
			const book = getScoreBook(bookId)
			if (book) {
				const bookValue = (book as unknown as Record<string, unknown>)[field]
				// If new value matches book value, or is empty string, store null
				if (newValue === bookValue || newValue === '' || newValue === null) {
					valueToStore = null
				}
			}
		}

		// Empty string should be null
		if (valueToStore === '') {
			valueToStore = null
		}

		await scoresStore.updateScoreFieldApi(id, field, valueToStore)
	}
}

const columnEditHandlers: Record<string, ColumnEditHandler> = {
	tags: async (id, newValue) => {
		const names = newValue as string[] | null

		if (!names || names.length === 0) {
			// Clear all tags
			await scoresStore.updateScoreTags(id, [])
			return
		}

		const tagIds = await tagsStore.namesToIds(names)
		await scoresStore.updateScoreTags(id, tagIds)
	},
	medleyContents: async (id, newValue) => {
		const contents = parseArrayValue(newValue)
		await scoresStore.updateScoreFieldApi(id, 'medleyContents', contents)
	},
	gemaIds: async (id, newValue) => {
		const gemaIds = parseArrayValue(newValue)
		await scoresStore.updateScoreFieldApi(id, 'gemaIds', gemaIds)
	},
	duration: async (id, newValue) => {
		let parsedDuration: number | null
		try {
			parsedDuration = parseDurationHHMMSS(newValue as string)
		} catch (e) {
			showError(t('Invalid duration format. Use (HH:)MM:SS'))
			return
		}
		await scoresStore.updateScoreFieldApi(id, 'duration', parsedDuration)
	},
	// Inheritable fields using the factory
	composer: inheritableFieldHandlerFactory('composer'),
	arranger: inheritableFieldHandlerFactory('arranger'),
	publisher: inheritableFieldHandlerFactory('publisher'),
	year: inheritableFieldHandlerFactory('year'),
	difficulty: inheritableFieldHandlerFactory('difficulty'),
	defects: inheritableFieldHandlerFactory('defects'),
	physicalCopiesStatus: inheritableFieldHandlerFactory('physicalCopiesStatus'),
}

/**
 * Handle double-click on cells - open dialog for score book columns
 * @param event - AG Grid cell double-click event
 */
function handleCellDoubleClicked(event: CellDoubleClickedEvent) {
	// Check if the column is a score book column by headerName since field uses valueGetter
	const headerName = event.colDef?.headerName
	const isScoreBookColumn = headerName === t('Score Book') || headerName === t('Book Index')
	if (isScoreBookColumn && props.editable) {
		const score = event.data as Score
		editingScoreId.value = score.id
		editingScoreBookId.value = getScoreBookId(score)
		editingScoreBookIndex.value = getScoreBookIndex(score)
		showAssignDialog.value = true
	}
}

/**
 * Handle score book update from dialog
 * @param scoreBookId - The new score book ID, or null if removed
 * @param index - The new index in the score book, or null if removed
 */
async function handleScoreBookUpdated(scoreBookId: number | null, index: number | null) {
	const oldScoreBookId = editingScoreBookId.value

	// If removing from a scorebook when viewing that scorebook's page
	if (scoreBookId === null && props.scoreBookId && oldScoreBookId === props.scoreBookId) {
		await handleRemoveFromScoreBook(editingScoreId.value)
		return
	}

	// Update the score book assignment
	if (scoreBookId === null) {
		scoresStore.updateScoreBookAssignment(editingScoreId.value, null)
	} else {
		scoresStore.updateScoreBookAssignment(editingScoreId.value, { id: scoreBookId, index: index! })
	}
}

/**
 * Remove a score from the current scorebook (when viewing a scorebook page)
 * @param scoreId - The ID of the score to remove
 */
async function handleRemoveFromScoreBook(scoreId: number) {
	if (!props.scoreBookId) return

	const result = await spawnDialog(
		ConfirmationDialog,
		{
			title: t('Remove score from book'),
			message: t('Are you sure you want to remove this score from the book?'),
			countdown: null,
		},
	)

	if (!result) {
		return
	}

	await tryShowError(
		async () => {
			// Remove from the score book via API and update stores
			await scoreBooksStore.removeScoreFromBook(props.scoreBookId, scoreId)

			// Update the score's scoreBook assignment in scoresStore
			scoresStore.updateScoreBookAssignment(scoreId, null)

			// Close sidebar if it's open for this score
			if (scoreSidebarStore.selectedScore?.id === scoreId) {
				scoreSidebarStore.closeSidebar()
			}

			showSuccess(t('Score removed from book'))
		},
		t('Failed to remove score from book: '),
	)
}

// Wrapper function that handles try-catch and table updates
async function handleCellValueChanged(event: CellValueChangedEvent) {
	const field = (event.colDef && (event.colDef.field as string)) || ''
	if (!field) return

	const id = event.data && event.data.id
	if (!id) return

	const score = event.data as Score

	await tryShowError(
		async () => {
			const handler = columnEditHandlers[field]
			if (handler) {
				// Use custom handler (includes inheritable field handlers from factory)
				await handler(id, event.newValue, event.oldValue, score)
			} else {
				// Use default handler for fields without custom handler
				await scoresStore.updateScoreFieldApi(id, field, event.newValue)
			}

			showSuccess(t('Changes saved'))
		},
		t('Saving changes failed: '),
	)
}

// Column definitions
const columnDefs = computed<ColDef[]>(() => {
	// Build custom delete handler when in scorebook or folder collection mode
	let customDeleteHandler: ((data: Score | ScoreBook) => Promise<void>) | undefined
	let deleteText: string | undefined

	if (props.folderCollectionScores && props.onDeleteScore) {
		// Folder collection mode
		customDeleteHandler = async (data: Score | ScoreIndexed) => {
			const score = data as Score | ScoreIndexed
			const scoreBookId = score.scoreBook?.id ?? null
			const viaScoreBook = score.viaScoreBook ?? false
			await props.onDeleteScore!(score.id, scoreBookId, viaScoreBook)
		}
		deleteText = t('Remove from collection')
	} else if (props.scoreBookId) {
		// Score book mode
		customDeleteHandler = async (data: Score | ScoreBook) => {
			await handleRemoveFromScoreBook((data as Score).id)
		}
		deleteText = t('Remove from book')
	}

	const cols: ColDef[] = [
		{
			headerName: '',
			pinned: 'left' as const,
			filter: false,
			editable: false,
			cellRenderer: markRaw(RowActionButton),
			cellRendererParams: {
				type: 'score',
				showDeleteButton: props.editable,
				deleteText,
				customDeleteHandler,
			},
			width: 60,
			resizable: false,
			suppressMovable: true,
		},
	]

	// Add index column for indexed folder collections
	if (props.folderCollectionScores && props.isIndexedCollection) {
		cols.push({
			field: 'collectionIndex',
			headerName: t('Index'),
			pinned: 'left' as const,
			filter: 'agNumberColumnFilter',
			editable: false,
			width: 100,
			valueGetter: (params) => {
				if (!params.data) return null
				const score = params.data as ScoreIndexed
				if (score.viaScoreBook) {
					// Inherited score - format as "bookIndex.scoreIndex"
					const scoreInBookIndex = score.scoreBook!.index
					const bookIndex = props.scoreBookIndexMap?.get(getScoreBookId(score)!) ?? null
					if (bookIndex !== null && scoreInBookIndex !== null) {
						return `${bookIndex}.${scoreInBookIndex}`
					}
				}

				// Direct score - use its index
				return score.index ?? null
			},
			comparator: (valueA: unknown, valueB: unknown) => {
				// Custom comparator to handle "bookIndex.scoreIndex" format
				const parseIndex = (value: unknown): [number, number] => {
					if (value === null || value === undefined) return [Infinity, Infinity]
					const str = String(value)
					if (str.includes('.')) {
						const parts = str.split('.')
						return [parseFloat(parts[0]) || Infinity, parseFloat(parts[1]) || Infinity]
					}
					return [parseFloat(str) || Infinity, 0]
				}

				const [aBook, aScore] = parseIndex(valueA)
				const [bBook, bScore] = parseIndex(valueB)

				// Compare book index first, then score index
				if (aBook !== bBook) return aBook - bBook
				return aScore - bScore
			},
		})
	}

	// Title column with optional icon renderer
	const titleColumn: ColDef = {
		field: 'title',
		pinned: 'left' as const,
		headerName: t('Title'),
		...(props.folderCollectionScores && hasScoreBooks.value
			? {
				cellRenderer: markRaw(ScoreOrScoreBookTitleRenderer),
				tooltipValueGetter: (params) => {
					const score = params.data as Score | ScoreIndexed
					// Only show tooltip for inherited scores (not direct members)
					if (score.viaScoreBook) {
						const scoreBook = getScoreBook(getScoreBookId(score))
						if (scoreBook) {
							return t('Included in folder collection via score book »{name}«', { name: scoreBook.title })
						}
					}
					return null
				},
			}
			: {}),
	}
	cols.push(titleColumn)

	cols.push(
		{
			field: 'scoreBook.id',
			headerName: t('Score Book'),
			editable: false,
			valueGetter: scoreBookTitleValueGetter,
			hide: !!props.scoreBookId, // Hide when viewing a specific scorebook
		},
		{
			field: 'scoreBook.index',
			headerName: t('Book Index'),
			filter: 'agNumberColumnFilter',
			editable: false,
			valueGetter: scoreBookIndexValueGetter,
		},
		{ field: 'titleShort', headerName: t('Short Title') },
		{
			field: 'composer',
			headerName: t('Composer'),
			valueGetter: createInheritableValueGetter('composer'),
		},
		{
			field: 'arranger',
			headerName: t('Arranger'),
			valueGetter: createInheritableValueGetter('arranger'),
		},
		{
			field: 'publisher',
			headerName: t('Publisher'),
			valueGetter: createInheritableValueGetter('publisher'),
		},
		{
			field: 'year',
			headerName: t('Year'),
			filter: 'agNumberColumnFilter',
			cellEditor: 'agNumberCellEditor',
			valueGetter: createInheritableValueGetter('year'),
		},
		{
			field: 'difficulty',
			headerName: t('Difficulty'),
			filter: 'agNumberColumnFilter',
			cellEditor: 'agNumberCellEditor',
			valueGetter: createInheritableValueGetter('difficulty'),
		},
		createDurationColumn('duration', t('Duration')),
		{ field: 'medleyContents', headerName: t('Medley Contents'), valueParser: params => parseArrayValue(params.newValue) },
		{ field: 'gemaIds', headerName: t('GEMA IDs'), valueParser: params => parseArrayValue(params.newValue) },
		{
			field: 'defects',
			headerName: t('Defects'),
			valueGetter: createInheritableValueGetter('defects'),
		},
		{ field: 'digitalStatus', headerName: t('Digital Status') },
		{
			field: 'physicalCopiesStatus',
			headerName: t('Physical Copies'),
			valueGetter: createInheritableValueGetter('physicalCopiesStatus'),
		},
		// custom valueParser to enable editing of array-type data
		{ field: 'tags', headerName: t('Tags'), valueParser: params => parseArrayValue(params.newValue) },
	)

	return cols
})

// Expose tableRef for export functionality
defineExpose({ tableRef })
</script>
