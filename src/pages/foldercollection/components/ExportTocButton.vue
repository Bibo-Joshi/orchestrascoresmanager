<template>
	<NcButton variant="primary" @click="onExportClick">
		<template #icon>
			<DownloadIcon />
		</template>
		{{ t('Export ToC & Index') }}
	</NcButton>
</template>

<script setup lang="ts">
import { t } from '@/utils/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { DownloadIcon } from '@/icons/vue-material'
import { tryShowError } from '@/utils/errorHandling'
import { exportFolderCollectionToXlsx, type CollectionEntry } from '@/utils/xlsx-exporter'
import type { FolderCollection, FolderCollectionVersion } from '@/api/generated/openapi/data-contracts'

interface Props {
	folderCollection: FolderCollection
	version: FolderCollectionVersion
	entries: CollectionEntry[]
}

const props = defineProps<Props>()

/**
 * Handle export button click
 */
async function onExportClick(): Promise<void> {
	await tryShowError(
		async () => {
			await exportFolderCollectionToXlsx(
				props.folderCollection,
				props.version,
				props.entries,
			)
		},
		t('Export failed: '),
	)
}
</script>
