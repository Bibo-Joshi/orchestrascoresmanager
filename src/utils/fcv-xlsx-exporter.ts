import type { Worksheet } from 'exceljs'
import type {
	FolderCollection,
	FolderCollectionVersion,
	Score,
	ScoreBook,
	ScoreBookIndexed,
	ScoreIndexed,
} from '@/api/generated/openapi/data-contracts'

/**
 * XLSX exporter for folder collection Table of Contents and Index export.
 * Generates an XLSX file with two sheets: "Contents" (ToC) and "Index".
 *
 * This module is designed to be easily adaptable for formatting changes.
 */
import { Workbook } from 'exceljs'
import { formatVersionDateRange } from '@/composables/useDateFormatting'
import { t } from '@/utils/l10n'

/**
 * Entry type for collection items with their data
 */
export interface CollectionEntry {
	type: 'score' | 'scorebook'
	index?: number
	score?: Score | ScoreIndexed
	scoreBook?: ScoreBook | ScoreBookIndexed
	scores?: Score[]
}

/**
 * Configuration for XLSX formatting. Adjust these values to customize the output.
 */
export interface XlsxFormatConfig {
	/** Font size for the title header */
	titleFontSize: number
	/** Font size for the subtitle (date range) */
	subtitleFontSize: number
	/** Font size for content rows */
	contentFontSize: number
	/** Whether to make the title bold */
	titleBold: boolean
	/** Column widths for the sheets */
	columnWidths: {
		groupColumn: number
		contentColumn: number
		indexColumn: number
	}
}

/**
 * Default formatting configuration
 */
export const defaultFormatConfig: XlsxFormatConfig = {
	titleFontSize: 16,
	subtitleFontSize: 12,
	contentFontSize: 11,
	titleBold: true,
	columnWidths: {
		groupColumn: 10,
		contentColumn: 50,
		indexColumn: 10,
	},
}

/**
 * Represents a column in a ToC or Index item
 */
interface ItemColumn {
	text: string
	bold: boolean
}

/**
 * Represents an item in a Toc or Index
 */
interface Item {
	leftColumn: ItemColumn
	rightColumn: ItemColumn | null
}

/**
 * Represents a group of items in the ToC or Index
 */
interface ItemGroup {
	groupLabel: string | null
	items: Item[]
}

/**
 * Data structure for A4 layout split content
 */
interface SplitLayoutData {
	leftSection: ItemGroup[]
	rightSection: ItemGroup[] | null
}

/**
 * Calculate the total number of rows for a set of groups
 *
 * @param groups - The grouped items
 * @return Total row count including separators
 */
function countGroupRows(groups: ItemGroup[]): number {
	const itemCount = groups.reduce((sum, group) => sum + group.items.length, 0)
	return itemCount + Math.max(0, groups.length - 1)
}

/**
 * Split grouped data into left and right sections for A4 layout
 *
 * @param groups - The grouped items
 * @return Split layout data with left and right sections
 */
function splitForA4Layout(groups: ItemGroup[]): SplitLayoutData {
	let splitIndex = 0
	let leftRows = 0
	let rightRows = countGroupRows(groups)

	// Rough guess: If we're below 45 rows, it's probably not worth splitting
	if (rightRows <= 45) {
		return { leftSection: groups, rightSection: null }
	}

	while (leftRows < rightRows) {
		splitIndex++
		leftRows = countGroupRows(groups.slice(0, splitIndex))
		rightRows = countGroupRows(groups.slice(splitIndex))
	}
	const leftSection = groups.slice(0, splitIndex - 1)
	const rightSection = groups.slice(splitIndex)
	const overlapGroup = groups[splitIndex - 1]

	if (overlapGroup.items.length === 1) {
		leftSection.push(overlapGroup)
		return { leftSection, rightSection }
	}

	// distribute overLapGroup into two groups in left and right section
	const overlapLeftGroup: ItemGroup = { groupLabel: overlapGroup.groupLabel, items: [overlapGroup.items[0]] }
	const overlapRightGroup: ItemGroup = { groupLabel: overlapGroup.groupLabel, items: [...overlapGroup.items.slice(1)] }
	leftSection.push(overlapLeftGroup)
	rightSection.unshift(overlapRightGroup)
	leftRows = countGroupRows(leftSection)
	rightRows = countGroupRows(rightSection)

	while (leftRows < rightRows && overlapGroup.items.length > 0) {
		const item = overlapRightGroup.items.shift()
		if (item === undefined) {
			break
		}

		overlapLeftGroup.items.push(item)

		leftRows = countGroupRows(leftSection)
		rightRows = countGroupRows(rightSection)
	}
	if (rightSection[0].items.length === 0) {
		rightSection.shift()
	}

	return {
		leftSection,
		rightSection,
	}
}

