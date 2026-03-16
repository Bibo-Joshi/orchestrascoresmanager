/**
 * Shared utilities for resolving score-information display values from setlist entries.
 *
 * Used by both SetlistEntriesTable (AG Grid value getters) and pdf-exporter (PDF cell values)
 * to avoid duplication of the score-field resolution logic.
 */
import { t } from '@/utils/l10n'
import type { SetlistEntry, Score, ScoreBook } from '@/api/generated/openapi/data-contracts'

/**
 * Score-information fields that can be resolved from a setlist entry.
 * Matches the colId values used in SetlistEntriesTable and the PdfColumnId entries in pdf-exporter.
 */
export type ScoreInfoField = 'title' | 'difficulty' | 'bookName' | 'bookIndex' | 'fcvIndex' | 'gemaIds'

/**
 * Check if a setlist entry is a break entry.
 *
 * @param entry - The setlist entry
 * @return True if the entry is a break entry
 */
export function isBreakEntry(entry: SetlistEntry): boolean {
	return entry.breakDuration !== null && entry.scoreId === null
}

/**
 * Resolve the display value for a score-information field of a setlist entry.
 *
 * Returns the raw typed value (string, number, string[], or null).
 * Break entries return the translated "Break" label for the `title` field and null for all others.
 *
 * @param field - The score-info field to resolve
 * @param entry - The setlist entry
 * @param score - Resolved score for this entry, or null/undefined for breaks/unresolved entries
 * @param getScoreBookById - Function to look up score books by ID
 * @param fcvScoresMap - Map of score ID to FCV index (direct scores only)
 * @param fcvScoreBookIndicesMap - Map of score book ID to FCV index
 * @return Typed cell value, or null for empty/not-applicable cases
 */
export function resolveScoreField(
	field: ScoreInfoField,
	entry: SetlistEntry,
	score: Score | null | undefined,
	getScoreBookById: (id: number) => ScoreBook | undefined,
	fcvScoresMap?: Map<number, number>,
	fcvScoreBookIndicesMap?: Map<number, number>,
): string | number | string[] | null {
	if (isBreakEntry(entry)) {
		return field === 'title' ? t('Break') : null
	}

	if (!score) return null

	switch (field) {
	case 'title':
		return score.title
	case 'difficulty':
		return score.difficulty ?? null
	case 'bookName':
		if (score.scoreBook?.id) {
			return getScoreBookById(score.scoreBook.id)?.title ?? null
		}
		return null
	case 'bookIndex':
		return score.scoreBook?.index ?? null
	case 'gemaIds':
		return score.gemaIds ?? null
	case 'fcvIndex': {
		if (fcvScoresMap && fcvScoreBookIndicesMap) {
			const directIndex = fcvScoresMap.get(score.id)
			if (directIndex !== undefined) return directIndex
			if (score.scoreBook?.id) {
				const bookIndex = fcvScoreBookIndicesMap.get(score.scoreBook.id)
				const bookItemIndex = score.scoreBook.index
				if (bookIndex !== undefined && bookItemIndex !== null && bookItemIndex !== undefined) {
					return `${bookIndex}.${bookItemIndex}`
				}
			}
		}
		return null
	}
	default:
		return null
	}
}
