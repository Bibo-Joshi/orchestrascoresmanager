<template>
	<NcButton v-if="editable" variant="primary" @click="showCreateDialog = true">
		<template #icon>
			<AddIcon />
		</template>
		{{ t('Add') }}
	</NcButton>

	<NcDialog
		v-model:open="showCreateDialog"
		:name="t('Create setlist')"
		@update:open="handleDialogClose">
		<template #default>
			<NcFormGroup hide-label>
				<NcTextField
					v-model="form.title"
					:label="t('Title')"
					required
					:error="titleError"
					@update:model-value="validateTitle" />

				<NcTextArea
					v-model="form.description"
					:label="t('Description')"
					:placeholder="t('Optional description')" />

				<NcDateTimePickerNative
					v-model="form.startDateTime"
					:label="t('Start date and time')"
					type="datetime-local" />

				<NcCheckboxRadioSwitch
					v-model="form.isDraft"
					type="switch">
					{{ t('Draft') }}
				</NcCheckboxRadioSwitch>

				<NcCheckboxRadioSwitch
					v-model="form.isPublished"
					type="switch">
					{{ t('Published') }}
				</NcCheckboxRadioSwitch>
			</NcFormGroup>
		</template>
		<template #actions>
			<NcButton @click="showCreateDialog = false">
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
				{{ t('Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { t } from '@/utils/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import { AddIcon, ConfirmIcon, CancelIcon } from '@/icons/vue-material'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import { useSetlistsStore } from '@/stores/setlistsStore'

interface Props {
	editable: boolean
}

defineProps<Props>()

const setlistsStore = useSetlistsStore()

const showCreateDialog = ref(false)
const titleError = ref(false)

const form = reactive({
	title: '',
	description: '',
	startDateTime: null as Date | null,
	isDraft: true,
	isPublished: false,
})

const isFormValid = computed(() => form.title.trim().length > 0)

function validateTitle() {
	titleError.value = form.title.trim().length === 0
}

function resetForm() {
	form.title = ''
	form.description = ''
	form.startDateTime = null
	form.isDraft = true
	form.isPublished = false
	titleError.value = false
}

function handleDialogClose(open: boolean) {
	if (!open) {
		resetForm()
	}
}

async function handleSubmit() {
	const title = String(form.title || '').trim()
	if (!title) {
		showError(t('Please enter a title'))
		titleError.value = true
		return
	}

	// Convert Date to ISO string for API, or null if not set
	let startDateTimeStr: string | null = null
	if (form.startDateTime) {
		startDateTimeStr = form.startDateTime.toISOString()
	}

	await tryShowError(
		async () => {
			await setlistsStore.createSetlist(
				title,
				form.description || null,
				startDateTimeStr,
				form.isDraft,
				form.isPublished,
			)
			showSuccess(t('Setlist created'))
			showCreateDialog.value = false
			resetForm()
		},
		t('Creating setlist failed: '),
	)
}
</script>
