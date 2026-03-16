<template>
	<!-- Header row -->
	<NcListItem
		:name="name"
		:bold="bold"
		:counter-number="counterNumber"
		:details="details"
		:to="to"
		:active="active"
		@click.prevent="handleClick">
		<template #icon>
			<ExpandedIcon v-if="expanded" :size="20" />
			<CollapsedIcon v-else :size="20" />
			<slot name="icon" />
		</template>
		<template v-if="$slots.subname" #subname>
			<slot name="subname" />
		</template>
		<template v-if="$slots.indicator" #indicator>
			<slot name="indicator" />
		</template>
		<template v-if="$slots.actions" #actions>
			<slot name="actions" />
		</template>
	</NcListItem>

	<!-- Nested content (if expanded) -->
	<ul v-if="expanded" class="nested-content">
		<slot name="nested" />
	</ul>
</template>

<script setup lang="ts">
import NcListItem from '@nextcloud/vue/components/NcListItem'
import { ExpandedIcon, CollapsedIcon } from '@/icons/vue-material'
import type { RouteLocationRaw } from 'vue-router'

interface Props {
	name: string
	expanded?: boolean
	bold?: boolean
	counterNumber?: number
	details?: string
	to?: RouteLocationRaw
	active?: boolean
}

withDefaults(defineProps<Props>(), {
	expanded: false,
	bold: false,
	counterNumber: undefined,
	details: undefined,
	to: undefined,
	active: false,
})

const emit = defineEmits<{
	toggle: []
}>()

/**
 * Handle click on the list item - emits toggle event
 */
function handleClick() {
	emit('toggle')
}
</script>

<style lang="scss" scoped>
.nested-content {
	margin-left: 40px;
}
</style>
