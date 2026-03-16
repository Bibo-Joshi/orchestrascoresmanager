<template>
	<NcButton variant="primary" @click="onExportClick">
		<template #icon>
			<DownloadIcon />
		</template>
		{{ t('Export CSV') }}
	</NcButton>
</template>

<script setup lang="ts">
import { t } from '@/utils/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { DownloadIcon } from '@/icons/vue-material'
import { showError } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'

type TableExportRef = { exportAsCsv?: (fileName?: string) => boolean } | null

interface Props {
	tableRef: TableExportRef
}

const props = defineProps<Props>()

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
