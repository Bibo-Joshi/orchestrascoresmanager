import type { MaybeRefOrGetter } from 'vue'
import type { Setlist, SetlistEntry } from '@/api/generated/openapi/data-contracts'

import { ref, toValue } from 'vue'
import { useScoresStore } from '@/stores/scoresStore'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'
import { exportSetlistToGemaXlsx } from '@/utils/setlist-xlsx-exporter'

/**
 * Composable providing the GEMA report export of a setlist.
 *
 * @param setlist - The setlist to export (ref or getter)
 * @param entries - The entries of the setlist (ref or getter)
 * @return The `gemaExporting` state and the `exportGema` handler to be used by an action
 */
export function useGemaExport(
	setlist: MaybeRefOrGetter<Setlist | null | undefined>,
	entries: MaybeRefOrGetter<SetlistEntry[]>,
) {
	const scoresStore = useScoresStore()
	const gemaExporting = ref(false)

	/**
	 * Export the GEMA report as an XLSX file.
	 */
	async function exportGema(): Promise<void> {
		const currentSetlist = toValue(setlist)
		if (!currentSetlist) {
			return
		}
		gemaExporting.value = true
		await tryShowError(
			async () => {
				await exportSetlistToGemaXlsx({
					setlist: currentSetlist,
					entries: toValue(entries),
					getScoreById: (id) => scoresStore.getScoreById(id),
				})
			},
			t('Export failed: '),
		)
		gemaExporting.value = false
	}

	return { gemaExporting, exportGema }
}
