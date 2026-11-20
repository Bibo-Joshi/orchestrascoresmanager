import type { FolderCollection, Score, ScoreBook, Setlist, SetlistEntry } from '@/api/generated/openapi/data-contracts'
import type { ScoreInfoField } from '@/utils/setlistScoreUtils'

import { t } from '@/utils/l10n'
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
 * Format a date and time from an ISO datetime string.
 * Time is formatted as HH:MM using formatDurationHHMM.
 *
 * @param dateTimeStr - ISO 8601 datetime string
 * @return Object with formatted date, time string, and startOfDaySecs (elapsed seconds since midnight)
 */
export function formatDateTime(dateTimeStr: string): { date: string, time: string, startOfDaySecs: number } {
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
export function computeSummary(
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

export function prepareEntries(data: PdfSetlistData) {
	const { setlist, entries, folderCollection, getScoreById, getScoreBookById, fcvScoresMap, fcvScoreBookIndicesMap } = data
	const columns = data.columnConfigs ?? buildDefaultColumnConfigs(
		setlist.folderCollectionVersionId !== null,
		folderCollection?.collectionType === 'indexed',
	)
	let cumulativeTime = setlist.startDateTime ? formatDateTime(setlist.startDateTime).startOfDaySecs : 0
	const rows = entries.map((entry) => {
		const score = entry.scoreId === null ? undefined : getScoreById(entry.scoreId)
		const duration = entry.breakDuration ?? score?.duration ?? 0
		const moderation = entry.moderationDuration ?? setlist.defaultModerationDuration ?? 0
		const startTime = cumulativeTime
		cumulativeTime += duration + moderation
		return {
			cells: columns.map(({ id }) => getEntryCellValue(id, entry, startTime, duration, moderation, score, getScoreBookById, fcvScoresMap, fcvScoreBookIndicesMap)),
			isBreak: isBreakEntry(entry),
			facts: { ...entry, tags: score?.tags ?? [] },
		}
	})
	return { columns, rows }
}
