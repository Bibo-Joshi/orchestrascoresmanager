<template>
	<Layout :title="t('Score Books')">
		<template #content>
			<ContentStateWrapper
				:loading="scoreBooksStore.isLoading"
				:is-empty="scoreBooksStore.scoreBooks.length === 0"
				:empty-text="t('No score books yet')"
				:empty-description="t('Create your first score book to organize your scores')">
				<template #empty-icon>
					<ScoreBookIcon :size="64" />
				</template>
				<ScoreBooksTable
					ref="scoreBooksTableRef"
					:editable="editable" />
			</ContentStateWrapper>
		</template>

		<template #header-actions>
			<AddScoreBookButton :editable="editable" />
			<ExportCsvButton :table-ref="scoreBooksTableRef?.tableRef ?? null" />
		</template>

		<template #sidebar>
			<ScoreBookSidebar :editable="editable" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import Layout from '@/components/Layout.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import ScoreBooksTable from './components/ScoreBooksTable.vue'
import AddScoreBookButton from './components/AddScoreBookButton.vue'
import ExportCsvButton from '@/components/ExportCsvButton.vue'
import ScoreBookSidebar from './components/ScoreBookSidebar.vue'
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
