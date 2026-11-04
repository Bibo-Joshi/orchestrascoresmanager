import type { MaybeRefOrGetter } from 'vue'

import { showError } from '@nextcloud/dialogs'
import { toValue } from 'vue'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

export type TableExportRef = { exportAsCsv?: (fileName?: string) => boolean } | null

/**
 * Composable providing the CSV export action of a table.
 *
 * @param tableRef - The table (or a getter/ref of it) offering an `exportAsCsv` function
 * @return The `exportCsv` handler to be used by an action
 */
export function useCsvExport(tableRef: MaybeRefOrGetter<TableExportRef>) {
	/**
	 * Export the table content as CSV file
	 */
	function exportCsv(): void {
		const table = toValue(tableRef)
		// early check for export availability
		if (!(table && typeof table.exportAsCsv === 'function')) {
			showError(t('Export failed: ') + 'Export function not available')
			return
		}

		tryShowError(
			async () => {
				table.exportAsCsv('orchestrascores.csv')
			},
			t('Export failed: '),
		)
	}

	return { exportCsv }
}
