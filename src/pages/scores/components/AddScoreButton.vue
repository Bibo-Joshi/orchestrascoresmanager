<template>
	<NcButton
		v-if="editable"
		:size="buttonSize"
		variant="primary"
		:text="buttonText(t('Add'))"
		@click="showCreateDialog = true">
		<template #icon>
			<AddIcon />
		</template>
	</NcButton>

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
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AddOrEditDialog from '@/components/AddOrEditDialog.vue'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { AddIcon } from '@/icons/vue-material'
import { useScoresStore } from '@/stores/scoresStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

interface Props {
	editable: boolean
}

defineProps<Props>()

const scoresStore = useScoresStore()

const showCreateDialog = ref(false)
const inputNewScoreTitle = ref('')
const { buttonSize, buttonText } = useBreakpoints()

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
