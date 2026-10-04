<template>
	<NcButton :size="buttonSize"
		variant="primary"
		:text="buttonText(t('Export CSV'))"
		@click="onExportClick">
		<template #icon>
			<DownloadIcon />
		</template>
	</NcButton>
</template>

<script setup lang="ts">
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { DownloadIcon } from '@/icons/vue-material'
import { tryShowError } from '@/utils/errorHandling'
import { t } from '@/utils/l10n'

type TableExportRef = { exportAsCsv?: (fileName?: string) => boolean } | null

interface Props {
	tableRef: TableExportRef
}

const props = defineProps<Props>()

const { buttonSize, buttonText } = useBreakpoints()
function onExportClick() {
	// early check for export availability
	if (!(props.tableRef && typeof props.tableRef.exportAsCsv === 'function')) {
		showError(t('Export failed: ') + 'Export function not available')
		return
	}

	tryShowError(
		async () => {
			props.tableRef.exportAsCsv('orchestrascores.csv')
		},
		t('Export failed: '),
	)
}
</script>
