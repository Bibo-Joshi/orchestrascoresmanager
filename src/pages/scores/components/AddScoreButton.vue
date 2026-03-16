<template>
	<NcButton v-if="editable" variant="primary" @click="showCreateDialog = true">
		<template #icon>
			<AddIcon />
		</template>
		{{ t('Add') }}
	</NcButton>

	<AddOrEditDialog
		v-model:is-open="showCreateDialog"
		:name="t('Create score')"
		:is-input-valid="isFormValid"
		@submit="handleSubmit"
		@reset="resetForm">
		<NcTextField v-model="inputNewScoreTitle" :label="t('Title')" required />
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
import { useScoresStore } from '@/stores/scoresStore'

interface Props {
	editable: boolean
}

defineProps<Props>()

const scoresStore = useScoresStore()

const showCreateDialog = ref(false)
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
