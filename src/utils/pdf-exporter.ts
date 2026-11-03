import type { Styles } from 'jspdf-autotable'
import type { FolderCollection, Score, ScoreBook, Setlist, SetlistEntry } from '@/api/generated/openapi/data-contracts'
import type { NtsoTagColorRule } from '@/utils/ntsoPdfSettings'
import type { ScoreInfoField } from '@/utils/setlistScoreUtils'

/**
 * PDF exporter for setlist export.
 * Generates an A4 portrait PDF with setlist metadata and entries table.
 *
 * This module is designed to be easily adaptable for formatting changes.
 */
import { jsPDF } from 'jspdf'
import { autoTable } from 'jspdf-autotable'
import { t } from '@/utils/l10n'
import { ntsoHexToRgb, ntsoMatchesTags } from '@/utils/ntsoPdfSettings'
import { isBreakEntry, resolveScoreField } from '@/utils/setlistScoreUtils'
import { formatDurationHHMM, formatDurationHHMMSS } from '@/utils/timeFormatUtils'

/**
 * Identifies a column in the entries table by a stable string key.
 */
export type PdfColumnId
	= | 'startTime'
		| 'endTime'
		| 'moderation'
		| 'duration'
		| 'fcvIndex'
		| 'title'
		| 'difficulty'
		| 'bookName'
		| 'bookIndex'
		| 'comment'
		| 'gemaIds'

/**
 * Describes a single column in the PDF entries table.
 */
export interface PdfColumnConfig {
	/** Stable identifier for this column */
	id: PdfColumnId
	/** Translated display label used as the column header */
	label: string
}

/**
 * Input data for a single setlist entry with resolved lookup functions
 */
export interface PdfSetlistData {
	setlist: Setlist
	entries: SetlistEntry[]
	getScoreById: (id: number) => Score | undefined
	getScoreBookById: (id: number) => ScoreBook | undefined
	/**
	 * Map of score ID to FCV index (only for direct scores, not via score books)
	 */
	fcvScoresMap: Map<number, number>
	/**
	 * Map of score book ID to FCV index
	 */
	fcvScoreBookIndicesMap: Map<number, number>
	/**
	 * Folder collection (if setlist has a FCV)
	 */
	folderCollection: FolderCollection | null
	/**
	 * Column selection and order for the entries table.
	 * When provided, only the listed columns are included, in the given order.
	 * When omitted, a default column set is used based on the setlist configuration.
	 */
	columnConfigs?: PdfColumnConfig[]
}

/**
 * Configuration for PDF formatting. Adjust these values to customize the output.
 */
export interface PdfFormatConfig {
	/** Font size for the main title */
	titleFontSize: number
	/** Font size for section text */
	sectionFontSize: number
	/** Font size for table content */
	tableFontSize: number
	/** Page margins in mm */
	margin: number
	cellPadding?: number
	breakPadding?: number
	breakColor?: string
	rules?: NtsoTagColorRule[]
}

/**
 * Default formatting configuration
 */
export const defaultPdfFormatConfig: PdfFormatConfig = {
	titleFontSize: 16,
	sectionFontSize: 14,
	tableFontSize: 12,
	margin: 8,
}

/**
 * NTSO stage layout: tag-to-colour mapping.
 * The Score API already includes tags as names, so no new backend API is needed.
 * Change the aliases below if your existing tags are named differently.
 * Priority: duet (also male + female) > instrumental > female > male.
 */
const NTSO_TAG_ALIASES = {
	duet: ['duett', 'duet'],
	instrumental: ['instrumental', 'instrumentalstück', 'instrumentalstueck', 'instr.'],
	female: ['female', 'weiblich'],
	male: ['male', 'männlich', 'maennlich'],
} as const

const NTSO_ROW_COLORS = {
	duet: [255, 224, 178] as [number, number, number], // pastel orange
	instrumental: [224, 242, 217] as [number, number, number], // light green
	female: [255, 220, 235] as [number, number, number], // pink
	male: [220, 238, 255] as [number, number, number], // light blue
} as const

function ntsoScoreRowColor(score: Score | undefined, config: PdfFormatConfig): [number, number, number] | null {
	if (config.rules) {
		const rule = config.rules.find((r) => ntsoMatchesTags(score?.tags ?? [], r.expression))
		return rule ? ntsoHexToRgb(rule.color) : null
	}
	const names = new Set((score?.tags ?? []).map((name) => name.trim().toLocaleLowerCase()))
	const has = (aliases: readonly string[]) => aliases.some((alias) => names.has(alias))
	const male = has(NTSO_TAG_ALIASES.male)
	const female = has(NTSO_TAG_ALIASES.female)
	if (has(NTSO_TAG_ALIASES.duet) || (male && female)) {
		return NTSO_ROW_COLORS.duet
	}
	if (has(NTSO_TAG_ALIASES.instrumental)) {
		return NTSO_ROW_COLORS.instrumental
	}
	if (female) {
		return NTSO_ROW_COLORS.female
	}
	if (male) {
		return NTSO_ROW_COLORS.male
	}
	return null
}

