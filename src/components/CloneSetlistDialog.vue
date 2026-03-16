<template>
	<NcDialog
		v-model:open="showDialog"
		:name="t('Clone setlist')"
		is-form
		:close-on-click-outside="true"
		:buttons="dialogButtons"
		@reset="handleReset">
		<NcFormGroup hide-label>
			<NcTextField
				v-model="title"
				:label="t('Title')"
				required
				:error="titleError"
				@update:model-value="validateTitle" />
		</NcFormGroup>
	</NcDialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@/utils/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { CancelIconRaw, ConfirmIconRaw } from '@/icons/mdi'
import type { NcDialogButtonProps } from '@nextcloud/vue/components/NcDialog'
import { showSuccess } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import { useSetlistsStore } from '@/stores/setlistsStore'

interface Props {
	/** Whether the dialog is open */
	isOpen: boolean
	/** The ID of the setlist to clone */
	setlistId: number
}

const props = defineProps<Props>()

const emit = defineEmits<{
	'update:isOpen': [value: boolean]
}>()

const router = useRouter()
const setlistsStore = useSetlistsStore()

const title = ref('')
const titleError = ref(false)

const showDialog = computed({
	get: () => props.isOpen,
	set: (value) => emit('update:isOpen', value),
})

function validateTitle() {
	titleError.value = title.value.trim().length === 0
}

function handleReset() {
	title.value = ''
	titleError.value = false
}

const dialogButtons = computed<NcDialogButtonProps[]>(() => [
	{
		label: t('Cancel'),
		variant: 'secondary',
		icon: CancelIconRaw,
	},
	{
		label: t('Clone'),
		variant: 'primary',
		icon: ConfirmIconRaw,
		disabled: title.value.trim().length === 0,
		callback: () => {
			handleSubmit()
			return false
		},
	},
])

async function handleSubmit() {
	const trimmedTitle = title.value.trim()
	if (!trimmedTitle) {
		titleError.value = true
		return
	}

	await tryShowError(
		async () => {
			const cloned = await setlistsStore.cloneSetlist(props.setlistId, trimmedTitle)
			showSuccess(t('Setlist cloned'))
			showDialog.value = false
			handleReset()
			await router.push({ name: 'setlist', params: { id: cloned.id } })
		},
		t('Cloning setlist failed: '),
	)
}
</script>
