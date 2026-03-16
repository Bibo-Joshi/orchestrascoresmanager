<template>
	<Layout :title="scoreBook?.title">
		<template #content>
			<ContentStateWrapper
				:loading="loading"
				:error="loadError || !scoreBook"
				:is-empty="scores.length === 0"
				:error-text="t('Failed to load score book')"
				:error-description="t('Please check if the score book exists and try again.')"
				:empty-text="t('No scores in this book')"
				:empty-description="t('Add scores to this score book to see them here.')">
				<template #empty-icon>
					<ScoreIcon :size="64" />
				</template>
				<!-- Scores Table -->
				<ScoresTable
					v-if="scoreBook"
					ref="scoresTableRef"
					:editable="editable"
					:score-book-id="scoreBook.id" />
			</ContentStateWrapper>
		</template>

		<template #header-actions>
			<AddScoreToBookButton
				v-if="scoreBook"
				:editable="editable"
				:score-book-id="scoreBook.id"
				:existing-score-ids="existingScoreIds"
				:occupied-indices="occupiedIndices"
				@score-added="handleScoreAdded" />
			<ExportCsvButton :table-ref="scoresTableRef?.tableRef ?? null" />
		</template>

		<template #sidebar>
			<ScoreSidebar :editable="editable" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { loadState } from '@nextcloud/initial-state'
import Layout from '@/components/Layout.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import ScoresTable from '@/components/ScoresTable.vue'
import ExportCsvButton from '@/components/ExportCsvButton.vue'
import ScoreSidebar from '@/components/ScoreSidebar.vue'
import { ScoreIcon } from '@/icons/vue-material'
import AddScoreToBookButton from './components/AddScoreToBookButton.vue'
import { t } from '@/utils/l10n'
import { useScoresStore } from '@/stores/scoresStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useTagsStore } from '@/stores/tagsStore'
import type { Score, ScoreBook } from '@/api/generated/openapi/data-contracts'

const route = useRoute()
const scoresStore = useScoresStore()
const scoreBooksStore = useScoreBooksStore()
const tagsStore = useTagsStore()

const loading = ref(false)
const loadError = ref(false)
const editable = ref<boolean>(!!loadState('orchestrascoresmanager', 'editable'))

// Define interface for ScoresTable exposed methods
interface ScoresTableRef {
	tableRef: { exportAsCsv?: (fileName?: string) => boolean } | null
}

// Ref to access ScoresTable and its tableRef
const scoresTableRef = ref<ScoresTableRef | null>(null)

/**
 * Get score book from store by route parameter
 */
const scoreBook = computed<ScoreBook | undefined>(() => {
	const id = parseInt(route.params.id as string, 10)
	if (isNaN(id)) {
		return undefined
	}
	return scoreBooksStore.getScoreBookById(id)
})

/**
 * Get scores for the current scorebook from the cache
 */
const scores = computed<Score[]>(() => {
	if (!scoreBook.value) return []
	return scoreBooksStore.getScoreBookScores(scoreBook.value.id)
})

/**
 * Load scores for the current score book
 */
async function loadScores() {
	if (!scoreBook.value) {
		loadError.value = true
		return
	}

	loading.value = true
	loadError.value = false

	try {
		await scoreBooksStore.loadScoreBookScores(scoreBook.value.id)
	} catch (error) {
		console.error('Failed to load score book scores', error)
		loadError.value = true
	} finally {
		loading.value = false
	}
}

// Initialize stores and load scores when component is mounted
onMounted(async () => {
	await Promise.all([
		scoresStore.initialize(),
		scoreBooksStore.initialize(),
		tagsStore.initialize(),
	])
	await loadScores()
})

// Reload scores when route parameter changes
watch(() => route.params.id, () => {
	loadScores()
})

/**
 * Set of existing score IDs in the book (for disabling in dropdown)
 */
const existingScoreIds = computed<Set<number>>(() => {
	return new Set(scores.value.map(s => s.id))
})

/**
 * Set of occupied indices in the book
 */
const occupiedIndices = computed<Set<number>>(() => {
	return new Set(scores.value.map(s => s.scoreBook?.index).filter((i): i is number => i !== null && i !== undefined))
})

/**
 * Handle score added event
 * @param newScore - The newly added score
 */
function handleScoreAdded(newScore: Score) {
	// The score is already added to the cache by the store
	// Just need to update the scoresStore as well
	scoresStore.updateScoreBookAssignment(newScore.id, newScore.scoreBook!)
}
</script>
