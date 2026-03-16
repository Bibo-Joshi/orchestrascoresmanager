<template>
	<NcAppSidebar
		v-if="setlistSidebarStore.isOpen && setlist"
		v-model="setlistSidebarStore.isOpen"
		:name="setlist.title"
		:force-tabs="false"
		@close="setlistSidebarStore.closeSidebar()">
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
					track-by="value"
					@update:model-value="handleFolderCollectionChange" />

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

				<div v-if="editable" class="save-button-container">
					<NcButton
						variant="secondary"
						:disabled="isSaving"
						@click="handleSave">
						<template #icon>
							<NcLoadingIcon v-if="isSaving" :size="20" />
							<ConfirmIcon v-else :size="20" />
						</template>
						{{ isSaving ? t('Saving...') : t('Save') }}
					</NcButton>
				</div>
			</NcFormGroup>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script setup lang="ts">
import { t } from '@/utils/l10n'
import { ref, computed, watch, onMounted } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { InfoIcon, ConfirmIcon } from '@/icons/vue-material'
import { useSetlistSidebarStore } from '@/stores/setlistSidebarStore'
import { useSetlistsStore } from '@/stores/setlistsStore'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import type { Setlist } from '@/api/generated/openapi/data-contracts'
import { formatDurationHHMMSS, parseDurationHHMMSS, formatDurationHHMM, parseDurationHHMM, restrictToTimeFormat as restrictInputToTimeFormat } from '@/utils/timeFormatUtils'

interface Props {
	setlist: Setlist | undefined
	editable: boolean
}

const props = defineProps<Props>()

const setlistSidebarStore = useSetlistSidebarStore()
const setlistsStore = useSetlistsStore()
const folderCollectionsStore = useFolderCollectionsStore()

const isSaving = ref(false)

interface FormData {
	startDateTime: Date | null
	description: string | null
	defaultModerationTimeStr: string
	durationStr: string
	isDraft: boolean
	isPublished: boolean
}

const formData = ref<FormData>({
	startDateTime: null,
	description: null,
	defaultModerationTimeStr: '',
	durationStr: '',
	isDraft: false,
	isPublished: false,
})

const selectedFolderCollectionVersion = ref<{ label: string; value: number | null } | null>(null)

/**
 * Get active folder collection versions for the dropdown
 */
const folderCollectionVersionOptions = computed(() => {
	const options: Array<{ label: string; value: number | null }> = []

	for (const collection of folderCollectionsStore.folderCollectionsSorted) {
		if (collection.activeVersionId) {
			options.push({
				label: collection.title,
				value: collection.activeVersionId,
			})
		}
	}

	return options
})

/**
 * Initialize form data from setlist
 */
function initializeFormData() {
	if (!props.setlist) return

	formData.value = {
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
watch(() => props.setlist, () => {
	initializeFormData()
}, { immediate: true })

/**
 * Initialize folder collections store when component mounts
 */
onMounted(async () => {
	await folderCollectionsStore.initialize()
})

/**
 * Handle folder collection selection change
 *
 * @param option - The selected folder collection option
 */
function handleFolderCollectionChange(option: { label: string; value: number | null } | null) {
	selectedFolderCollectionVersion.value = option
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
				startDateTime: formData.value.startDateTime?.toISOString() ?? null,
				description: formData.value.description,
				defaultModerationDuration,
				duration,
				folderCollectionVersionId: selectedFolderCollectionVersion.value?.value ?? null,
				isDraft: formData.value.isDraft,
				isPublished: formData.value.isPublished,
			})
			showSuccess(t('Setlist updated successfully'))
		},
		t('Failed to update setlist: '),
		() => {
			isSaving.value = false
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

.save-button-container {
	display: flex;
	justify-content: flex-end;
	margin-top: 16px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}
</style>