/**
 * Get the first character of a string for grouping purposes.
 * Non-Latin characters are grouped together.
 *
 * @param text - The text to get the first character from
 * @return The uppercase first character or empty string for non-Latin
 */
function getFirstCharGroup(text: string): string {
	if (!text) {
		return ''
	}
	const firstChar = text.charAt(0).toUpperCase()
	// Check if it's a Latin letter (A-Z)
	if (firstChar >= 'A' && firstChar <= 'Z') {
		return firstChar
	}
	// Return empty string for non-Latin characters (they will be grouped together)
	return ''
}

/**
 * Get the index group label for indexed collections.
 * Groups by tens (indices 0-9 show as "9", 10-19 as "10", 20-29 as "20", etc.)
 *
 * @param index - The index number
 * @return The group label ("00" for 0-9, tens value for 10+)
 */
function getIndexGroup(index: number): string {
	const tens = Math.floor(index / 10) * 10
	if (tens === 0) {
		return '0'
	}
	return `${tens}`
}

/**
 * Flatten all entries into a list of scores with their titles and indices.
 * For scorebooks, include all scores with composite indices (e.g., "12.3").
 *
 * @param entries - Collection entries
 * @param isIndexed - Whether the collection is indexed
 * @return Flat list of items with title, shortTitle, and index
 */
function flattenEntries(
	entries: CollectionEntry[],
	isIndexed: boolean,
): {
	title: string
	shortTitle: string | null
	index?: number
	indexDisplay?: string
}[] {
	const result: {
		title: string
		shortTitle: string | null
		index?: number
		indexDisplay?: string
	}[] = []

	for (const entry of entries) {
		if (entry.type === 'scorebook' && entry.scoreBook && entry.scores) {
			// Scorebook: include all scores with composite index
			for (const score of entry.scores) {
				let indexDisplay: string | undefined
				if (isIndexed && entry.index !== undefined && score.scoreBook?.index !== undefined) {
					indexDisplay = `${entry.index}.${score.scoreBook.index}`
				}
				result.push({
					title: score.title,
					shortTitle: score.titleShort ?? null,
					index: entry.index,
					indexDisplay,
				})
			}
		} else if (entry.type === 'score' && entry.score) {
			// Direct score
			let indexDisplay: string | undefined
			const indexedScore = entry.score as ScoreIndexed
			if (isIndexed && indexedScore.index !== undefined) {
				indexDisplay = String(indexedScore.index)
			}
			result.push({
				title: entry.score.title,
				shortTitle: entry.score.titleShort ?? null,
				index: (entry.score as ScoreIndexed).index,
				indexDisplay,
			})
		}
	}

	return result
}

/**
 * Converts a map of string -> Item[] to an ItemGroup[] array
 * @param groups - the groups to process
 */
function extractItemGroup(groups: Map<string, Item[]>) {
	// Convert to ordered array (empty string first for non-Latin, then A-Z)
	const result: ItemGroup[] = []
	if (groups.has('')) {
		result.push({ groupLabel: '', items: groups.get('')! })
	}
	for (let charCode = 65; charCode <= 90; charCode++) {
		const char = String.fromCharCode(charCode)
		if (groups.has(char)) {
			result.push({ groupLabel: char, items: groups.get(char)! })
		}
	}
	return result
}

/**
 * Create Table of Contents sheet data.
 * Lists contents in the appropriate order (alphabetical or indexed).
 *
 * @param entries - Collection entries
 * @param isIndexed - Whether the collection is indexed
 * @return Grouped items for the ToC
 */
