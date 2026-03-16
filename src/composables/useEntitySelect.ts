/**
 * Composable for entity selection with title-based filtering.
 * Provides consistent filtering logic for scores and score books in dropdowns.
 */
import type { Score, ScoreBook } from '@/api/generated/openapi/data-contracts'

export interface EntityOption<T> {
	label: string
	value: number
	entity: T
}

/**
 * Format a label for display in dropdown combining title and short title
 * @param title - Main title
 * @param titleShort - Optional short title
 * @return Formatted label string
 */
export function formatEntityLabel(title: string, titleShort?: string | null): string {
	if (titleShort) {
		return `${title} (${titleShort})`
	}
	return title
}

/**
 * Create score options for NcSelect dropdown
 * @param scores - Array of scores
 * @param excludeInBook - Whether to exclude scores already in a book
 * @param excludeScoreBooksIds - Set of score book IDs to exclude scores from
 * @return Array of score options for NcSelect
 */
export function createScoreOptions(
	scores: Score[],
	excludeInBook: boolean = false,
	excludeScoreBooksIds?: Set<number>,
): EntityOption<Score>[] {
	return scores
		.filter(score => {
			// If excludeScoreBooksIds is provided, exclude scores belonging to those score books
			if (excludeScoreBooksIds && score.scoreBook !== null) {
				return !excludeScoreBooksIds.has(score.scoreBook.id)
			}
			// Otherwise use the old logic
			return !excludeInBook || score.scoreBook === null
		})
		.map(score => ({
			label: formatEntityLabel(score.title, score.titleShort),
			value: score.id,
			entity: score,
		}))
}

/**
 * Create score book options for NcSelect dropdown
 * @param scoreBooks - Array of score books
 * @return Array of score book options for NcSelect
 */
export function createScoreBookOptions(
	scoreBooks: ScoreBook[],
): EntityOption<ScoreBook>[] {
	return scoreBooks.map(scoreBook => ({
		label: formatEntityLabel(scoreBook.title, scoreBook.titleShort),
		value: scoreBook.id,
		entity: scoreBook,
	}))
}

/**
 * Filter function for scores by title and short title
 * Works with NcSelect's filter-by prop
 * @param option - The score option to filter
 * @param _label - The label of the option (unused, required by NcSelect API)
 * @param search - The search string
 * @return Whether the option matches the search
 */
export function filterScores(option: EntityOption<Score>, _label: string, search: string): boolean {
	const searchLower = search.toLowerCase()
	const score = option.entity
	return (
		score.title.toLowerCase().includes(searchLower)
		|| (score.titleShort?.toLowerCase().includes(searchLower) ?? false)
	)
}

/**
 * Filter function for score books by title and short title
 * Works with NcSelect's filter-by prop
 * @param option - The score book option to filter
 * @param _label - The label of the option (unused, required by NcSelect API)
 * @param search - The search string
 * @return Whether the option matches the search
 */
export function filterScoreBooks(
	option: EntityOption<ScoreBook>,
	_label: string,
	search: string,
): boolean {
	const searchLower = search.toLowerCase()
	const scoreBook = option.entity
	return (
		scoreBook.title.toLowerCase().includes(searchLower)
		|| (scoreBook.titleShort?.toLowerCase().includes(searchLower) ?? false)
	)
}

/**
 * Create a selectable function for scores (excludes specific IDs)
 * @param excludeIds - Set of score IDs to exclude
 * @return Function to check if a score is selectable
 */
export function createScoreSelectable(excludeIds: Set<number>): (option: EntityOption<Score>) => boolean {
	return (option: EntityOption<Score>) => !excludeIds.has(option.value)
}

/**
 * Create a selectable function for score books (excludes specific IDs)
 * @param excludeIds - Set of score book IDs to exclude
 * @return Function to check if a score book is selectable
 */
export function createScoreBookSelectable(
	excludeIds: Set<number>,
): (option: EntityOption<ScoreBook>) => boolean {
	return (option: EntityOption<ScoreBook>) => !excludeIds.has(option.value)
}
