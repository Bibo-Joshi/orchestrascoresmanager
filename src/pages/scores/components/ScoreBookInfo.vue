<template>
	<ContentStateWrapper
		:loading="loading"
		:error="error || !scoreBook"
		:is-empty="false"
		:error-text="t('Failed to load score book')"
		:icon-size="32">
		<template #default>
			<!-- Score Book Title -->
			<div class="book-header">
				<h3 class="book-title">
					{{ scoreBook?.title }}
				</h3>
				<NcButton
					variant="tertiary"
					:aria-label="t('Go to score book')"
					:to="{ name: 'scorebook', params: { id: scoreBookId } }">
					<template #icon>
						<OpenExternalIcon :size="20" />
					</template>
				</NcButton>
			</div>

			<!-- Scores in Book -->
			<div class="scores-header">
				<h4>{{ t('Scores in this book') }}</h4>
			</div>

			<ul>
				<NcListItem
					v-for="score in scores"
					:key="score.id"
					:name="score.title"
					:bold="score.id === scoreId"
					:counter-number="score.scoreBook?.index ?? undefined"
					class="score-item"
					@click="handleScoreClick(score)">
					<template #subname>
						{{ score.composer || '' }}
					</template>
				</NcListItem>
			</ul>
		</template>
	</ContentStateWrapper>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { t } from '@/utils/l10n'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcButton from '@nextcloud/vue/components/NcButton'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import { OpenExternalIcon } from '@/icons/vue-material'
import { useScoreSidebarStore } from '@/stores/scoreSidebarStore'
import { useScoreBookSidebarStore } from '@/stores/scoreBookSidebarStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import type { Score } from '@/api/generated/openapi/data-contracts'

interface Props {
	scoreId: number
	scoreBookId: number
}

const props = defineProps<Props>()

const scoreSidebarStore = useScoreSidebarStore()
const scoreBookSidebarStore = useScoreBookSidebarStore()
const scoreBooksStore = useScoreBooksStore()

const loading = ref(false)
const error = ref(false)

const scoreBook = computed(() => scoreBooksStore.getScoreBookById(props.scoreBookId))
const scores = computed(() => scoreBooksStore.getScoreBookScores(props.scoreBookId))

/**
 * Load scores in the score book
 */
async function loadScoreBook() {
	loading.value = true
	error.value = false

	try {
		await scoreBooksStore.loadScoreBookScores(props.scoreBookId)
	} catch (e) {
		console.error('Failed to load score book info:', e)
		error.value = true
	} finally {
		loading.value = false
	}
}

/**
 * Handle click on a score in the list - switch sidebar to that score
 * @param score - The score that was clicked
 */
function handleScoreClick(score: Score) {
	// Close score book sidebar if open
	scoreBookSidebarStore.closeSidebar()
	scoreSidebarStore.openSidebar(score)
}

onMounted(() => {
	loadScoreBook()
})

watch(() => props.scoreBookId, () => {
	loadScoreBook()
})
</script>

<style lang="scss" scoped>
.book-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px;
}

.book-title {
	margin: 0;
	font-size: 1.1em;
	font-weight: 600;
}

.scores-header {
	padding: 8px;
	border-top: 1px solid var(--color-border);
	margin-top: 8px;

	h4 {
		margin: 0;
		font-size: 0.9em;
		color: var(--color-text-maxcontrast);
	}
}

.score-item {
	cursor: pointer;
}
</style>