function createTocData(
	entries: CollectionEntry[],
	isIndexed: boolean,
): ItemGroup[] {
	const flatItems = flattenEntries(entries, isIndexed)

	if (isIndexed) {
		// Sort by index for indexed collections
		flatItems.sort((a, b) => {
			if (a.index === undefined) {
				return 1
			}
			if (b.index === undefined) {
				return -1
			}
			return a.index - b.index
		})

		// Find the maximum index to include all indices up to it
		let maxIndex = 0
		for (const item of flatItems) {
			if (item.index !== undefined && item.index > maxIndex) {
				maxIndex = item.index
			}
		}

		// Create a map of existing indices for quick lookup
		const existingIndices = new Map<number, {
			title: string
			indexDisplay: string
		}>()
		for (const item of flatItems) {
			if (item.index === undefined) {
				continue
			}
			existingIndices.set(item.index, {
				title: item.title,
				indexDisplay: item.indexDisplay ?? String(item.index),
			})
		}

		// Group by tens, including missing indices
		const groups = new Map<string, Item[]>()
		for (let i = 1; i <= maxIndex; i++) {
			const groupLabel = getIndexGroup(i)
			if (!groups.has(groupLabel)) {
				groups.set(groupLabel, [])
			}

			if (existingIndices.has(i)) {
				groups.get(groupLabel)!.push({
					leftColumn: {
						text: existingIndices.get(i)!.indexDisplay,
						bold: true,
					},
					rightColumn: {
						text: existingIndices.get(i)!.title,
						bold: false,
					},
				})
			} else {
				// Add empty row for missing index
				groups.get(groupLabel)!.push({
					leftColumn: { text: String(i), bold: true },
					rightColumn: null,
				})
			}
		}

		// Convert to ordered array
		const result: ItemGroup[] = []
		const sortedKeys = Array.from(groups.keys()).sort((a, b) => {
			const aNum = parseInt(a, 10)
			const bNum = parseInt(b, 10)
			return aNum - bNum
		})
		for (const key of sortedKeys) {
			result.push({ groupLabel: null, items: groups.get(key)! })
		}
		return result
	} else {
		// Sort alphabetically for alphabetical collections
		flatItems.sort((a, b) => a.title.localeCompare(b.title))

		// Group by first character
		const groups = new Map<string, Item[]>()
		for (const item of flatItems) {
			const groupLabel = getFirstCharGroup(item.title)
			if (!groups.has(groupLabel)) {
				groups.set(groupLabel, [])
			}
			groups.get(groupLabel)!.push({
				leftColumn: { text: item.title, bold: true },
				rightColumn: null, // No right column for alphabetical ToC
			})
		}
		return extractItemGroup(groups)
	}
}

/**
 * Create Index sheet data.
 * For indexed collections: lists both score titles & short titles with respective indices.
 * For alphabetical collections: lists short titles with the respective long title to look under.
 *
 * @param entries - Collection entries
 * @param isIndexed - Whether the collection is indexed
 * @return Grouped items for the Index
 */
function createIndexData(
	entries: CollectionEntry[],
	isIndexed: boolean,
): ItemGroup[] {
	const flatItems = flattenEntries(entries, isIndexed)

	if (isIndexed) {
		// For indexed: list both title and short title with the index
		const indexItems: {
			text: string
			index: number
			indexDisplay: string
		}[] = []

		for (const item of flatItems) {
			if (item.index === undefined || !item.indexDisplay) {
				continue
			}

			// Add title
			indexItems.push({
				text: item.title,
				index: item.index,
				indexDisplay: item.indexDisplay,
			})

			// Add short title if different from title
			if (item.shortTitle && item.shortTitle !== item.title) {
				indexItems.push({
					text: item.shortTitle,
					index: item.index,
					indexDisplay: item.indexDisplay,
				})
			}
		}

		// Sort alphabetically by text
		indexItems.sort((a, b) => a.text.localeCompare(b.text))

		// Group by first character
		const groups = new Map<string, Item[]>()
		for (const item of indexItems) {
			const groupLabel = getFirstCharGroup(item.text)
			if (!groups.has(groupLabel)) {
				groups.set(groupLabel, [])
			}
			groups.get(groupLabel)!.push({
				leftColumn: { text: item.text, bold: false },
				rightColumn: { text: item.indexDisplay, bold: false },
			})
		}

		// Convert to ordered array
		return extractItemGroup(groups)
	} else {
		// For alphabetical: list short titles with the long title to look under
		const indexItems: { shortTitle: string, longTitle: string }[] = []

		for (const item of flatItems) {
			if (item.shortTitle && item.shortTitle !== item.title) {
				indexItems.push({
					shortTitle: item.shortTitle,
					longTitle: item.title,
				})
			}
		}

		// Sort alphabetically by short title
		indexItems.sort((a, b) => a.shortTitle.localeCompare(b.shortTitle))

		// Group by first character
		const groups = new Map<string, Item[]>()
		for (const item of indexItems) {
			const groupLabel = getFirstCharGroup(item.shortTitle)
			if (!groups.has(groupLabel)) {
				groups.set(groupLabel, [])
			}
			groups.get(groupLabel)!.push({
				leftColumn: { text: item.shortTitle, bold: false },
				rightColumn: { text: item.longTitle, bold: false },
			})
		}

		// Convert to ordered array
		return extractItemGroup(groups)
	}
}

