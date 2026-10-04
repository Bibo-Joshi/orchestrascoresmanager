<template>
	<NcButton v-if="editable"
		:size="buttonSize"
		variant="primary"
		:text="buttonText(t('Add'))"
		@click="openDialog">
		<template #icon>
			<AddIcon />
		</template>
	</NcButton>

	<AddOrEditDialog
		v-model:isOpen="showDialog"
		:name="t('Add score to book')"
		:loading="loadingScores"
		:error="loadScoresError ? t('Failed to load scores. Please try again.') : ''"
		:isInputValid="isFormValid"
		:submitLabel="t('Add')"
		@submit="handleSubmit"
		@reset="resetForm">
		<NcSelect
			v-model="selectedScore"
			:options="scoreOptions"
			:inputLabel="t('Score')"
			:placeholder="t('Select a score')"
			:filterBy="filterScores"
			:selectable="isScoreSelectable"
			required />

		<NcTextField
			v-model.number="inputIndex"
			:label="t('Index')"
			:placeholder="t('Enter index position')"
			:error="!!indexValidation.error.value"
			:success="indexValidation.isValid.value"
			:helperText="indexValidation.helperText.value"
			type="number"
			required />
	</AddOrEditDialog>
</template>

<script setup lang="ts">
import type { Score } from '@/api/generated/openapi/data-contracts'
import type { EntityOption } from '@/composables/useEntitySelect'

import { showSuccess } from '@nextcloud/dialogs'
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AddOrEditDialog from '@/components/AddOrEditDialog.vue'
import { useBreakpoints } from '@/composables/useBreakpoints.ts'
import {
	createScoreOptions,
	createScoreSelectable,
	filterScores,
} from '@/composables/useEntitySelect'
import { useIndexValidation } from '@/composables/useIndexValidation'
import { AddIcon } from '@/icons/vue-material'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useScoresStore } from '@/stores/scoresStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

interface Props {
	editable: boolean
	scoreBookId: number
	existingScoreIds: Set<number>
	occupiedIndices: Set<number>
}

const props = defineProps<Props>()

const emit = defineEmits<{
	scoreAdded: [score: Score]
}>()

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const { buttonSize, buttonText } = useBreakpoints()

const showDialog = ref(false)
const loadingScores = ref(false)
const loadScoresError = ref(false)
const selectedScore = ref<EntityOption<Score> | null>(null)
const inputIndex = ref<number>(NaN)

const occupiedIndicesRef = computed(() => props.occupiedIndices)
const indexValidation = useIndexValidation({
	index: inputIndex,
	occupiedIndices: occupiedIndicesRef,
})

const scoreOptions = computed(() => createScoreOptions(scoresStore.scores, false))

const isScoreSelectable = computed(() => createScoreSelectable(props.existingScoreIds))

const isFormValid = computed<boolean>(() => {
	if (!selectedScore.value) {
		return false
	}
	return indexValidation.isValid.value
})

/**
 * Ensure scores are loaded in the store
 */
async function ensureScoresLoaded() {
	if (scoresStore.isLoaded) {
		return
	}

	loadingScores.value = true
	loadScoresError.value = false

	try {
		await scoresStore.initialize()
	} catch (error) {
		console.error('Failed to load scores:', error)
		loadScoresError.value = true
	} finally {
		loadingScores.value = false
	}
}

// Initialize scores store on mount
onMounted(() => {
	scoresStore.initialize()
})

/**
 * Open the dialog and load scores if needed
 */
function openDialog() {
	resetForm()
	showDialog.value = true
	ensureScoresLoaded()
}

/**
 * Reset the form to initial state
 */
function resetForm() {
	selectedScore.value = null
	inputIndex.value = NaN
}

/**
 * Submit handler - add score to book
 */
async function handleSubmit() {
	if (!isFormValid.value) {
		return
	}

	await tryShowError(
		async () => {
			await scoreBooksStore.addScoreToBook(
				props.scoreBookId,
				selectedScore.value!.value,
				inputIndex.value,
			)

			// Build the score object to emit with updated scoreBook info
			const addedScore: Score = {
				...selectedScore.value!.entity,
				scoreBook: {
					id: props.scoreBookId,
					index: inputIndex.value,
				},
			}
			emit('scoreAdded', addedScore)

			showSuccess(t('Score added to book'))
			showDialog.value = false
			resetForm()
		},
		t('Failed to add score to book: '),
	)
}
</script>
