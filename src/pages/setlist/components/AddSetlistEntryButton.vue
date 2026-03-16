<template>
	<NcButton variant="primary" @click="showDialog = true">
		<template #icon>
			<AddIcon :size="20" />
		</template>
		{{ t('Add Entry') }}
	</NcButton>

	<NcDialog
		v-model:open="showDialog"
		:name="t('Add Setlist Entry')"
		@update:open="handleDialogClose">
		<template #default>
			<NcFormGroup hide-label>
				<FocusTrap />

				<NcSelect
					v-model="selectedEntryType"
					:options="entryTypeOptions"
					:input-label="t('Entry Type')"
					:placeholder="t('Select entry type')"
					label="label"
					track-by="value"
					@update:model-value="handleEntryTypeChange" />

				<NcSelect
					v-if="selectedEntryType?.value === 'score'"
					v-model="selectedScore"
					:options="availableScoreOptions"
					:input-label="t('Score')"
					:placeholder="t('Select score')"
					label="label"
					track-by="value" />

				<NcTextField
					v-if="selectedEntryType?.value === 'break'"
					v-model="breakDurationStr"
					:label="t('Break Duration (HH:MM:SS)')"
					:placeholder="t('e.g., 0:30 or 1:30:00')"
					@input="restrictToTimeFormat"
					@paste="restrictToTimeFormat" />
			</NcFormGroup>
		</template>
		<template #actions>
			<NcButton @click="showDialog = false">
				<template #icon>
					<CancelIcon :size="20" />
				</template>
				{{ t('Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="!isFormValid"
				@click="handleSubmit">
				<template #icon>
					<ConfirmIcon :size="20" />
				</template>
				{{ t('Add') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { t } from '@/utils/l10n'
import { ref, computed, watch } from 'vue'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { AddIcon, ConfirmIcon, CancelIcon } from '@/icons/vue-material'
import FocusTrap from '@/components/FocusTrap.vue'
import { useSetlistEntriesStore } from '@/stores/setlistEntriesStore'
import { useScoresStore } from '@/stores/scoresStore'
import { parseDurationHHMMSS, formatDurationHHMMSS, restrictToTimeFormat as restrictInputToTimeFormat } from '@/utils/timeFormatUtils'
import type { Setlist, Score, ScoreIndexed } from '@/api/generated/openapi/data-contracts'
import { apiClients } from '@/api/client'

interface Props {
	setlist: Setlist
	editable: boolean
}

const props = defineProps<Props>()

const setlistEntriesStore = useSetlistEntriesStore()
const scoresStore = useScoresStore()

const showDialog = ref(false)

/**
 * Entry type options for the first dropdown
 */
const entryTypeOptions = [
	{ label: t('Score'), value: 'score' as const },
	{ label: t('Break'), value: 'break' as const },
]

const selectedEntryType = ref<{ label: string; value: 'score' | 'break' } | null>(entryTypeOptions[0])
const selectedScore = ref<{ label: string; value: number } | null>(null)
const breakDurationStr = ref('')
const fcvScoreOptions = ref<Array<{ label: string; value: number }>>([])

/**
 * Get available scores based on whether setlist has a folder collection version
 */
const availableScoreOptions = computed(() => {
	// If setlist has FCV and we've loaded FCV scores, use those
	if (props.setlist.folderCollectionVersionId && fcvScoreOptions.value.length > 0) {
		return fcvScoreOptions.value
	}

	// Otherwise, show all scores
	const options: Array<{ label: string; value: number }> = []
	for (const score of scoresStore.scoresSorted) {
		options.push({
			label: score.title,
			value: score.id,
		})
	}
	return options
})

/**
 * Validate form inputs
 */
const isFormValid = computed(() => {
	if (!selectedEntryType.value) return false

	if (selectedEntryType.value.value === 'score') {
		return selectedScore.value !== null
	}

	if (selectedEntryType.value.value === 'break') {
		if (!breakDurationStr.value.trim()) return false
		try {
			parseDurationHHMMSS(breakDurationStr.value)
			return true
		} catch {
			return false
		}
	}

	return false
})

/**
 * Handle entry type selection change
 *
 * @param option - The selected entry type option
 */
function handleEntryTypeChange(option: { label: string; value: 'score' | 'break' } | null) {
	selectedEntryType.value = option
	// Reset the other fields when changing type
	selectedScore.value = null
	breakDurationStr.value = ''
}

/**
 * Restrict input to time format characters
 *
 * @param event - The input or paste event
 */
function restrictToTimeFormat(event: Event) {
	restrictInputToTimeFormat(event)
}

/**
 * Reset form to initial state
 */
function resetForm() {
	// keep default
	selectedEntryType.value = entryTypeOptions[0]
	selectedScore.value = null
	breakDurationStr.value = props.setlist.defaultModerationDuration !== null
		? formatDurationHHMMSS(props.setlist.defaultModerationDuration)
		: ''
}

/**
 * Handle dialog close
 *
 * @param open - Whether the dialog is open
 */
function handleDialogClose(open: boolean) {
	if (!open) {
		resetForm()
	}
}

/**
 * Load FCV scores when the dialog opens
 */
async function loadFcvScores() {
	if (!props.setlist.folderCollectionVersionId) return

	try {
		// Get the folder collection version to find the folder collection ID
		const versionResponse = await apiClients.default.folderCollectionVersionApiGetFolderCollectionVersion(
			props.setlist.folderCollectionVersionId,
		)
		const version = versionResponse.data.ocs.data

		// Load the scores for this folder collection version
		const scoresResponse = await apiClients.default.folderCollectionApiGetFolderCollectionScores(
			version.folderCollectionId,
			{ versionId: props.setlist.folderCollectionVersionId },
		)
		const fcvScores = scoresResponse.data.ocs.data as (Score | ScoreIndexed)[]

		// Build options from FCV scores
		const options: Array<{ label: string; value: number }> = []
		for (const score of fcvScores) {
			options.push({
				label: score.title,
				value: score.id,
			})
		}

		// Sort by title
		options.sort((a, b) => a.label.localeCompare(b.label))

		// Update the available scores
		fcvScoreOptions.value = options
	} catch (error) {
		console.error('Failed to load FCV scores:', error)
		showError(t('Failed to load available scores'))
	}
}

/**
 * Watch for dialog opening to load FCV scores if needed
 */
watch(showDialog, (isOpen) => {
	if (isOpen) {
		resetForm()
		if (props.setlist.folderCollectionVersionId) {
			loadFcvScores()
		}
	}
})

/**
 * Submit the form to create a new entry
 */
async function handleSubmit() {
	if (!isFormValid.value) return

	try {
		// Get the current entries to determine the next index
		const currentEntries = setlistEntriesStore.getEntriesBySetlistId(props.setlist.id)
		const nextIndex = currentEntries.length > 0
			? Math.max(...currentEntries.map(e => e.index)) + 10
			: 0

		if (selectedEntryType.value?.value === 'score' && selectedScore.value) {
			// Create score entry
			await setlistEntriesStore.createEntry(
				props.setlist.id,
				nextIndex,
				selectedScore.value.value,
				null,
				null,
				null,
			)
			// Overlaps with "add entry" button which is annoying. Let's skip the toast for now
			// showSuccess(t('Score entry added'))
		} else if (selectedEntryType.value?.value === 'break') {
			// Create break entry
			// Form validation ensures the duration string is valid and non-empty
			const breakDuration = parseDurationHHMMSS(breakDurationStr.value)!
			await setlistEntriesStore.createEntry(
				props.setlist.id,
				nextIndex,
				null,
				breakDuration,
				null,
				null,
			)
			// Overlaps with "add entry" button which is annoying. Let's skip the toast for now
			// showSuccess(t('Break entry added'))
		}

		showDialog.value = false
		resetForm()
	} catch (error) {
		console.error('Failed to create entry:', error)
		showError(t('Failed to add entry'))
	}
}
</script>
