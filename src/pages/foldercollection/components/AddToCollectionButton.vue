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
		:name="t('Add to collection')"
		:loading="loadingData"
		:error="loadDataError ? t('Failed to load data. Please try again.') : ''"
		:isInputValid="isFormValid"
		:submitLabel="t('Add')"
		@submit="handleSubmit"
		@reset="resetForm">
		<FocusTrap />

		<!-- Type Selection -->
		<NcSelect
			v-model="selectedType"
			:options="typeOptions"
			:inputLabel="t('Type')"
			:placeholder="t('Select what to add')"
			required />

		<!-- Score Selection (when type is 'score') -->
		<NcSelect
			v-if="selectedType?.value === 'score'"
			v-model="selectedScore"
			:options="scoreOptions"
			:inputLabel="t('Score')"
			:placeholder="t('Select a score')"
			:filterBy="filterScores"
			:selectable="scoreSelectable"
			required />

		<!-- Score Book Selection (when type is 'scorebook') -->
		<NcSelect
			v-if="selectedType?.value === 'scorebook'"
			v-model="selectedScoreBook"
			:options="scoreBookOptions"
			:inputLabel="t('Score Book')"
			:placeholder="t('Select a score book')"
			:filterBy="filterScoreBooks"
			:selectable="scoreBookSelectable"
			required />

		<!-- Index field (for indexed collections) -->
		<NcTextField
			v-if="isIndexed"
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
import type { Score, ScoreBook, ScoreBookIndexed, ScoreIndexed } from '@/api/generated/openapi/data-contracts'
import type { EntityOption } from '@/composables/useEntitySelect'

import { showSuccess } from '@nextcloud/dialogs'
import { computed, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AddOrEditDialog from '@/components/AddOrEditDialog.vue'
import FocusTrap from '@/components/FocusTrap.vue'
import { apiClients } from '@/api/client'
import { useBreakpoints } from '@/composables/useBreakpoints'
import {
	createScoreBookOptions,
	createScoreBookSelectable,
	createScoreOptions,
	createScoreSelectable,
	filterScoreBooks,
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
	folderCollectionId: number
	isIndexed: boolean
	existingScoreIds: Set<number>
	existingScoreBookIds: Set<number>
	occupiedIndices: Set<number>
}

const props = defineProps<Props>()

const emit = defineEmits<{
	scoreAdded: [score: Score | ScoreIndexed]
	scorebookAdded: [scoreBook: ScoreBook | ScoreBookIndexed]
}>()

interface TypeOption {
	label: string
	value: 'score' | 'scorebook'
}

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const { buttonSize, buttonText } = useBreakpoints()

const showDialog = ref(false)
const loadingData = ref(false)
const loadDataError = ref(false)

const selectedType = ref<TypeOption | null>({ label: t('Score'), value: 'score' })
const selectedScore = ref<EntityOption<Score> | null>(null)
const selectedScoreBook = ref<EntityOption<ScoreBook> | null>(null)
const inputIndex = ref<number>(NaN)

const typeOptions: TypeOption[] = [
	{ label: t('Score'), value: 'score' },
	{ label: t('Score Book'), value: 'scorebook' },
]

const occupiedIndicesRef = computed(() => props.occupiedIndices)
const isIndexedRef = computed(() => props.isIndexed)
const indexValidation = useIndexValidation({
	index: inputIndex,
	occupiedIndices: occupiedIndicesRef,
	enabled: isIndexedRef,
})

const scoreOptions = computed(() => createScoreOptions(scoresStore.scores, false, props.existingScoreBookIds))
const scoreBookOptions = computed(() => createScoreBookOptions(scoreBooksStore.scoreBooks))

const scoreSelectable = computed(() => createScoreSelectable(props.existingScoreIds))
const scoreBookSelectable = computed(() => createScoreBookSelectable(props.existingScoreBookIds))

const isFormValid = computed<boolean>(() => {
	if (!selectedType.value) {
		return false
	}

	if (selectedType.value.value === 'score') {
		if (!selectedScore.value) {
			return false
		}
	} else {
		if (!selectedScoreBook.value) {
			return false
		}
	}

	if (props.isIndexed) {
		return indexValidation.isValid.value
	}

	return true
})

/**
 * Ensure data is loaded in stores
 */
async function ensureDataLoaded() {
	if (scoresStore.isLoaded && scoreBooksStore.isLoaded) {
		return
	}

	loadingData.value = true
	loadDataError.value = false

	try {
		await Promise.all([
			scoresStore.initialize(),
			scoreBooksStore.initialize(),
		])
	} catch (error) {
		console.error('Failed to load data:', error)
		loadDataError.value = true
	} finally {
		loadingData.value = false
	}
}

// Initialize stores on mount
onMounted(() => {
	scoresStore.initialize()
	scoreBooksStore.initialize()
})

/**
 * Open dialog and load data
 */
function openDialog() {
	resetForm()
	showDialog.value = true
	ensureDataLoaded()
}

/**
 * Reset form
 */
function resetForm() {
	selectedType.value = { label: t('Score'), value: 'score' }
	selectedScore.value = null
	selectedScoreBook.value = null
	inputIndex.value = NaN
}

// Clear selection when type changes
watch(selectedType, () => {
	selectedScore.value = null
	selectedScoreBook.value = null
})

/**
 * Submit handler
 */
async function handleSubmit() {
	if (!isFormValid.value) {
		return
	}

	await tryShowError(
		async () => {
			if (selectedType.value!.value === 'score') {
				const payload: { scoreId: number, index?: number | null } = {
					scoreId: selectedScore.value!.value,
				}
				if (props.isIndexed) {
					payload.index = inputIndex.value
				}

				await apiClients.default.folderCollectionApiPostFolderCollectionScore(
					props.folderCollectionId,
					payload,
				)

				const addedScore = selectedScore.value!.entity
				if (props.isIndexed) {
					const scoreIndexed: ScoreIndexed = { ...addedScore, index: inputIndex.value }
					emit('scoreAdded', scoreIndexed)
				} else {
					emit('scoreAdded', addedScore)
				}

				showSuccess(t('Score added to collection'))
			} else {
				const payload: { scoreBookId: number, index?: number | null } = {
					scoreBookId: selectedScoreBook.value!.value,
				}
				if (props.isIndexed) {
					payload.index = inputIndex.value
				}

				await apiClients.default.folderCollectionApiPostFolderCollectionScoreBook(
					props.folderCollectionId,
					payload,
				)

				const addedScoreBook = selectedScoreBook.value!.entity
				if (props.isIndexed) {
					const scoreBookIndexed: ScoreBookIndexed = { ...addedScoreBook, index: inputIndex.value }
					emit('scorebookAdded', scoreBookIndexed)
				} else {
					emit('scorebookAdded', addedScoreBook)
				}

				showSuccess(t('Score book added to collection'))
			}

			showDialog.value = false
			resetForm()
		},
		t('Failed to add to collection: '),
	)
}
</script>
