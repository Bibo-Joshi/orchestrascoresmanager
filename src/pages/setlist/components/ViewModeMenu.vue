<template>
	<NcActions
		:menu-name="buttonText(t('View Mode'))"
		:size="buttonSize"
		:primary="true"
		variant="primary"
		:force-name="true">
		<template #icon>
			<TableViewIcon :size="20" />
		</template>
		<NcActionRadio
			key="full"
			v-model="viewMode"
			value="full"
			name="view-mode">
			{{ t('Full View') }}
		</NcActionRadio>
		<NcActionRadio
			key="compact"
			v-model="viewMode"
			value="compact"
			name="view-mode">
			{{ t('Compact View') }}
		</NcActionRadio>
	</NcActions>
</template>

<script setup lang="ts">
import { defineModel, PropType } from 'vue'
import { t } from '@/utils/l10n'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import { TableViewIcon } from '@/icons/vue-material'
import { useBreakpoints } from '@/composables/useBreakpoints'

const { buttonSize, buttonText } = useBreakpoints()

const viewMode = defineModel('viewMode', {
	type: String as PropType<'full' | 'compact'>,
	default: 'full',
})

</script>
