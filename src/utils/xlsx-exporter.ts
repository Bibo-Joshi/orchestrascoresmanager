/**
 * XLSX exporter for folder collection Table of Contents and Index export.
 * Generates an XLSX file with two sheets: "Contents" (ToC) and "Index".
 *
 * This module is designed to be easily adaptable for formatting changes.
 */
import { Workbook, type Worksheet } from 'exceljs'
import { t } from '@/utils/l10n'
import type {
	FolderCollection,
	FolderCollectionVersion,
	Score,
	ScoreIndexed,
	ScoreBook,
	ScoreBookIndexed,
} from '@/api/generated/openapi/data-contracts'

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
 * Represents a grouped item in the ToC or Index
 */
interface GroupedItem {
	groupLabel: string
	items: { leftColumn: string; rightColumn: string }[]
}

/**
 * Get the first character of a string for grouping purposes.
 * Non-Latin characters are grouped together.
 *
 * @param text - The text to get the first character from
 * @return The uppercase first character or empty string for non-Latin
 */
function getFirstCharGroup(text: string): string {
	if (!text) return ''
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
		return '00'
	}
	return `${tens}`
}

/**
 * Flatten all entries into a list of scores with their titles and indices.
 * For score books, include all scores with composite indices (e.g., "12.3").
 *
 * @param entries - Collection entries
 * @param isIndexed - Whether the collection is indexed
 * @return Flat list of items with title, shortTitle, and index
 */