/**
 * Format seconds as HH:MM with a trailing "h" unit label, or empty string for null/undefined.
 * Used for duration values in the info table to make the unit explicit.
 *
 * @param seconds - Duration in seconds or null
 * @return Formatted duration string with unit suffix, or empty string
 */
function formatDurationWithUnit(seconds: number | null | undefined): string {
	if (seconds === null || seconds === undefined) {
		return ''
	}
	return `${formatDurationHHMM(seconds)} ${t('h')}`
}

/**
 * Format a date and time from an ISO datetime string.
 * Time is formatted as HH:MM using formatDurationHHMM.
 *
 * @param dateTimeStr - ISO 8601 datetime string
 * @return Object with formatted date, time string, and startOfDaySecs (elapsed seconds since midnight)
 */
function formatDateTime(dateTimeStr: string): { date: string, time: string, startOfDaySecs: number } {
	const date = new Date(dateTimeStr)
	// Elapsed seconds since midnight, used to compute end time and format start time
	const startOfDaySecs = date.getHours() * 3600 + date.getMinutes() * 60 + date.getSeconds()
	return {
		date: date.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' }),
		time: formatDurationHHMM(startOfDaySecs),
		startOfDaySecs,
	}
}

/**
 * Compute summary statistics for the setlist
 *
 * @param setlist - The setlist
 * @param entries - Sorted list of setlist entries
 * @param getScoreById - Function to look up scores by ID
 * @return Summary statistics object
 */
function computeSummary(
	setlist: Setlist,
	entries: SetlistEntry[],
	getScoreById: (id: number) => Score | undefined,
): {
	playTimeSecs: number
	moderationAndBreakTimeSecs: number
	totalDurationSecs: number
} {
	let playTimeSecs = 0
	let moderationAndBreakTimeSecs = 0

	for (const entry of entries) {
		const effectiveMod = entry.moderationDuration ?? setlist.defaultModerationDuration ?? 0
		moderationAndBreakTimeSecs += effectiveMod

		if (isBreakEntry(entry)) {
			moderationAndBreakTimeSecs += entry.breakDuration ?? 0
		} else if (entry.scoreId !== null) {
			const score = getScoreById(entry.scoreId)
			playTimeSecs += score?.duration ?? 0
		}
	}

	return {
		playTimeSecs,
		moderationAndBreakTimeSecs,
		totalDurationSecs: playTimeSecs + moderationAndBreakTimeSecs,
	}
}

/**
 * Build column styles for the entries table.
 * Computes the minimum column width for each header based on its rendered text width,
 * making the layout translation-aware.
 *
 * @param doc - The jsPDF document (used for text measurement)
 * @param config - PDF formatting configuration
 * @param headers - The list of column header strings
 * @return Column styles record keyed by zero-based column index
 */
function buildEntryColumnStyles(
	doc: jsPDF,
	config: PdfFormatConfig,
	headers: string[],
): Record<number, Partial<Styles>> {
	doc.setFontSize(config.tableFontSize)
	const cellPadding = 3 // 1.5 mm per side
	const cols: Record<number, Partial<Styles>> = {}
	headers.forEach((header, idx) => {
		cols[idx] = { minCellWidth: doc.getTextWidth(header) + cellPadding }
	})
	return cols
}

/**
 * Build the default column configuration based on setlist properties.
 * Mirrors the columns currently visible in the SetlistEntriesTable.
 *
 * @param hasFolderCollectionVersion - Whether the setlist is linked to a folder collection version
 * @param isIndexedCollection - Whether the linked folder collection uses indexed ordering
 * @return Ordered list of column configs to include in the PDF
 */
function buildDefaultColumnConfigs(
	hasFolderCollectionVersion: boolean,
	isIndexedCollection: boolean,
): PdfColumnConfig[] {
	const cols: PdfColumnConfig[] = [
		{ id: 'startTime', label: t('Start Time') },
		{ id: 'duration', label: t('Duration') },
	]
	if (hasFolderCollectionVersion && isIndexedCollection) {
		cols.push({ id: 'fcvIndex', label: t('Index') })
	}
	cols.push({ id: 'title', label: t('Title') })
	if (!hasFolderCollectionVersion) {
		cols.push({ id: 'bookName', label: t('Score Book') })
		cols.push({ id: 'bookIndex', label: t('Book Index') })
	}
	cols.push({ id: 'comment', label: t('Comment') })
	return cols
}

