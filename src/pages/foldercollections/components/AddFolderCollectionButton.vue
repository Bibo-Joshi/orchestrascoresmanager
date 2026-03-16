<template>
	<NcButton v-if="editable" variant="primary" @click="showCreateDialog = true">
		<template #icon>
			<AddIcon />
		</template>
		{{ t('Add') }}
	</NcButton>

	<AddOrEditDialog
		v-model:is-open="showCreateDialog"
		:name="t('Create folder collection')"
		:is-input-valid="isFormValid"
		@submit="handleSubmit"
		@reset="resetForm">
		<NcTextField
			v-model="inputTitle"
			:label="t('Name')"
			required
			:placeholder="t('Enter a name')"
			:success="isNameValid"
			:error="!isNameValid"
			:helper-text="isNameValid ? '' : t('Name is required')" />
		<NcTextArea
			v-model="inputDescription"
			resize="none"
			:label="t('Description')"
			:placeholder="t('Enter a description (optional)')" />
		<NcSelect
			v-model="inputCollectionType"
			:options="collectionTypeOptions"
			:input-label="t('Collection type')"
			:placeholder="t('Select collection type')"
			:searchable="false"
			required />
	</AddOrEditDialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { t } from '@/utils/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { AddIcon } from '@/icons/vue-material'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import AddOrEditDialog from '@/components/AddOrEditDialog.vue'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'

interface Props {
	editable: boolean
}

defineProps<Props>()

const folderCollectionsStore = useFolderCollectionsStore()

interface CollectionTypeOption {
	label: string
	value: 'alphabetical' | 'indexed'
}

const showCreateDialog = ref(false)
const inputTitle = ref('')
const inputDescription = ref('')
const inputCollectionType = ref<CollectionTypeOption | null>(null)

const isNameValid = computed(() => inputTitle.value.trim().length > 0)
const isFormValid = computed(() => isNameValid.value && inputCollectionType.value !== null)

const collectionTypeOptions: CollectionTypeOption[] = [
	{ label: t('Alphabetical'), value: 'alphabetical' },
	{ label: t('Indexed'), value: 'indexed' },
]

function resetForm() {
	inputTitle.value = ''
	inputDescription.value = ''
	inputCollectionType.value = null
}

async function handleSubmit() {
	const title = String(inputTitle.value || '').trim()
	if (!title) {
		showError(t('Please enter a name'))
		return
	}
	if (!inputCollectionType.value) {
		showError(t('Please select a collection type'))
		return
	}

	await tryShowError(
		async () => {
			await folderCollectionsStore.createFolderCollection(
				title,
				inputCollectionType.value.value,
				inputDescription.value.trim() || null,
			)
			showSuccess(t('Folder collection created'))
			showCreateDialog.value = false
			resetForm()
		},
		t('Creating folder collection failed: '),
	)
}
</script>