/**
 * Add header (title and subtitle) to a worksheet
 *
 * @param worksheet - The worksheet to add the header to
 * @param title - The collection title
 * @param subtitle - The version date range
 * @param config - Formatting configuration
 * @param colsToMerge - Total columns to merge across (for A4 layout)
 */
function addHeader(
	worksheet: Worksheet,
	title: string,
	subtitle: string,
	config: XlsxFormatConfig,
	colsToMerge: number = 3,
): void {
	// Add title row
	const titleRow = worksheet.addRow([title])
	titleRow.font = { size: config.titleFontSize, bold: config.titleBold }
	titleRow.alignment = { horizontal: 'center', vertical: 'middle' }
	const mergeCellsRange = `A1:${String.fromCharCode(64 + colsToMerge)}1`
	worksheet.mergeCells(mergeCellsRange)

	// Add subtitle row
	const subtitleRow = worksheet.addRow([subtitle])
	subtitleRow.font = { size: config.subtitleFontSize }
	subtitleRow.alignment = { horizontal: 'center', vertical: 'middle' }
	const subtitleMergeCellsRange = `A2:${String.fromCharCode(64 + colsToMerge)}2`
	worksheet.mergeCells(subtitleMergeCellsRange)

	// Add empty row
	worksheet.addRow([])
}

/**
 * Add content in A4 layout (two columns side by side)
 *
 * @param worksheet - The worksheet to add content to
 * @param splitData - The split layout data
 * @param config - Formatting configuration
 * @param isIndexedToc - Whether this is an indexed ToC
 */
function addA4LayoutContent(
	worksheet: Worksheet,
	splitData: SplitLayoutData,
	config: XlsxFormatConfig,
	isIndexedToc: boolean = false,
): void {
	const leftGroups = splitData.leftSection
	const rightGroups = splitData.rightSection

	// Collect all rows for both sides
	const leftRows: Array<{ cells: string[], isBold?: boolean[] }> = []
	const rightRows: Array<{ cells: string[], isBold?: boolean[] }> = []

	function collectRows(source: ItemGroup[], target: Array<{
		cells: string[]
		isBold?: boolean[]
	}>) {
		// Collect left rows
		for (let i = 0; i < source.length; i++) {
			const group = source[i]
			if (i > 0) {
				// Separator row
				target.push({ cells: [] })
			}
			for (let j = 0; j < group.items.length; j++) {
				const item = group.items[j]
				if (isIndexedToc) {
					target.push({
						cells: item.leftColumn && item.rightColumn ? [item.leftColumn.text, item.rightColumn.text] : [item.leftColumn.text],
						isBold: item.leftColumn && item.leftColumn.bold ? [true, false] : undefined,
					})
				} else {
					const groupLabel = j === 0 ? group.groupLabel || '' : ''
					target.push({
						cells: item.leftColumn && item.rightColumn ? [groupLabel, item.leftColumn.text, item.rightColumn.text] : [groupLabel, item.leftColumn.text],
						isBold: item.leftColumn && item.leftColumn.bold ? [true, false, false] : [true, false],
					})
				}
			}
		}
	}

	collectRows(leftGroups, leftRows)
	if (rightGroups !== null) {
		collectRows(rightGroups, rightRows)
	}

	// Merge rows to same height
	const maxRows = Math.max(leftRows.length, rightRows.length)

	// Determine column offsets
	const leftColCount = Math.max(leftRows.reduce((max, row) => Math.max(max, row.cells.length), 0), 1)
	const rightColCount = Math.max(rightRows.reduce((max, row) => Math.max(max, row.cells.length), 0), 1)
	const gapCol = leftColCount + 1
	const rightColStart = gapCol + 1

	for (let i = 0; i < maxRows; i++) {
		const rowData: Array<string | null> = new Array(rightColStart + rightColCount - 1)
		rowData.fill(null)

		// Add left section
		if (i < leftRows.length) {
			const leftRow = leftRows[i]
			for (let j = 0; j < leftRow.cells.length; j++) {
				rowData[j] = leftRow.cells[j] || ''
			}
		}

		// Add right section (offset by gap and right start column)
		if (i < rightRows.length) {
			const rightRow = rightRows[i]
			for (let j = 0; j < rightRow.cells.length; j++) {
				rowData[gapCol + j] = rightRow.cells[j] || ''
			}
		}

		const row = worksheet.addRow(rowData)
		row.font = { size: config.contentFontSize }

		// Apply bold formatting where needed
		if (i < leftRows.length && leftRows[i].isBold) {
			for (let j = 0; j < leftRows[i].isBold!.length; j++) {
				if (leftRows[i].isBold![j]) {
					row.getCell(j + 1).font = {
						bold: true,
						size: config.contentFontSize,
					}
				}
			}
		}

		if (i < rightRows.length && rightRows[i].isBold) {
			for (let j = 0; j < rightRows[i].isBold!.length; j++) {
				if (rightRows[i].isBold![j]) {
					const cellIndex = gapCol + j + 1
					row.getCell(cellIndex).font = {
						bold: true,
						size: config.contentFontSize,
					}
				}
			}
		}

		// Add vertical separator line at gap column
		if (rightGroups !== null) {
			const cell = row.getCell(gapCol)
			cell.border = {
				left: { style: 'thin' },
			}
		}
	}
}

