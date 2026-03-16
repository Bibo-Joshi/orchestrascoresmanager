<template>
	<NcButton
		:aria-label="t('Start new version')"
		:disabled="disabled"
		@click="handleStartNewVersion">
		<template #icon>
			<HistoryIcon :size="20" />
		</template>
		{{ t('New Version') }}
	</NcButton>
</template>

<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import { HistoryIcon } from '@/icons/vue-material'
import StartNewVersionDialog from './StartNewVersionDialog.vue'
import { t } from '@/utils/l10n'
import { useFolderCollectionVersionsStore } from '@/stores/folderCollectionVersionsStore'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import type { FolderCollectionVersion } from '@/api/generated/openapi/data-contracts'

interface Props {
	folderCollectionId: number
	disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
	disabled: false,
})

const emit = defineEmits<{
	'version-created': [version: FolderCollectionVersion]
}>()

const versionsStore = useFolderCollectionVersionsStore()
const folderCollectionsStore = useFolderCollectionsStore()

/**
 * Handle start new version button click
 */
async function handleStartNewVersion() {
	// Get the current active version to determine the minimum date
	const activeVersion = versionsStore.getSelectedVersion(props.folderCollectionId)

	if (!activeVersion || !activeVersion.validFrom) {
		showError(t('Could not determine current version information'))
		return
	}

	const result = await spawnDialog(
		StartNewVersionDialog,
		{
			activeVersionValidFrom: activeVersion.validFrom,
		},
	)

	if (result.confirmed && result.validFrom) {
		await tryShowError(
			async () => {
				const newVersion = await versionsStore.startNewVersion(props.folderCollectionId, result.validFrom)
				// Update folder collection active version in store
				folderCollectionsStore.updateFolderCollectionActiveVersion(props.folderCollectionId, newVersion.id)
				showSuccess(t('New version started successfully'))
				emit('version-created', newVersion)
			},
			t('Failed to start new version: '),
		)
	}
}
</script>