/**
 * Score-info fields handled by the shared resolveScoreField helper.
 * These are forwarded to the shared utility; all other PdfColumnId values
 * are time-based or entry-level and are handled locally.
 */
const SCORE_INFO_FIELDS = new Set<PdfColumnId>(['title', 'difficulty', 'fcvIndex', 'bookName', 'bookIndex', 'gemaIds'])

/**
 * Return the string cell value for a given column and entry.
 *
 * @param columnId - Column identifier
 * @param entry - Setlist entry
 * @param startTime - Cumulative start time in seconds
 * @param effectiveDuration - Effective play/break duration in seconds
 * @param effectiveMod - Effective moderation duration in seconds
 * @param score - Resolved score for this entry, or undefined for breaks
 * @param getScoreBookById - Function to look up score books by ID
 * @param fcvScoresMap - Map of score ID to FCV index
 * @param fcvScoreBookIndicesMap - Map of score book ID to FCV index
 * @return Cell value as a string
 */
function getEntryCellValue(
	columnId: PdfColumnId,
	entry: SetlistEntry,
	startTime: number,
	effectiveDuration: number,
	effectiveMod: number,
	score: Score | undefined,
	getScoreBookById: (id: number) => ScoreBook | undefined,
	fcvScoresMap: Map<number, number>,
	fcvScoreBookIndicesMap: Map<number, number>,
): string {
	// Delegate score-information fields to the shared utility
	if (SCORE_INFO_FIELDS.has(columnId)) {
		const raw = resolveScoreField(
			columnId as ScoreInfoField,
			entry,
			score ?? null,
			getScoreBookById,
			fcvScoresMap,
			fcvScoreBookIndicesMap,
		)
		if (raw === null || raw === undefined) {
			return ''
		}
		if (Array.isArray(raw)) {
			return raw.join(', ')
		}
		return String(raw)
	}

	switch (columnId) {
		case 'startTime':
			return formatDurationHHMM(startTime)
		case 'endTime':
			return formatDurationHHMM(startTime + effectiveMod + effectiveDuration)
		case 'moderation':
			return effectiveMod > 0 ? formatDurationHHMMSS(effectiveMod) : '-'
		case 'duration':
			return effectiveDuration > 0 ? formatDurationHHMMSS(effectiveDuration) : '-'
		case 'comment':
			return entry.comment ?? ''
		default:
			return ''
	}
}

/**
 * Export a setlist to a PDF file.
 *
 * @param data - Setlist data including entries and lookup functions
 * @param config - Optional formatting configuration
 */
