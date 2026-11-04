<template>
	<AddOrEditDialog
		v-model:isOpen="showCreateDialog"
		:name="t('Create score')"
		:isInputValid="isFormValid"
		@submit="handleSubmit"
		@reset="resetForm">
		<NcTextField v-model="inputNewScoreTitle" :label="t('Title')" required />
	</AddOrEditDialog>
</template>

<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { computed, ref } from 'vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AddOrEditDialog from '@/components/AddOrEditDialog.vue'
import { useScoresStore } from '@/stores/scoresStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

/** Whether the dialog is open */
const showCreateDialog = defineModel<boolean>('open', { default: false })

const scoresStore = useScoresStore()

const inputNewScoreTitle = ref('')

const isFormValid = computed(() => inputNewScoreTitle.value.trim().length > 0)

function resetForm() {
	inputNewScoreTitle.value = ''
}

async function handleSubmit() {
	const title = String(inputNewScoreTitle.value || '').trim()
	if (!title) {
		showError(t('Please enter a title'))
		return
	}

	await tryShowError(
		async () => {
			await scoresStore.createScore(title)
			showSuccess(t('Score created'))
			showCreateDialog.value = false
			resetForm()
		},
		t('Creating score failed: '),
	)
}
</script>
