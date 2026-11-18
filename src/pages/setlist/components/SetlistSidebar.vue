<template>
	<NcAppSidebar
		v-if="setlistSidebarStore.isOpen && setlist"
		v-model="setlistSidebarStore.isOpen"
		:name="formData.title"
		:forceTabs="false"
		:nameEditable="nameEditable"
		@update:name="(event) => {
			formData.title = event
		}"
		@submitName="nameEditable = false"
		@dismissEditing="nameEditable = false"
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
			<NcFormGroup hideLabel>
				<NcDateTimePickerNative
					id="startDateTime"
					v-model="formData.startDateTime"
					:disabled="!editable"
					:label="t('Start Date & Time')"
					type="datetime-local" />

				<NcTextArea
					id="description"
					:modelValue="formData.description ?? ''"
					:disabled="!editable"
					:label="t('Description')"
					:placeholder="t('Enter description')"
					@update:modelValue="formData.description = $event" />

				<NcSelect
					v-model="selectedFolderCollectionVersion"
					:disabled="!editable"
					:options="folderCollectionVersionOptions"
					:inputLabel="t('Folder Collection')"
					:placeholder="t('Select folder collection')"
					:clearable="true"
					label="label"
					trackBy="value" />

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

				<label v-if="editable" class="pdf-template-select">
					{{ t('Default PDF template') }}
					<select
						:value="selectedTemplateId"
						:disabled="!templatesLoaded || isSaving"
						@change="selectTemplate">
						<option value="">{{ t('Standard template (built-in)') }}</option>
						<option v-for="template in pdfTemplates" :key="template.id" :value="template.id">
							{{ template.name }}
						</option>
					</select>
				</label>

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
						:name="isSaving ? t('Saving…') : t('Saved')"
						:saving="isSaving"
						:error="isSavingError" />
				</div>
			</NcFormGroup>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script setup lang="ts">
import type { Setlist } from '@/api/generated/openapi/data-contracts'
import type { PdfTemplate } from '@/utils/pdfTemplatesApi'

import { showError } from '@nextcloud/dialogs'
import debounce from 'lodash.debounce'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { apiClients } from '@/api/client.ts'
import { useBreakpoints } from '@/composables/useBreakpoints.ts'
import { formatDateStr } from '@/composables/useDateFormatting.ts'
import { EditIcon, InfoIcon } from '@/icons/vue-material'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import { useSetlistSidebarStore } from '@/stores/setlistSidebarStore'
import { useSetlistsStore } from '@/stores/setlistsStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'
import { fetchPdfTemplates } from '@/utils/pdfTemplatesApi'
import { formatDurationHHMM, formatDurationHHMMSS, parseDurationHHMM, parseDurationHHMMSS, restrictToTimeFormat as restrictInputToTimeFormat } from '@/utils/timeFormatUtils'

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
const pdfTemplates = ref<PdfTemplate[]>([])
const templatesLoaded = ref(false)
const templateChange = ref<{ id: string | null }>()
const selectedTemplateId = computed(() => {
	const id = templateChange.value ? templateChange.value.id : props.setlist?.defaultTemplateId
	return pdfTemplates.value.find((template) => template.id === id)?.id ?? ''
})

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

const selectedFolderCollectionVersion = ref<{ label: string, value: number | null } | null>(null)
const folderCollectionVersionOptions = ref<Array<{ label: string, value: number | null }>>([])

/**
 * Get active folder collection versions for the dropdown
 *
 * @param selectedID - The currently selected folder collection version ID (if any) to ensure it's included in options
 * @return An array of options for the folder collection version select dropdown
 */
