<template>
	<div class="content-state-wrapper">
		<!-- Loading State -->
		<div v-if="loading" class="centered-state">
			<NcEmptyContent :name="loadingText">
				<template #icon>
					<NcLoadingIcon :size="iconSize" />
				</template>
			</NcEmptyContent>
		</div>

		<!-- Error State -->
		<div v-else-if="error" class="centered-state">
			<NcEmptyContent
				:name="errorText"
				:description="errorDescription">
				<template #icon>
					<slot name="error-icon">
						<ErrorIcon :size="iconSize" />
					</slot>
				</template>
			</NcEmptyContent>
		</div>

		<!-- Empty State with optional above-content shown based on showAboveContentOnEmpty -->
		<div v-else-if="isEmpty" class="state-container">
			<!-- Content above empty state (e.g., input forms) - only shown if showAboveContentOnEmpty is true -->
			<template v-if="showAboveContentOnEmpty && $slots['above-content']">
				<slot name="above-content" />
				<hr class="content-separator" aria-hidden="true">
			</template>
			<div class="centered-state">
				<NcEmptyContent
					:name="emptyText"
					:description="emptyDescription">
					<template #icon>
						<slot name="empty-icon">
							<InfoIcon :size="iconSize" />
						</slot>
					</template>
				</NcEmptyContent>
			</div>
		</div>

		<!-- Content (shown when not loading, no error, and not empty) -->
		<template v-else>
			<!-- Content above main content (e.g., input forms) -->
			<template v-if="$slots['above-content']">
				<slot name="above-content" />
				<hr class="content-separator" aria-hidden="true">
			</template>
			<slot />
		</template>
	</div>
</template>

<script setup lang="ts">
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { ErrorIcon, InfoIcon } from '@/icons/vue-material'
import { t } from '@/utils/l10n'

interface Props {
	/**
	 * Whether content is currently loading
	 */
	loading?: boolean
	/**
	 * Whether an error occurred while loading
	 */
	error?: boolean
	/**
	 * Whether the content is empty (no items to display)
	 */
	isEmpty?: boolean
	/**
	 * Text shown during loading state
	 */
	loadingText?: string
	/**
	 * Text shown when an error occurs
	 */
	errorText?: string
	/**
	 * Description text shown below error text
	 */
	errorDescription?: string
	/**
	 * Text shown when content is empty
	 */
	emptyText?: string
	/**
	 * Description text shown below empty text
	 */
	emptyDescription?: string
	/**
	 * Size of the state icons (loading, error, empty)
	 */
	iconSize?: number
	/**
	 * Whether to show the above-content slot when content is empty
	 */
	showAboveContentOnEmpty?: boolean
}

withDefaults(defineProps<Props>(), {
	loading: false,
	error: false,
	isEmpty: false,
	loadingText: () => t('Loading...'),
	errorText: () => t('An error occurred'),
	errorDescription: '',
	emptyText: () => t('No content'),
	emptyDescription: '',
	iconSize: 64,
	showAboveContentOnEmpty: false,
})
</script>

<style lang="scss" scoped>
.content-state-wrapper {
	height: 100%;
	display: flex;
	flex-direction: column;
}

.state-container {
	height: 100%;
	display: flex;
	flex-direction: column;
}

.centered-state {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
	min-height: 0;
}

.content-separator {
	border-bottom: 1px solid var(--color-border);
	margin: 10px 0;
	width: 100%;
}
</style>
