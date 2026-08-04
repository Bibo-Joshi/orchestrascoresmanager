<template>
	<NcAppSidebar
		v-if="setlistSidebarStore.isOpen && setlist"
		v-model="setlistSidebarStore.isOpen"
		:name="formData.title"
		:force-tabs="false"
		:name-editable="nameEditable"
		@update:name="(event) => {
			formData.title = event
		}"
		@submit-name="nameEditable = false"
		@dismiss-editing="nameEditable = false"
		@close="setlistSidebarStore.closeSidebar()">
		<template #tertiary-actions>
			<NcButton
				v-if="editable"
				variant="tertiary-no-background"
				:size="buttonSize"
				@click="nameEditable = true">
				<template #icon>
					<EditIcon :size="20" />
				</template>
			</NcButton>
		</template>
		<NcAppSidebarTab
			id="details"
			:name="t('Details')">
			<template #icon>
				<InfoIcon :size="20" />
			</template>
			<NcFormGroup hide-label>
				<NcDateTimePickerNative
					id="startDateTime"
					v-model="formData.startDateTime"
					:disabled="!editable"
					:label="t('Start Date & Time')"
					type="datetime-local" />

				<NcTextArea
					id="description"
					v-model="formData.description"
					:disabled="!editable"
					:label="t('Description')"
					:placeholder="t('Enter description')" />

				<NcSelect
					v-model="selectedFolderCollectionVersion"
					:disabled="!editable"
					:options="folderCollectionVersionOptions"
					:input-label="t('Folder Collection')"
					:placeholder="t('Select folder collection')"
					:clearable="true"
					label="label"
					track-by="value" />

				<NcTextField
					v-model="formData.defaultModerationTimeStr"
					:disabled="!editable"
					:label="t('Default Moderation Time (HH:MM:SS)')"
					:placeholder="t('e.g., 0:30 or 1:30:00')"
					@input="restrictToTimeFormat"
					@paste="restrictToTimeFormat" />

				<NcTextField
					v-model="formData.durationStr"
					:disabled="!editable"
					:label="t('Duration (HH:MM)')"
					:placeholder="t('e.g., 90 or 1:30')"
					@input="restrictToTimeFormat"
					@paste="restrictToTimeFormat" />

				<div class="status-switches">
					<NcCheckboxRadioSwitch
						v-model="formData.isDraft"
						:disabled="!editable"
						type="switch">
						{{ t('Is Draft') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						v-model="formData.isPublished"
						:disabled="!editable"
						type="switch">
						{{ t('Is Published') }}
					</NcCheckboxRadioSwitch>
				</div>

				<div v-if="editable" class="save-icon-container">
					<NcSavingIndicatorIcon
						:name="isSaving ? t('Saving...') : t('Saved')"
						:saving="isSaving"
						:error="isSavingError" />
				</div>
			</NcFormGroup>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script setup lang="ts">
import { t } from '@/utils/l10n'
import { ref, watch, onMounted } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import debounce from 'lodash.debounce'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { InfoIcon, EditIcon } from '@/icons/vue-material'
import { useSetlistSidebarStore } from '@/stores/setlistSidebarStore'
import { useSetlistsStore } from '@/stores/setlistsStore'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import type { Setlist } from '@/api/generated/openapi/data-contracts'
import { formatDurationHHMMSS, parseDurationHHMMSS, formatDurationHHMM, parseDurationHHMM, restrictToTimeFormat as restrictInputToTimeFormat } from '@/utils/timeFormatUtils'
import { apiClients } from '@/api/client.ts'
import { formatDateStr } from '@/composables/useDateFormatting.ts'
import NcButton from '@nextcloud/vue/components/NcButton'
import { useBreakpoints } from '@/composables/useBreakpoints.ts'

interface Props {
	setlist: Setlist | undefined
	editable: boolean
}

const props = defineProps<Props>()

const setlistSidebarStore = useSetlistSidebarStore()
const setlistsStore = useSetlistsStore()
const folderCollectionsStore = useFolderCollectionsStore()
const { buttonSize } = useBreakpoints()

const nameEditable = ref(false)
const isSaving = ref(false)
const isSavingError = ref(false)
const skipNextSave = ref(false)

interface FormData {
	title: string
	startDateTime: Date | null
	description: string | null
	defaultModerationTimeStr: string
	durationStr: string
	isDraft: boolean
	isPublished: boolean
}

const formData = ref<FormData>({
	title: '',
	startDateTime: null,
	description: null,
	defaultModerationTimeStr: '',
	durationStr: '',
	isDraft: false,
	isPublished: false,
})

const selectedFolderCollectionVersion = ref<{ label: string; value: number | null } | null>(null)
const folderCollectionVersionOptions = ref<Array<{ label: string; value: number | null }>>([])

/**
 * Get active folder collection versions for the dropdown
 *
 * @param selectedID - The currently selected folder collection version ID (if any) to ensure it's included in options
 * @return An array of options for the folder collection version select dropdown
 */
async function getFolderCollectionVersionOptions(selectedID: number | null = null) {
	const options: Array<{ label: string; value: number | null }> = []

	for (const collection of folderCollectionsStore.folderCollectionsSorted) {
		if (collection.activeVersionId) {
			options.push({
				label: collection.title,
				value: collection.activeVersionId,
			})
		}
	}

	if (selectedID && !options.some(opt => opt.value === selectedID)) {
		// If the currently selected version is not in the options, add it
		const fcv = (await apiClients.default.folderCollectionVersionApiGetFolderCollectionVersion(selectedID)).data.ocs.data
		const fc = folderCollectionsStore.getFolderCollectionById(fcv.folderCollectionId)
		if (fc) {
			options.push({
				label: `${fc.title} (${formatDateStr(fcv.validFrom)})`,
				value: selectedID,
			})
		}
	}

	return options
}

/**
 * Initialize form data from setlist
 */
async function initializeFormData() {
	if (!props.setlist) return

	skipNextSave.value = true

	formData.value = {
		title: props.setlist.title,
		startDateTime: props.setlist.startDateTime ? new Date(props.setlist.startDateTime) : null,
		description: props.setlist.description,
		defaultModerationTimeStr: props.setlist.defaultModerationDuration !== null
			? formatDurationHHMMSS(props.setlist.defaultModerationDuration)
			: '',
		durationStr: props.setlist.duration !== null
			? formatDurationHHMM(props.setlist.duration)
			: '',
		isDraft: props.setlist.isDraft,
		isPublished: props.setlist.isPublished,
	}

	// Set selected folder collection
	folderCollectionVersionOptions.value = await getFolderCollectionVersionOptions(props.setlist?.folderCollectionVersionId)
	if (props.setlist.folderCollectionVersionId) {
		const option = folderCollectionVersionOptions.value.find(
			opt => opt.value === props.setlist.folderCollectionVersionId,
		)
		selectedFolderCollectionVersion.value = option || null
	} else {
		selectedFolderCollectionVersion.value = null
	}
}

/**
 * Watch for setlist changes and reinitialize form
 */
watch(() => props.setlist?.id, async (newId, oldId) => {
	if (newId !== oldId) {
		await initializeFormData()
	}
}, { immediate: true })

/**
 * Create debounced save function to avoid multiple rapid requests
 */
const debouncedSave = debounce(handleSave, 800)
/**
 * Watch all form fields and auto-save with debounce
 */
watch(
	() => ({
		formData: formData.value,
		selectedFolderCollection: selectedFolderCollectionVersion.value,
	}),
	() => {
		if (skipNextSave.value) {
			skipNextSave.value = false
			return
		}
		debouncedSave()
	},
	{ deep: true },
)

/**
 * Initialize folder collections store when component mounts
 */
onMounted(async () => {
	await folderCollectionsStore.initialize()
})

/**
 * Restrict input to time format characters
 *
 * @param event - The input or paste event
 */
function restrictToTimeFormat(event: Event) {
	restrictInputToTimeFormat(event)
}

/**
 * Validate and save the setlist
 */
async function handleSave() {
	if (!props.setlist || !props.editable) return

	// Validate and parse times
	let defaultModerationDuration: number | null = null
	let duration: number | null = null

	try {
		if (formData.value.defaultModerationTimeStr.trim()) {
			defaultModerationDuration = parseDurationHHMMSS(formData.value.defaultModerationTimeStr)
		}
	} catch (e) {
		showError(t('Invalid default moderation time format. Use (HH:)MM:SS'))
		return
	}

	try {
		if (formData.value.durationStr.trim()) {
			duration = parseDurationHHMM(formData.value.durationStr)
		}
	} catch (e) {
		showError(t('Invalid duration format. Use (HH:)MM'))
		return
	}

	isSaving.value = true

	await tryShowError(
		async () => {
			await setlistsStore.updateSetlist(props.setlist.id, {
				title: formData.value.title.trim(),
				startDateTime: formData.value.startDateTime?.toISOString() ?? null,
				description: formData.value.description,
				defaultModerationDuration,
				duration,
				folderCollectionVersionId: selectedFolderCollectionVersion.value?.value ?? null,
				isDraft: formData.value.isDraft,
				isPublished: formData.value.isPublished,
			})
			isSavingError.value = false
		},
		t('Failed to update setlist: '),
		() => {
			isSaving.value = false
			isSavingError.value = true
		},
	)

	isSaving.value = false
}
</script>

<style lang="scss" scoped>
.status-switches {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.save-icon-container {
	display: flex;
	justify-content: flex-end;
	margin-top: 16px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}
</style>