function flattenEntries(
	entries: CollectionEntry[],
	isIndexed: boolean,
): { title: string; shortTitle: string | null; index?: number; indexDisplay?: string }[] {
	const result: { title: string; shortTitle: string | null; index?: number; indexDisplay?: string }[] = []

	for (const entry of entries) {
		if (entry.type === 'scorebook' && entry.scoreBook && entry.scores) {
			// Score book: include all scores with composite index
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
): GroupedItem[] {
	const flatItems = flattenEntries(entries, isIndexed)

	if (isIndexed) {
		// Sort by index for indexed collections
		flatItems.sort((a, b) => {
			if (a.index === undefined) return 1
			if (b.index === undefined) return -1
			return a.index - b.index
		})

		// Group by tens
		const groups = new Map<string, { leftColumn: string; rightColumn: string }[]>()
		for (const item of flatItems) {
			if (item.index === undefined) continue
			const groupLabel = getIndexGroup(item.index)
			if (!groups.has(groupLabel)) {
				groups.set(groupLabel, [])
			}
			groups.get(groupLabel)!.push({
				leftColumn: item.title,
				rightColumn: item.indexDisplay ?? String(item.index),
			})
		}

		// Convert to ordered array
		const result: GroupedItem[] = []
		const sortedKeys = Array.from(groups.keys()).sort((a, b) => {
			const aNum = parseInt(a, 10)
			const bNum = parseInt(b, 10)
			return aNum - bNum
		})
		for (const key of sortedKeys) {
			result.push({ groupLabel: key, items: groups.get(key)! })
		}
		return result
	} else {
		// Sort alphabetically for alphabetical collections
		flatItems.sort((a, b) => a.title.localeCompare(b.title))

		// Group by first character
		const groups = new Map<string, { leftColumn: string; rightColumn: string }[]>()
		for (const item of flatItems) {
			const groupLabel = getFirstCharGroup(item.title)
			if (!groups.has(groupLabel)) {
				groups.set(groupLabel, [])
			}
			groups.get(groupLabel)!.push({
				leftColumn: item.title,
				rightColumn: '', // No right column for alphabetical ToC
			})
		}

		// Convert to ordered array (empty string first for non-Latin, then A-Z)
		const result: GroupedItem[] = []
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
): GroupedItem[] {
	const flatItems = flattenEntries(entries, isIndexed)

	if (isIndexed) {
		// For indexed: list both title and short title with the index
		const indexItems: { text: string; index: number; indexDisplay: string }[] = []

		for (const item of flatItems) {
			if (item.index === undefined || !item.indexDisplay) continue

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
		const groups = new Map<string, { leftColumn: string; rightColumn: string }[]>()
		for (const item of indexItems) {
			const groupLabel = getFirstCharGroup(item.text)
			if (!groups.has(groupLabel)) {
				groups.set(groupLabel, [])
			}
			groups.get(groupLabel)!.push({
				leftColumn: item.text,
				rightColumn: item.indexDisplay,
			})
		}

		// Convert to ordered array
		const result: GroupedItem[] = []
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
	} else {
		// For alphabetical: list short titles with the long title to look under
		const indexItems: { shortTitle: string; longTitle: string }[] = []

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
		const groups = new Map<string, { leftColumn: string; rightColumn: string }[]>()
		for (const item of indexItems) {
			const groupLabel = getFirstCharGroup(item.shortTitle)
			if (!groups.has(groupLabel)) {
				groups.set(groupLabel, [])
			}
			groups.get(groupLabel)!.push({
				leftColumn: item.shortTitle,
				rightColumn: item.longTitle,
			})
		}

		// Convert to ordered array
		const result: GroupedItem[] = []
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
}

/**
 * Add header (title and subtitle) to a worksheet
 *
 * @param worksheet - The worksheet to add the header to
 * @param title - The collection title
 * @param subtitle - The version date range
 * @param config - Formatting configuration
 */
function addHeader(
	worksheet: Worksheet,
	title: string,
	subtitle: string,
	config: XlsxFormatConfig,
): void {
	// Add title row
	const titleRow = worksheet.addRow([title])
	titleRow.font = { size: config.titleFontSize, bold: config.titleBold }
	worksheet.mergeCells('A1:C1')

	// Add subtitle row
	const subtitleRow = worksheet.addRow([subtitle])
	subtitleRow.font = { size: config.subtitleFontSize }
	worksheet.mergeCells('A2:C2')

	// Add empty row
	worksheet.addRow([])
}

/**
 * Add grouped content to a worksheet
 *
 * @param worksheet - The worksheet to add content to
 * @param groups - The grouped items
 * @param config - Formatting configuration
 */
function addGroupedContent(
	worksheet: Worksheet,
	groups: GroupedItem[],
	config: XlsxFormatConfig,
): void {
	for (let i = 0; i < groups.length; i++) {
		const group = groups[i]

		// Add group separator (empty row) if not first group
		if (i > 0) {
			worksheet.addRow([])
		}

		// Add items in this group
		for (let j = 0; j < group.items.length; j++) {
			const item = group.items[j]
			// First item in group shows the group label
			const groupLabel = j === 0 ? group.groupLabel : ''
			const row = worksheet.addRow([groupLabel, item.leftColumn, item.rightColumn])
			row.font = { size: config.contentFontSize }
		}
	}
}

/**
 * Configure worksheet columns
 *
 * @param worksheet - The worksheet to configure
 * @param config - Formatting configuration
 */
function configureColumns(worksheet: Worksheet, config: XlsxFormatConfig): void {
	worksheet.columns = [
		{ width: config.columnWidths.groupColumn },
		{ width: config.columnWidths.contentColumn },
		{ width: config.columnWidths.indexColumn },
	]
}

/**
 * Format a version date range for display in the subtitle
 *
 * @param version - The folder collection version
 * @return Formatted date range string
 */
function formatVersionDateRange(version: FolderCollectionVersion): string {
	const formatDate = (dateStr: string): string => {
		const [year, month, day] = dateStr.split('-').map(Number)
		const date = new Date(year, month - 1, day)
		return date.toLocaleDateString()
	}

	if (version.validTo === null) {
		return `${formatDate(version.validFrom)} - ${t('Present')}`
	}
	return `${formatDate(version.validFrom)} - ${formatDate(version.validTo)}`
}

/**
 * Export a folder collection to an XLSX file with ToC and Index sheets.
 *
 * @param folderCollection - The folder collection to export
 * @param version - The selected folder collection version
 * @param entries - The collection entries (scores and score books)
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
	configureColumns(contentsSheet, config)
	addHeader(contentsSheet, title, subtitle, config)
	const tocData = createTocData(entries, isIndexed)
	addGroupedContent(contentsSheet, tocData, config)

	// Create Index sheet
	const indexSheet = workbook.addWorksheet(t('Index'))
	configureColumns(indexSheet, config)
	addHeader(indexSheet, title, subtitle, config)
	const indexData = createIndexData(entries, isIndexed)
	addGroupedContent(indexSheet, indexData, config)

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
