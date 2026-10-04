<template>
	<NcActions
		:menuName="buttonText(t('View Mode'))"
		:size="buttonSize"
		:primary="true"
		variant="primary"
		:forceName="true">
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
import type { PropType } from 'vue'

import { defineModel } from 'vue'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActions from '@nextcloud/vue/components/NcActions'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { TableViewIcon } from '@/icons/vue-material'
import { t } from '@/utils/l10n'

const viewMode = defineModel('viewMode', {
	type: String as PropType<'full' | 'compact'>,
	default: 'full',
})

const { buttonSize, buttonText } = useBreakpoints()

</script>
