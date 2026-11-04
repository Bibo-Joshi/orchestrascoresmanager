<template>
	<AddOrEditDialog
		v-model:isOpen="showCreateDialog"
		:name="t('Create score book')"
		:isInputValid="isFormValid"
		@submit="handleSubmit"
		@reset="resetForm">
		<NcTextField v-model="inputNewScoreBookTitle" :label="t('Title')" required />
	</AddOrEditDialog>
</template>

<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { computed, ref } from 'vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AddOrEditDialog from '@/components/AddOrEditDialog.vue'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

/** Whether the dialog is open */
const showCreateDialog = defineModel<boolean>('open', { default: false })

const scoreBooksStore = useScoreBooksStore()

const inputNewScoreBookTitle = ref('')

const isFormValid = computed(() => inputNewScoreBookTitle.value.trim().length > 0)

function resetForm() {
	inputNewScoreBookTitle.value = ''
}

async function handleSubmit() {
	const title = String(inputNewScoreBookTitle.value || '').trim()
	if (!title) {
		showError(t('Please enter a title'))
		return
	}

	await tryShowError(
		async () => {
			await scoreBooksStore.createScoreBook(title)
			showSuccess(t('Score book created'))
			showCreateDialog.value = false
			resetForm()
		},
		t('Creating score book failed: '),
	)
}
</script>
