<template>
	<NcActions
		:ariaLabel="t('Actions')"
		:size="buttonSize"
		:primary="true"
		variant="primary"
		:forceName="isDesktop"
		:inline="inline ?? actionsInline">
		<slot />
	</NcActions>
</template>

<script setup lang="ts">
import NcActions from '@nextcloud/vue/components/NcActions'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { t } from '@/utils/l10n'

interface Props {
	/** Overrides the number of inline actions given by the breakpoints */
	inline?: number
}

defineProps<Props>()

/**
 * Single `NcActions` menu for the header actions of a page. Only `NcAction*`
 * components may be passed as default slot content.
 */
const { buttonSize, actionsInline, isDesktop } = useBreakpoints()
</script>
