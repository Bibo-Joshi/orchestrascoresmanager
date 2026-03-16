<template>
	<AddOrEditDialog
		v-model:is-open="showDialog"
		:name="t('Assign to Score Book')"
		:is-input-valid="isFormValid"
		:submit-label="t('Save')"
		@submit="handleSubmit"
		@reset="resetForm">
		<NcSelect
			v-model="selectedScoreBook"
			:options="scoreBookOptions"
			:input-label="t('Score Book')"
			:placeholder="t('Select a score book')"
			:clearable="true"
			:filter-by="filterScoreBooks" />

		<NcTextField
			v-if="selectedScoreBook !== null"
			v-model.number="inputIndex"
			:label="t('Index')"
			:placeholder="t('Enter index position')"
			:error="!!indexValidation.error.value"
			:success="indexValidation.isValid.value"
			:helper-text="indexValidation.helperText.value"
			type="number"
			required />

		<NcNoteCard v-if="isRemovingFromBook" type="info">
			{{ t('This will remove the score from its current book.') }}
		</NcNoteCard>
	</AddOrEditDialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { t } from '@/utils/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { showSuccess } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import AddOrEditDialog from '@/components/AddOrEditDialog.vue'
import { useIndexValidation } from '@/composables/useIndexValidation'
import {
	createScoreBookOptions,
	filterScoreBooks,
	type EntityOption,
} from '@/composables/useEntitySelect'
import { useScoresStore } from '@/stores/scoresStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import type { ScoreBook } from '@/api/generated/openapi/data-contracts'

interface Props {
	modelValue: boolean
	scoreId: number
	currentScoreBookId: number | null
	currentIndex: number | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
	'update:modelValue': [value: boolean]
	'updated': [scoreBookId: number | null, index: number | null]
}>()

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()

const showDialog = computed({
	get: () => props.modelValue,
	set: (value) => emit('update:modelValue', value),
})

const selectedScoreBook = ref<EntityOption<ScoreBook> | null>(null)
const inputIndex = ref<number>(NaN)
const occupiedIndices = ref<Set<number>>(new Set())
const loadingIndices = ref(false)

const scoreBookOptions = computed(() => createScoreBookOptions(scoreBooksStore.scoreBooks))

/**
 * Check if the user is removing the score from a book
 */
const isRemovingFromBook = computed<boolean>(() => {
	return selectedScoreBook.value === null && props.currentScoreBookId !== null
})

/**
 * Load occupied indices for the selected score book
 * @param scoreBookId - The ID of the score book to load indices for
 */
async function loadOccupiedIndices(scoreBookId: number) {
	loadingIndices.value = true
	try {
		await scoreBooksStore.loadScoreBookScores(scoreBookId)
		occupiedIndices.value = scoreBooksStore.getOccupiedIndices(scoreBookId, props.scoreId)
	} catch (error) {
		console.error('Failed to load occupied indices', error)
		occupiedIndices.value = new Set()
	} finally {
		loadingIndices.value = false
	}
}

// Load occupied indices when score book changes
watch(selectedScoreBook, (newValue) => {
	if (newValue !== null) {
		loadOccupiedIndices(newValue.value)
	} else {
		occupiedIndices.value = new Set()
	}
})

const hasScoreBookSelected = computed(() => selectedScoreBook.value !== null)
const currentIndexRef = computed(() => {
	if (selectedScoreBook.value?.value === props.currentScoreBookId) {
		return props.currentIndex
	}
	return undefined
})

const indexValidation = useIndexValidation({
	index: inputIndex,
	occupiedIndices,
	enabled: hasScoreBookSelected,
	loading: loadingIndices,
	currentIndex: currentIndexRef,
})

/**
 * Check if there are any changes to submit
 */
const hasChanges = computed<boolean>(() => {
	// If clearing selection and was previously in a book, that's a change
	if (selectedScoreBook.value === null) {
		return props.currentScoreBookId !== null
	}

	// If selecting a different book, that's a change
	if (selectedScoreBook.value.value !== props.currentScoreBookId) {
		return true
	}

	// If same book but different index, that's a change
	return inputIndex.value !== props.currentIndex
})

/**
 * Check if the form is valid for submission
 */
const isFormValid = computed<boolean>(() => {
	// Must have changes to submit
	if (!hasChanges.value) return false

	// Valid if removing from book (clearing selection)
	if (selectedScoreBook.value === null) return true

	// Valid if assigning to book with valid index (and index is not unchanged)
	return indexValidation.isValid.value
})

/**
 * Reset the form
 */
function resetForm() {
	// Set initial values based on current assignment
	if (props.currentScoreBookId !== null) {
		const currentBook = scoreBookOptions.value.find(o => o.value === props.currentScoreBookId)
		selectedScoreBook.value = currentBook ?? null
		inputIndex.value = props.currentIndex ?? NaN
	} else {
		selectedScoreBook.value = null
		inputIndex.value = NaN
	}
}

// Initialize form when dialog opens
watch(showDialog, (isOpen) => {
	if (isOpen) {
		resetForm()
	}
})

/**
 * Submit handler
 */
async function handleSubmit() {
	if (!isFormValid.value) return

	await tryShowError(
		async () => {
			if (selectedScoreBook.value === null) {
				// Remove from book
				await scoresStore.updateScoreBookAssignmentApi(props.scoreId, null)
				emit('updated', null, null)
			} else {
				// Assign to book with index
				const newScoreBook = { id: selectedScoreBook.value.value, index: inputIndex.value }
				await scoresStore.updateScoreBookAssignmentApi(props.scoreId, newScoreBook)
				emit('updated', selectedScoreBook.value.value, inputIndex.value)
			}

			showSuccess(t('Score book assignment updated'))
			showDialog.value = false
		},
		t('Failed to update score book assignment: '),
	)
}
</script>
