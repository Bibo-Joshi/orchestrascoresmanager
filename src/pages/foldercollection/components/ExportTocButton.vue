<template>
	<NcButton variant="primary"
		:size="buttonSize"
		:text="buttonText(t('Export ToC & Index'))"
		@click="onExportClick">
		<template #icon>
			<DownloadIcon />
		</template>
	</NcButton>
</template>

<script setup lang="ts">
import { t } from '@/utils/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { DownloadIcon } from '@/icons/vue-material'
import { tryShowError } from '@/utils/errorHandling'
import { exportFolderCollectionToXlsx, type CollectionEntry } from '@/utils/fcv-xlsx-exporter'
import type { FolderCollection, FolderCollectionVersion } from '@/api/generated/openapi/data-contracts'
import { useBreakpoints } from '@/composables/useBreakpoints'

interface Props {
	folderCollection: FolderCollection
	version: FolderCollectionVersion
	entries: CollectionEntry[]
}

const props = defineProps<Props>()

const { buttonSize, buttonText } = useBreakpoints()

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
