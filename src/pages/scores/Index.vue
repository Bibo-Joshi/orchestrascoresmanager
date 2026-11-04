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
			<HeaderSearch v-model="searchText" />
			<HeaderActions>
				<NcActionButton
					v-if="editable"
					closeAfterClick
					@click="showAddDialog = true">
					<template #icon>
						<AddIcon :size="20" />
					</template>
					{{ t('Add') }}
				</NcActionButton>
				<NcActionButton
					closeAfterClick
					@click="exportCsv">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('Export CSV') }}
				</NcActionButton>
			</HeaderActions>
			<AddScoreDialog v-model:open="showAddDialog" />
		</template>

		<template #sidebar>
			<ScoreSidebar :editable="editable" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { onMounted, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import AddScoreDialog from './components/AddScoreDialog.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import HeaderActions from '@/components/HeaderActions.vue'
import HeaderSearch from '@/components/HeaderSearch.vue'
import Layout from '@/components/Layout.vue'
import ScoreSidebar from '@/components/ScoreSidebar.vue'
import ScoresTable from '@/components/ScoresTable.vue'
import { useCsvExport } from '@/composables/useCsvExport'
import { AddIcon, DownloadIcon, ScoreIcon } from '@/icons/vue-material'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useScoresStore } from '@/stores/scoresStore'
import { useTagsStore } from '@/stores/tagsStore'
import { t } from '@/utils/l10n'

const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const tagsStore = useTagsStore()
const searchText = ref<string | undefined>(undefined)

// Load initial state from the server
const editable = ref<boolean>(!!loadState('orchestrascoresmanager', 'editable'))

// Ref to access ScoresTable and its tableRef
const scoresTableRef = ref<{ tableRef: { exportAsCsv?: (fileName?: string) => boolean } | null } | null>(null)

const showAddDialog = ref(false)
const { exportCsv } = useCsvExport(() => scoresTableRef.value?.tableRef ?? null)

// Initialize stores on mount
onMounted(async () => {
	await Promise.all([
		scoresStore.initialize(),
		scoreBooksStore.initialize(),
		tagsStore.initialize(),
	])
})
</script>
