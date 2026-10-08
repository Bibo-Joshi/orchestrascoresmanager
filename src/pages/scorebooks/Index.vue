<template>
	<Layout :title="t('Score Books')">
		<template #content>
			<ContentStateWrapper
				:loading="scoreBooksStore.isLoading"
				:isEmpty="scoreBooksStore.scoreBooks.length === 0"
				:emptyText="t('No score books yet')"
				:emptyDescription="t('Create your first score book to organize your scores')">
				<template #emptyIcon>
					<ScoreBookIcon :size="64" />
				</template>
				<ScoreBooksTable
					ref="scoreBooksTableRef"
					:editable="editable" />
			</ContentStateWrapper>
		</template>

		<template #headerActions>
			<AddScoreBookButton :editable="editable" />
			<ExportCsvButton :tableRef="scoreBooksTableRef?.tableRef ?? null" />
		</template>

		<template #sidebar>
			<ScoreBookSidebar :editable="editable" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { onMounted, ref } from 'vue'
import AddScoreBookButton from './components/AddScoreBookButton.vue'
import ScoreBookSidebar from './components/ScoreBookSidebar.vue'
import ScoreBooksTable from './components/ScoreBooksTable.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import ExportCsvButton from '@/components/ExportCsvButton.vue'
import Layout from '@/components/Layout.vue'
import { ScoreBookIcon } from '@/icons/vue-material'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useTagsStore } from '@/stores/tagsStore'
import { t } from '@/utils/l10n'

const scoreBooksStore = useScoreBooksStore()
const tagsStore = useTagsStore()

// Load initial state from the server
const editable = ref<boolean>(!!loadState('orchestrascoresmanager', 'editable'))

// Ref to access ScoreBooksTable and its tableRef
const scoreBooksTableRef = ref<{ tableRef: { exportAsCsv?: (fileName?: string) => boolean } | null } | null>(null)

// Initialize stores on mount
onMounted(async () => {
	await Promise.all([
		scoreBooksStore.initialize(),
		tagsStore.initialize(),
	])
})
</script>
