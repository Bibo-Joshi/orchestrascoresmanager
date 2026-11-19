import type { MaybeRefOrGetter } from 'vue'
import type { FolderCollection, FolderCollectionVersion } from '@/api/generated/openapi/data-contracts'
import type { CollectionEntry } from '@/utils/fcv-xlsx-exporter'

import { toValue } from 'vue'
import { tryShowError } from '@/utils/errorHandling'
import { exportFolderCollectionToXlsx } from '@/utils/fcv-xlsx-exporter'
import { t } from '@/utils/l10n'

interface TocExportSource {
	folderCollection: MaybeRefOrGetter<FolderCollection | null | undefined>
	version: MaybeRefOrGetter<FolderCollectionVersion | null | undefined>
	entries: MaybeRefOrGetter<CollectionEntry[]>
}

/**
 * Composable providing the "ToC & Index" XLSX export of a folder collection version.
 *
 * @param source - The folder collection, version and entries (refs or getters) to export
 * @return The `exportToc` handler to be used by an action
 */
export function useTocExport(source: TocExportSource) {
	/**
	 * Export the table of contents and index as XLSX file
	 */
	async function exportToc(): Promise<void> {
		const folderCollection = toValue(source.folderCollection)
		const version = toValue(source.version)
		if (!folderCollection || !version) {
			return
		}
		await tryShowError(
			async () => {
				await exportFolderCollectionToXlsx(
					folderCollection,
					version,
					toValue(source.entries),
				)
			},
			t('Export failed: '),
		)
	}

	return { exportToc }
}
