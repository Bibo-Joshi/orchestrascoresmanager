<template>
	<ContentStateWrapper
		:loading="loading"
		:error="error"
		:is-empty="scores.length === 0"
		:error-text="t('Failed to load scores')"
		:empty-text="t('No scores in this book')"
		:icon-size="32"
		:show-above-content-on-empty="true">
		<template #empty-icon>
			<ScoreIcon :size="32" />
		</template>
		<template #above-content>
			<!-- Header with button -->
			<div class="scores-header">
				<h4>{{ t('Show detailed list') }}</h4>
				<NcButton
					variant="tertiary"
					:aria-label="t('Go to score book')"
					:to="{ name: 'scorebook', params: { id: scoreBookId } }">
					<template #icon>
						<OpenExternalIcon :size="20" />
					</template>
				</NcButton>
			</div>
		</template>
		<ul>
			<NcListItem
				v-for="score in scores"
				:key="score.id"
				:name="score.title"
				:bold="false"
				:counter-number="score.scoreBook?.index ?? undefined"
				:to="{ name: 'scorebook', params: { id: scoreBookId } }">
				<template #subname>
					{{ score.composer || '' }}
				</template>
			</NcListItem>
		</ul>
	</ContentStateWrapper>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { t } from '@/utils/l10n'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcButton from '@nextcloud/vue/components/NcButton'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import { ScoreIcon, OpenExternalIcon } from '@/icons/vue-material'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'

interface Props {
	scoreBookId: number
}

const props = defineProps<Props>()

const scoreBooksStore = useScoreBooksStore()

const loading = ref(false)
const error = ref(false)

const scores = computed(() => scoreBooksStore.getScoreBookScores(props.scoreBookId))

async function loadScores() {
	loading.value = true
	error.value = false
	try {
		await scoreBooksStore.loadScoreBookScores(props.scoreBookId)
	} catch (e) {
		console.error('Failed to load score book scores:', e)
		error.value = true
	} finally {
		loading.value = false
	}
}

onMounted(() => {
	loadScores()
})

watch(() => props.scoreBookId, () => {
	loadScores()
})
</script>

<style lang="scss" scoped>
.scores-header {
	display: flex;
	align-items: center;
	justify-content: space-between;

	h4 {
		margin: 0;
		font-size: 0.9em;
		color: var(--color-text-maxcontrast);
	}
}
</style>
