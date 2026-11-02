import type { MaybeRefOrGetter } from 'vue'
import type { FolderCollectionVersion } from '@/api/generated/openapi/data-contracts'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { toValue } from 'vue'
import StartNewVersionDialog from '../components/StartNewVersionDialog.vue'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import { useFolderCollectionVersionsStore } from '@/stores/folderCollectionVersionsStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

/**
 * Composable providing the "start new version" action of a folder collection.
 *
 * @param folderCollectionId - ID of the folder collection (ref or getter)
 * @param onVersionCreated - Callback invoked with the newly created version
 * @return The `startNewVersion` handler to be used by an action
 */
export function useStartNewVersion(
	folderCollectionId: MaybeRefOrGetter<number | undefined>,
	onVersionCreated: (version: FolderCollectionVersion) => void,
) {
	const versionsStore = useFolderCollectionVersionsStore()
	const folderCollectionsStore = useFolderCollectionsStore()

	/**
	 * Ask for the start date of the new version and create it
	 */
	async function startNewVersion(): Promise<void> {
		const id = toValue(folderCollectionId)
		if (id === undefined) {
			return
		}

		// Get the current active version to determine the minimum date
		const activeVersion = versionsStore.getSelectedVersion(id)

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
					const newVersion = await versionsStore.startNewVersion(id, result.validFrom)
					// Update folder collection active version in store
					folderCollectionsStore.updateFolderCollectionActiveVersion(id, newVersion.id)
					showSuccess(t('New version started successfully'))
					onVersionCreated(newVersion)
				},
				t('Failed to start new version: '),
			)
		}
	}

	return { startNewVersion }
}