async function getFolderCollectionVersionOptions(selectedID: number | null = null) {
	const options: Array<{ label: string, value: number | null }> = []

	for (const collection of folderCollectionsStore.folderCollectionsSorted) {
		if (collection.activeVersionId) {
			options.push({
				label: collection.title,
				value: collection.activeVersionId,
			})
		}
	}

	if (selectedID && !options.some((opt) => opt.value === selectedID)) {
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
	const setlist = props.setlist
	if (!setlist) {
		return
	}

	const options = await getFolderCollectionVersionOptions(setlist.folderCollectionVersionId)
	if (props.setlist?.id !== setlist.id) {
		return
	}

	skipNextSave.value = true

	formData.value = {
		title: setlist.title,
		startDateTime: setlist.startDateTime ? new Date(setlist.startDateTime) : null,
		description: setlist.description,
		defaultModerationTimeStr: setlist.defaultModerationDuration !== null
			? formatDurationHHMMSS(setlist.defaultModerationDuration)
			: '',
		durationStr: setlist.duration !== null
			? formatDurationHHMM(setlist.duration)
			: '',
		isDraft: setlist.isDraft,
		isPublished: setlist.isPublished,
	}

	folderCollectionVersionOptions.value = options
	selectedFolderCollectionVersion.value = options.find(({ value }) => value === setlist.folderCollectionVersionId) ?? null
}

const debouncedSave = debounce(handleSave, 800)
onUnmounted(() => debouncedSave.cancel())

watch(() => props.setlist?.id, async (newId, oldId) => {
	if (newId !== oldId) {
		debouncedSave.cancel()
		templateChange.value = undefined
		await initializeFormData()
	}
}, { immediate: true })

watch(
	() => setlistSidebarStore.isOpen && props.editable ? props.setlist?.id : undefined,
	async (id, _oldId, onCleanup) => {
		templatesLoaded.value = false
		pdfTemplates.value = []
		if (id === undefined) {
			return
		}
		let current = true
		onCleanup(() => {
			current = false
		})
		await tryShowError(async () => {
			const templates = await fetchPdfTemplates()
			if (current) {
				pdfTemplates.value = templates
				templatesLoaded.value = true
			}
		}, t('Failed to load PDF templates'))
	},
	{ immediate: true },
)

/**
 * Schedule the selected default as part of the sidebar's normal autosave.
 * @param event Template selection event.
 */
function selectTemplate(event: Event): void {
	const id = (event.target as HTMLSelectElement).value || null
	templateChange.value = { id }
	debouncedSave()
}

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
	const setlist = props.setlist
	if (!setlist || !props.editable) {
		return
	}

	if (isSaving.value) {
		debouncedSave()
		return
	}

	// Validate and parse times
	let defaultModerationDuration: number | null = null
	let duration: number | null = null

	try {
		if (formData.value.defaultModerationTimeStr.trim()) {
			defaultModerationDuration = parseDurationHHMMSS(formData.value.defaultModerationTimeStr)
		}
	} catch {
		showError(t('Invalid default moderation time format. Use (HH:)MM:SS'))
		return
	}

	try {
		if (formData.value.durationStr.trim()) {
			duration = parseDurationHHMM(formData.value.durationStr)
		}
	} catch {
		showError(t('Invalid duration format. Use (HH:)MM'))
		return
	}

	isSaving.value = true
	const pendingTemplate = templateChange.value

	await tryShowError(
		async () => {
			await setlistsStore.updateSetlist(setlist.id, {
				title: formData.value.title.trim(),
				startDateTime: formData.value.startDateTime?.toISOString() ?? null,
				description: formData.value.description,
				defaultModerationDuration,
				duration,
				folderCollectionVersionId: selectedFolderCollectionVersion.value?.value ?? null,
				isDraft: formData.value.isDraft,
				isPublished: formData.value.isPublished,
				...(pendingTemplate ? { defaultTemplateId: pendingTemplate.id } : {}),
			})
			if (templateChange.value === pendingTemplate) {
				templateChange.value = undefined
			}
			isSavingError.value = false
		},
		t('Failed to update setlist: '),
		() => {
			isSaving.value = false
			isSavingError.value = true
			if (templateChange.value === pendingTemplate) {
				templateChange.value = undefined
			}
		},
	)

	isSaving.value = false
}
</script>

<style lang="scss" scoped>
.pdf-template-select {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

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
