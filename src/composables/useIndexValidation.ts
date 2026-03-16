/**
 * Composable for validating index inputs in score books and folder collections.
 * Provides consistent index validation logic across add/edit dialogs.
 */
import { computed, type Ref } from 'vue'
import { t } from '@/utils/l10n'

export interface IndexValidationOptions {
	/** The index value to validate */
	index: Ref<number>
	/** Set of indices that are already occupied */
	occupiedIndices: Ref<Set<number>>
	/** Whether index validation is enabled (for indexed collections) */
	enabled?: Ref<boolean>
	/** Whether loading is in progress */
	loading?: Ref<boolean>
	/** The current index (for edit scenarios to allow unchanged values) */
	currentIndex?: Ref<number | null | undefined>
}

export interface IndexValidationResult {
	/** Error message if validation fails, empty string otherwise */
	error: Ref<string>
	/** Whether the index is valid for submission */
	isValid: Ref<boolean>
	/** Whether the index is unchanged from original */
	isUnchanged: Ref<boolean>
	/** Helper text for the index field */
	helperText: Ref<string>
}

/**
 * Composable for index validation in add/edit dialogs
 * @param options - Validation options including index value and occupied indices
 * @return Validation result with error, isValid, isUnchanged, and helperText
 */
export function useIndexValidation(options: IndexValidationOptions): IndexValidationResult {
	const {
		index,
		occupiedIndices,
		enabled = { value: true } as Ref<boolean>,
		loading = { value: false } as Ref<boolean>,
		currentIndex,
	} = options

	const isUnchanged = computed<boolean>(() => {
		if (currentIndex === undefined) return false
		return index.value === currentIndex.value
	})

	const error = computed<string>(() => {
		if (!enabled.value) return ''
		if (isNaN(index.value) || index.value < 0) {
			return t('Index must be a non-negative integer')
		}
		// Don't show "already occupied" error if index is unchanged
		if (isUnchanged.value) {
			return ''
		}
		if (occupiedIndices.value.has(index.value)) {
			return t('This index is already occupied')
		}
		return ''
	})

	const isValid = computed<boolean>(() => {
		if (!enabled.value) return true
		if (isUnchanged.value) return false // Not "valid" in the sense of allowing submission for unchanged
		return !error.value && !isNaN(index.value)
	})

	const helperText = computed<string>(() => {
		if (!enabled.value) return ''
		if (loading.value) return t('Loading occupied indices...')
		if (isUnchanged.value) return t('Index is unchanged')
		if (error.value) return error.value
		if (isValid.value) return t('Index is valid')
		return t('Enter the position index in the collection')
	})

	return {
		error,
		isValid,
		isUnchanged,
		helperText,
	}
}
