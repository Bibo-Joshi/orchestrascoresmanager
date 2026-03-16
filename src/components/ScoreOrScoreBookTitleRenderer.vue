<template>
	<div :style="containerStyle">
		<component
			:is="icon"
			v-if="showIcon"
			:size="16"
			:style="iconStyle" />
		<span :style="textStyle">{{ title }}</span>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { ScoreIcon, ScoreBookIcon } from '@/icons/vue-material'
import type { ICellRendererParams } from 'ag-grid-community'
import type { Score, ScoreIndexed } from '@/api/generated/openapi/data-contracts.ts'

interface Props {
	params: ICellRendererParams
}

const props = defineProps<Props>()

/**
 * Determine if we should show icons (only if at least one scorebook exists)
 */
const showIcon = computed(() => {
	return props.params.context?.hasScoreBooks ?? false
})

/**
 * Determine which icon to show based on whether score is a direct member or inherited
 */
const icon = computed(() => {
	const score = props.params.data as Score | ScoreIndexed

	if (score.viaScoreBook) {
		return ScoreBookIcon
	}

	return ScoreIcon
})

/**
 * Get the title text
 */
const title = computed(() => {
	return props.params.value || ''
})

// Inline styles to ensure they work with AG Grid
const containerStyle = {
	display: 'flex',
	alignItems: 'center',
	gap: '8px',
	height: '100%',
}

const iconStyle = {
	flexShrink: '0',
}

const textStyle = {
	flex: '1',
	overflow: 'hidden',
	textOverflow: 'ellipsis',
}
</script>
