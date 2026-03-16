<template>
	<NcContent app-name="orchestrascoresmanager">
		<Navigation />

		<NcAppContent>
			<div class="app-content-wrapper">
				<div class="app-content-header">
					<h1 class="app-content-header__title">
						{{ displayTitle }}
					</h1>
					<!-- Slot for right-aligned header actions (e.g. export button) -->
					<div class="app-content-header__actions">
						<slot name="header-actions" />
					</div>
				</div>

				<div class="content-area">
					<slot name="content" />
				</div>
			</div>
		</NcAppContent>

		<slot name="sidebar" />
	</NcContent>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import Navigation from './Navigation.vue'
import { generateFullTitle } from '@/navigation'

interface Props {
	title?: string | null
}

const props = withDefaults(defineProps<Props>(), {
	title: null,
})

// Compute display title from prop or fallback to app name
const displayTitle = computed((): string => {
	return props.title || 'Orchestra Scores Manager'
})

// Update document title when title prop changes
watch(() => props.title, (newTitle) => {
	document.title = generateFullTitle(newTitle)
}, { immediate: true })
</script>

<style lang="scss" scoped>
.app-content-wrapper {
	display: grid;
	grid-template-rows: min-content 1fr;
	height: 100%;
}

.app-content-header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	row-gap: 4px;
	padding-block: var(--app-navigation-padding, 4px);
	padding-inline: calc(var(--default-clickable-area, 44px) + 2 * var(--app-navigation-padding, 4px)) var(--app-navigation-padding, 4px);
	border-bottom: 1px solid var(--color-border);
	background-color: var(--color-main-background);
	position: sticky;
	top: 0;
	z-index: 100;
}

.app-content-header__title {
	margin: 0;
	font-size: 24px;
	font-weight: 600;
	color: var(--color-main-text);
	flex: 1 1 auto;
	min-width: 200px;
}

.app-content-header__actions {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.content-area {
	overflow-y: auto;
}
</style>
