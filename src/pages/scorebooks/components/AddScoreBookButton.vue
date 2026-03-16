<template>
	<NcButton v-if="editable" variant="primary" @click="showCreateDialog = true">
		<template #icon>
			<AddIcon />
		</template>
		{{ t('Add') }}
	</NcButton>

	<AddOrEditDialog
		v-model:is-open="showCreateDialog"
		:name="t('Create score book')"
		:is-input-valid="isFormValid"
		@submit="handleSubmit"
		@reset="resetForm">
		<NcTextField v-model="inputNewScoreBookTitle" :label="t('Title')" required />
	</AddOrEditDialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { t } from '@/utils/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { AddIcon } from '@/icons/vue-material'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import AddOrEditDialog from '@/components/AddOrEditDialog.vue'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'

interface Props {
	editable: boolean
}

defineProps<Props>()

const scoreBooksStore = useScoreBooksStore()

const showCreateDialog = ref(false)
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
