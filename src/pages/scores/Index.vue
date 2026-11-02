<template>
	<Layout :title="t('Scores')">
		<template #content>
			<ContentStateWrapper
				:loading="scoresStore.isLoading"
				:isEmpty="scoresStore.scores.length === 0"
				:emptyText="t('No scores yet')"
				:emptyDescription="t('Create your first score to get started')">
				<template #emptyIcon>
					<ScoreIcon :size="64" />
				</template>
				<ScoresTable
					ref="scoresTableRef"
					:searchText="searchText"
					:editable="editable" />
			</ContentStateWrapper>
		</template>

		<template #headerActions>
			<div
				:style="{width: screenSizeCategory === 'mobile' ? '100%' : screenSizeCategory === 'tablet' ? '40ex' : '60ex'}">
				<NcTextField
					v-model="searchText"
					trailingButtonIcon="close"
					:placeholder="t('Search Score or Score details')"
					:showTrailingButton="searchText !== ''"
					@trailingButtonClick="searchText = undefined">
					<template #icon>
						<SearchIcon :size="20" />
					</template>
				</NcTextField>
			</div>
			<AddScoreButton :editable="editable" />
			<ExportCsvButton :tableRef="scoresTableRef?.tableRef ?? null" />
		</template>

		<template #sidebar>
			<ScoreSidebar :editable="editable" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { onMounted, ref } from 'vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AddScoreButton from './components/AddScoreButton.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import ExportCsvButton from '@/components/ExportCsvButton.vue'
import Layout from '@/components/Layout.vue'
import ScoreSidebar from '@/components/ScoreSidebar.vue'
import ScoresTable from '@/components/ScoresTable.vue'
import { useBreakpoints } from '@/composables/useBreakpoints'
import { ScoreIcon, SearchIcon } from '@/icons/vue-material'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useScoresStore } from '@/stores/scoresStore'
import { useTagsStore } from '@/stores/tagsStore'
import { t } from '@/utils/l10n'

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const tagsStore = useTagsStore()
const { screenSizeCategory } = useBreakpoints()
const searchText = ref<string | undefined>(undefined)

// Load initial state from the server
const editable = ref<boolean>(!!loadState('orchestrascoresmanager', 'editable'))

// Ref to access ScoresTable and its tableRef
const scoresTableRef = ref<{ tableRef: { exportAsCsv?: (fileName?: string) => boolean } | null } | null>(null)

// Initialize stores on mount
onMounted(async () => {
	await Promise.all([
		scoresStore.initialize(),
		scoreBooksStore.initialize(),
		tagsStore.initialize(),
	])
})
</script>
