import type { FolderCollection, Score, ScoreBook, Setlist, SetlistEntry } from '@/api/generated/openapi/data-contracts'
import type { ScoreInfoField } from '@/utils/setlistScoreUtils'

import { t } from '@/utils/l10n'
import { resolveScoreField } from '@/utils/setlistScoreUtils'

/** Identifier of a column in the share image table */
export type ShareColumnId = 'fcvIndex' | 'title' | 'bookName' | 'bookIndex' | 'comment'

/** A column of the share image table */
export interface ShareColumn {
	id: ShareColumnId
	label: string
}

/** Normalized data used to render the share image and to build the share text */
export interface SetlistShareModel {
	title: string
	subtitleLines: string[]
	isDraft: boolean
	columns: ShareColumn[]
	rows: string[][]
	shareText: string
}

/** Input required to build a {@link SetlistShareModel} */
export interface SetlistShareData {
	setlist: Setlist
	entries: SetlistEntry[]
	getScoreById: (id: number) => Score | undefined
	getScoreBookById: (id: number) => ScoreBook | undefined
	fcvScoresMap: Map<number, number>
	fcvScoreBookIndicesMap: Map<number, number>
	folderCollection: FolderCollection | null
	url: string
	sharedAt?: Date
}

/**
 * Format a date for display in the share subtitle and share text.
 *
 * @param date - The date to format
 * @return Localized date string
 */
function formatDate(date: Date): string {
	return date.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' })
}

/**
 * Format a date with hour and minute for display in the share subtitle and share text.
 *
 * @param date - The date to format
 * @return Localized date and time string
 */
function formatDateTime(date: Date): string {
	return date.toLocaleString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

/**
 * Convert a resolved score-field value to a display string.
 *
 * @param value - Resolved value
 * @return Display string, empty for null values
 */
function toText(value: string | number | string[] | null): string {
	if (value === null) {
		return ''
	}
	return Array.isArray(value) ? value.join(', ') : String(value)
}

/**
 * Build the normalized share model for a setlist.
 *
 * @param data - Setlist, entries, lookup functions and context
 * @return Model consumed by the share image component and share text
 */
export function buildSetlistShareModel(data: SetlistShareData): SetlistShareModel {
	const { setlist, entries, getScoreById, getScoreBookById, fcvScoresMap, fcvScoreBookIndicesMap, folderCollection } = data

	const hasFolderCollectionVersion = setlist.folderCollectionVersionId !== null
	const isIndexedCollection = hasFolderCollectionVersion && folderCollection?.collectionType === 'indexed'
	const scores = entries.map((entry) => (entry.scoreId !== null ? getScoreById(entry.scoreId) : undefined))
	const hasAnyScoreBook = scores.some((score) => !!score?.scoreBook?.id)
	const showScoreBook = !hasFolderCollectionVersion && hasAnyScoreBook
	const hasAnyComment = entries.some((entry) => entry.comment && entry.comment.trim() !== '')

	const columns: ShareColumn[] = []
	if (isIndexedCollection) {
		columns.push({ id: 'fcvIndex', label: t('Index') })
	}
	columns.push({ id: 'title', label: t('Title') })
	if (showScoreBook) {
		columns.push({ id: 'bookName', label: t('Score Book') })
		columns.push({ id: 'bookIndex', label: t('Book Index') })
	}
	if (hasAnyComment) {
		columns.push({ id: 'comment', label: t('Comment') })
	}

	const rows = entries.map((entry, i) => columns.map((col) => {
		if (col.id === 'comment') {
			return entry.comment ?? ''
		}
		return toText(resolveScoreField(col.id as ScoreInfoField, entry, scores[i], getScoreBookById, fcvScoresMap, fcvScoreBookIndicesMap))
	}))

	const sharedOn = `${t('Shared on')} ${formatDateTime(data.sharedAt ?? new Date())}`
	const date = setlist.startDateTime ? formatDate(new Date(setlist.startDateTime)) : null
	const separator = ' · '

	const subtitleLines = [
		[date, folderCollection ? folderCollection.title : null].filter((part): part is string => part !== null).join(separator),
		sharedOn,
	].filter((line) => line !== '')

	const headline = `${t('Setlist')}: ${setlist.title}${date ? ` (${date})` : ''}`
	const statusLine = [setlist.isDraft ? t('Draft') : null, sharedOn].filter((part): part is string => part !== null).join(separator)
	const shareText = [headline, statusLine, '', `${t('View online')}: ${data.url}`].join('\n')

	return {
		title: setlist.title,
		subtitleLines,
		isDraft: setlist.isDraft,
		columns,
		rows,
		shareText,
	}
}