export function exportSetlistToPdf(
	data: PdfSetlistData,
	config: PdfFormatConfig = defaultPdfFormatConfig,
): void {
	const { setlist, entries, getScoreById, getScoreBookById, fcvScoresMap, fcvScoreBookIndicesMap, folderCollection } = data

	const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
	const pageWidth = doc.internal.pageSize.getWidth()
	const margin = config.margin
	const contentWidth = pageWidth - 2 * margin

	// --- Compact NTSO stage header (no large details/summary table) ---
	let y = margin + 5
	const draftSuffix = setlist.isDraft ? ` (${t('Draft')})` : ''
	const headerTitle = `${setlist.title}${draftSuffix}`
	doc.setFont('helvetica', 'bold')
	doc.setFontSize(config.titleFontSize)
	const titleLines = doc.splitTextToSize(headerTitle, contentWidth) as string[]
	doc.text(titleLines, margin, y)
	y += titleLines.length * (config.titleFontSize * 0.4 + 1.2) + 1

	const summary = computeSummary(setlist, entries, getScoreById)
	const headerDetails: string[] = []
	if (setlist.startDateTime) {
		const { date, time } = formatDateTime(setlist.startDateTime)
		headerDetails.push(`${date} · ${time}`)
	}
	headerDetails.push(`${t('Overall Duration')}: ${formatDurationWithUnit(summary.totalDurationSecs)}`)
	doc.setFont('helvetica', 'normal')
	doc.setFontSize(config.tableFontSize + 1)
	const detailLines = doc.splitTextToSize(headerDetails.join('  |  '), contentWidth) as string[]
	doc.text(detailLines, margin, y)
	y += detailLines.length * (config.tableFontSize * 0.4 + 1.5) + 3

	// One-line legend. Coloured backgrounds are derived from score tags, not setlist comments.
	const legend: Array<{ label: string, color: [number, number, number] }> = config.rules
		? config.rules.map((rule) => ({ label: rule.label, color: ntsoHexToRgb(rule.color) }))
		: [
				{ label: 'Male', color: NTSO_ROW_COLORS.male },
				{ label: 'Female', color: NTSO_ROW_COLORS.female },
				{ label: 'Duett', color: NTSO_ROW_COLORS.duet },
				{ label: 'Instrumental', color: NTSO_ROW_COLORS.instrumental },
			]
	let x = margin
	doc.setFontSize(8)
	for (const item of legend) {
		const itemWidth = doc.getTextWidth(item.label) + 13
		if (x + itemWidth > pageWidth - margin && x > margin) {
			x = margin
			y += 6
		}
		doc.setFillColor(...item.color)
		doc.rect(x, y - 2.5, 3.5, 3.5, 'F')
		doc.setTextColor(0, 0, 0)
		doc.text(item.label, x + 5, y + 0.2)
		x += itemWidth
	}
	y += 6
	// --- Entries table ---
	const hasFolderCollectionVersion = setlist.folderCollectionVersionId !== null
	const isIndexedCollection = folderCollection?.collectionType === 'indexed'

	const activeColumns = data.columnConfigs
		?? buildDefaultColumnConfigs(hasFolderCollectionVersion, isIndexedCollection)

	const entryHeaders = activeColumns.map((col) => col.label)

	// Compute cumulative start times
	let cumulativeTime = 0
	if (setlist.startDateTime) {
		const d = new Date(setlist.startDateTime)
		cumulativeTime = d.getHours() * 3600 + d.getMinutes() * 60 + d.getSeconds()
	}

	const entryRows: string[][] = []
	const ntsoRowColors: Array<[number, number, number] | null> = []
	const ntsoBreakRows = new Set<number>()

	for (const entry of entries) {
		const effectiveMod = entry.moderationDuration ?? setlist.defaultModerationDuration ?? 0
		const score = entry.scoreId !== null ? getScoreById(entry.scoreId) : undefined
		const effectiveDuration = entry.breakDuration ?? score?.duration ?? 0
		const startTime = cumulativeTime
		cumulativeTime += effectiveMod + effectiveDuration

		if (isBreakEntry(entry)) {
			ntsoBreakRows.add(entryRows.length)
			ntsoRowColors.push(null)
		} else {
			ntsoRowColors.push(ntsoScoreRowColor(score, config))
		}
		entryRows.push(activeColumns.map((col) => getEntryCellValue(col.id, entry, startTime, effectiveDuration, effectiveMod, score, getScoreBookById, fcvScoresMap, fcvScoreBookIndicesMap)))
	}

	autoTable(doc, {
		startY: y,
		head: [entryHeaders],
		body: entryRows,
		theme: 'plain',
		margin: { left: margin, right: margin },
		tableWidth: contentWidth,
		styles: { fontSize: config.tableFontSize, overflow: 'linebreak', lineWidth: 0, ...(config.cellPadding !== undefined ? { cellPadding: config.cellPadding } : {}) },
		headStyles: {
			fontStyle: 'bold',
			fillColor: false,
			textColor: [0, 0, 0],
			lineColor: [0, 0, 0],
			lineWidth: { bottom: 0.8, top: 0, left: 0, right: 0 },
		},
		columnStyles: buildEntryColumnStyles(doc, config, entryHeaders),
		didParseCell: (cellData) => {
			if (cellData.section !== 'body') {
				return
			}
			if (ntsoBreakRows.has(cellData.row.index)) {
				// Dark, high-contrast band spanning the entire table row.
				cellData.cell.styles.fillColor = ntsoHexToRgb(config.breakColor ?? '#354354')
				cellData.cell.styles.textColor = [255, 255, 255]
				cellData.cell.styles.fontStyle = 'bold'
				cellData.cell.styles.minCellHeight = 10
				cellData.cell.styles.cellPadding = {
					top: config.breakPadding ?? 3,
					right: 2,
					bottom: config.breakPadding ?? 3,
					left: 2,
				}
				if (activeColumns[cellData.column.index]?.id === 'title') {
					cellData.cell.text = [t('Break').toLocaleUpperCase()]
				}
				return
			}
			const background = ntsoRowColors[cellData.row.index]
			if (background) {
				cellData.cell.styles.fillColor = background
			}
		},
	})

	// Download the PDF
	const sanitizedTitle = setlist.title.replace(/[<>:"/\\|?*]/g, '_')
	doc.save(`${sanitizedTitle}_Setlist.pdf`)
}