/**
 * Configure worksheet columns for A4 layout
 *
 * @param worksheet - The worksheet to configure
 * @param config - Formatting configuration
 * @param kind - what kind of worksheet this is
 * @param twoColumnLayout - if the worksheet is using a two column layout
 * @param isIndexedToc - if the worksheet is an indexed table of contents
 */
function configureColumns(
	worksheet: Worksheet,
	config: XlsxFormatConfig,
	kind: 'toc' | 'index',
	twoColumnLayout: boolean,
	isIndexedToc: boolean,
): void {
	const colCountPerSection = (kind === 'toc') ? 2 : 3
	const columns: Array<{ width: number }> = []

	// Left Section
	if (kind === 'toc') {
		columns.push({ width: isIndexedToc ? config.columnWidths.indexColumn : config.columnWidths.groupColumn })
		columns.push({ width: config.columnWidths.contentColumn })
	} else {
		columns.push({ width: config.columnWidths.groupColumn })
		columns.push({ width: config.columnWidths.contentColumn })
		columns.push({ width: isIndexedToc ? config.columnWidths.indexColumn : config.columnWidths.contentColumn })
	}

	if (twoColumnLayout) {
		// Gap columns
		columns.push({ width: 2 })

		// Right section
		// Copy of the first part
		columns.push(...columns.slice(0, colCountPerSection))
	}

	worksheet.columns = columns
}

/**
 * Export a folder collection to an XLSX file with ToC and Index sheets.
 *
 * @param folderCollection - The folder collection to export
 * @param version - The selected folder collection version
 * @param entries - The collection entries (scores and scorebooks)
 * @param config - Optional formatting configuration
 */
export async function exportFolderCollectionToXlsx(
	folderCollection: FolderCollection,
	version: FolderCollectionVersion,
	entries: CollectionEntry[],
	config: XlsxFormatConfig = defaultFormatConfig,
): Promise<void> {
	const workbook = new Workbook()
	const isIndexed = folderCollection.collectionType === 'indexed'

	const title = folderCollection.title
	const subtitle = formatVersionDateRange(version)

	// Create Contents (ToC) sheet
	const contentsSheet = workbook.addWorksheet(t('Contents'))
	const tocData = createTocData(entries, isIndexed)
	const splitData = splitForA4Layout(tocData)
	const tocColsToMerge = splitData.rightSection ? 5 : 2

	configureColumns(contentsSheet, config, 'toc', splitData.rightSection !== null, isIndexed)
	addHeader(contentsSheet, title, subtitle, config, tocColsToMerge)
	addA4LayoutContent(contentsSheet, splitData, config, isIndexed)

	// Create Index sheet
	const indexSheet = workbook.addWorksheet(t('Index'))
	const indexData = createIndexData(entries, isIndexed)
	const splitIndexData = splitForA4Layout(indexData)
	const indexColsToMerge = splitIndexData.rightSection ? 7 : 3

	configureColumns(indexSheet, config, 'index', splitIndexData.rightSection !== null, isIndexed)
	addHeader(indexSheet, title, subtitle, config, indexColsToMerge)
	addA4LayoutContent(indexSheet, splitIndexData, config, false)

	// Generate and download the file
	const buffer = await workbook.xlsx.writeBuffer()
	const blob = new Blob([buffer], {
		type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	})
	const url = URL.createObjectURL(blob)

	const link = document.createElement('a')
	link.href = url
	// Sanitize filename: replace characters that are problematic in filenames
	// while preserving Unicode letters (including umlauts, accented chars, etc.)
	const sanitizedTitle = folderCollection.title.replace(/[<>:"/\\|?*]/g, '_')
	link.download = `${sanitizedTitle}_ToC_Index.xlsx`
	document.body.appendChild(link)
	link.click()
	document.body.removeChild(link)
	URL.revokeObjectURL(url)
}
