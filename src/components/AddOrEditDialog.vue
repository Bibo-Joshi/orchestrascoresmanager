<template>
	<NcDialog
		v-model:open="showDialog"
		:name="name"
		is-form
		:close-on-click-outside="true"
		:buttons="dialogButtons"
		@reset="handleReset">
		<NcLoadingIcon v-if="loading" :size="32" />
		<NcNoteCard v-else-if="error" type="error">
			{{ error }}
		</NcNoteCard>
		<NcFormGroup v-else hide-label>
			<slot />
		</NcFormGroup>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@/utils/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { CancelIconRaw, ConfirmIconRaw } from '@/icons/mdi'
import type { NcDialogButtonProps } from '@nextcloud/vue/components/NcDialog'

interface Props {
	/** Dialog title */
	name: string
	/** Whether the dialog is open */
	isOpen: boolean
	/** Whether data is loading */
	loading?: boolean
	/** Error message to display */
	error?: string
	/** Whether the form input is valid for submission */
	isInputValid?: boolean
	/** Label for the submit button */
	submitLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
	loading: false,
	error: '',
	isInputValid: true,
	submitLabel: '',
})

const emit = defineEmits<{
	'update:isOpen': [value: boolean]
	'submit': []
	'reset': []
}>()

const showDialog = computed({
	get: () => props.isOpen,
	set: (value) => emit('update:isOpen', value),
})

function handleReset() {
	emit('reset')
}

const dialogButtons = computed<NcDialogButtonProps[]>(() => [
	{
		label: t('Cancel'),
		variant: 'secondary',
		icon: CancelIconRaw,
	},
	{
		label: props.submitLabel || t('Create'),
		variant: 'primary',
		icon: ConfirmIconRaw,
		disabled: !props.isInputValid,
		callback: () => {
			emit('submit')
			// Return false to prevent auto-close - parent controls close via isOpen
			return false
		},
	},
])
</script>
