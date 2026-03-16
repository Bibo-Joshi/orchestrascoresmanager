<template>
	<Layout :title="t('Scores')">
		<template #content>
			<ContentStateWrapper
				:loading="scoresStore.isLoading"
				:is-empty="scoresStore.scores.length === 0"
				:empty-text="t('No scores yet')"
				:empty-description="t('Create your first score to get started')">
				<template #empty-icon>
					<ScoreIcon :size="64" />
				</template>
				<ScoresTable
					ref="scoresTableRef"
					:editable="editable" />
			</ContentStateWrapper>
		</template>

		<template #header-actions>
			<AddScoreButton :editable="editable" />
			<ExportCsvButton :table-ref="scoresTableRef?.tableRef ?? null" />
		</template>

		<template #sidebar>
			<ScoreSidebar :editable="editable" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import Layout from '@/components/Layout.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import ScoresTable from '@/components/ScoresTable.vue'
import AddScoreButton from './components/AddScoreButton.vue'
import ExportCsvButton from '@/components/ExportCsvButton.vue'
import ScoreSidebar from '@/components/ScoreSidebar.vue'
import { ScoreIcon } from '@/icons/vue-material'
import { useScoresStore } from '@/stores/scoresStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useTagsStore } from '@/stores/tagsStore'
import { t } from '@/utils/l10n'

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const tagsStore = useTagsStore()

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
