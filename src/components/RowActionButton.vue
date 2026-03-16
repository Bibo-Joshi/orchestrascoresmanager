<template>
	<div class="row-action-button-container">
		<NcActions>
			<NcActionButton
				v-if="props.params.showInfoButton !== false"
				:close-after-click="true"
				:name="t('Details')"
				@click="handleInfoButton">
				<template #icon>
					<InfoIcon :size="20" />
				</template>
			</NcActionButton>
			<!-- Optional custom actions -->
			<NcActionButton
				v-for="(action, index) in props.params.customActions"
				:key="index"
				:close-after-click="action.closeAfterClick ?? true"
				:name="action.name"
				:href="getActionHref(action)"
				:target="action.target"
				@click="() => handleCustomActionClick(action)">
				<template #icon>
					<component :is="action.icon" :size="20" />
				</template>
			</NcActionButton>
			<NcActionButton
				v-if="props.params.showDeleteButton == true"
				:close-after-click="true"
				:name="props.params.deleteText || t('Delete')"
				@click="handleDeleteButton">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script setup lang="ts">
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { InfoIcon, DeleteIcon } from '@/icons/vue-material'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import { useScoreBookSidebarStore } from '@/stores/scoreBookSidebarStore'
import { useScoresStore } from '@/stores/scoresStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import type { Score, ScoreBook } from '@/api/generated/openapi/data-contracts'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import { t } from '@/utils/l10n'
import { tryShowError } from '@/utils/errorHandling'
import type { Component } from 'vue'
import { useRouter } from 'vue-router'

type ItemType = 'score' | 'scorebook'

/**
 * Custom action configuration for row action button
 */
type CustomAction = {
	/**
	 * Display name of the action
	 */
	name: string
	/**
	 * Icon component to display
	 */
	icon: Component
	/**
	 * Optional router location for navigation
	 * Can be an object or a function that returns a router location
	 * Preferred over href for internal navigation
	 */
	to?: { name: string; params?: Record<string, string | number> } | ((data: Score | ScoreBook) => { name: string; params?: Record<string, string | number> })
	/**
	 * Optional href for link actions
	 * Can be a string or a function that takes the row data and returns a string
	 * Use 'to' for internal navigation instead
	 */
	href?: string | ((data: Score | ScoreBook) => string)
	/**
	 * Optional target for link actions (e.g., '_blank')
	 */
	target?: string
	/**
	 * Optional click handler
	 * @param data - The row data
	 */
	onClick?: (data: Score | ScoreBook) => void
	/**
	 * Whether to close the actions menu after clicking
	 * @default true
	 */
	closeAfterClick?: boolean
}

type Params = {
	data: Score | ScoreBook
	type?: ItemType
	/**
	 * Whether to show the default info/details button
	 * @default true
	 */
	showInfoButton?: boolean
	/**
	 * Whether to show the delete button
	 */
	showDeleteButton?: boolean
	deleteText?: string
	/**
	 * Optional custom delete handler
	 * If provided, this will be called instead of the default delete behavior
	 * @return Promise that resolves when deletion is complete
	 */
	customDeleteHandler?: (data: Score | ScoreBook) => Promise<void>
	/**
	 * Optional custom actions to display between Details and Delete buttons
	 */
	customActions?: CustomAction[]
}

interface Props {
	params: Params
}

const props = defineProps<Props>()
const router = useRouter()
const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const scoreSidebarStore = useScoreSidebarStore()
const scoreBookSidebarStore = useScoreBookSidebarStore()

/**
 * Get the href value for an action
 * @param action - The custom action
 * @return The href string or undefined
 */
function getActionHref(action: CustomAction): string | undefined {
	// Don't return href if 'to' is specified (router navigation takes precedence)
	if (action.to) return undefined
	if (!action.href) return undefined
	if (typeof action.href === 'function') {
		return action.href(props.params.data)
	}
	return action.href
}

/**
 * Handle click on custom action
 * @param action - The custom action
 */
function handleCustomActionClick(action: CustomAction) {
	// Handle router navigation if 'to' is specified
	if (action.to) {
		const to = typeof action.to === 'function' ? action.to(props.params.data) : action.to
		router.push(to)
		return
	}

	// Handle custom onClick handler
	if (action.onClick) {
		action.onClick(props.params.data)
	}
}

function handleInfoButton() {
	const p = props.params

	const itemType = p.type || 'score'

	if (itemType === 'scorebook') {
		scoreBookSidebarStore.openSidebar(p.data as ScoreBook)
	} else {
		scoreSidebarStore.openSidebar(p.data as Score)
	}
}

async function handleDeleteButton() {
	const p = props.params

	const itemType = p.type || 'score'

	// If custom handler is provided, use it
	if (p.customDeleteHandler) {
		await tryShowError(
			async () => {
				await p.customDeleteHandler(p.data)
			},
			t('Operation failed: '),
		)
		return
	}

	const result = await spawnDialog(
		ConfirmationDialog,
		{
			title: itemType === 'scorebook' ? t('Delete Score Book') : t('Delete Score'),
			message: itemType === 'scorebook' ? t('Are you sure you want to delete this score book? This action cannot be undone.') : t('Are you sure you want to delete this score? This action cannot be undone.'),
		},
	)

	if (!result) {
		return
	}

	await tryShowError(
		async () => {
			if (itemType === 'scorebook') {
				await scoreBooksStore.deleteScoreBook((p.data as ScoreBook).id)
				scoreBookSidebarStore.closeSidebar()
			} else {
				await scoresStore.deleteScore((p.data as Score).id)
				scoreSidebarStore.closeSidebar()
			}
		},
		itemType === 'scorebook'
			? t('Failed to delete score book: ')
			: t('Failed to delete score: '),
	)
}
</script>

<style lang="scss" scoped>
.row-action-button-container {
	position: absolute;
	display: flex;
	align-items: center;
	height: 100%;
	width: 100%;
}
</style>
