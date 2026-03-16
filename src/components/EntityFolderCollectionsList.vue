<template>
	<ContentStateWrapper
		:loading="loading"
		:error="loadError"
		:is-empty="groupedFolderCollections.length === 0"
		:error-text="t('Failed to fetch folder collections. Please reload the page.')"
		:empty-text="t('Not in any folder collection')"
		:empty-description="emptyDescription">
		<template #empty-icon>
			<FolderCollectionIcon :size="64" />
		</template>
		<VersionHistoryList
			:groups="groupedFolderCollections"
			:is-expanded="isExpanded"
			@toggle="toggleGroup" />
	</ContentStateWrapper>
</template>

<script setup lang="ts">
import { watch, computed } from 'vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import VersionHistoryList from '@/components/VersionHistoryList.vue'
import { FolderCollectionIcon } from '@/icons/vue-material'
import { t } from '@/utils/l10n'
import { useVersionHistory } from '@/composables/useVersionHistory'

type EntityType = 'score' | 'scorebook'

const props = defineProps<{
	/** Entity type: 'score' or 'scorebook' */
	type: EntityType
	/** ID of the entity (score or scorebook) */
	entityId: number
}>()

const {
	loading,
	loadError,
	groupedFolderCollections,
	load,
	toggleGroup,
	isExpanded,
} = useVersionHistory(props.type)

/**
 * Empty description text based on entity type
 */
const emptyDescription = computed(() => {
	return props.type === 'score'
		? t('This score is not part of any folder collection')
		: t('This score book is not part of any folder collection')
})

// Load folder collections when entityId changes
watch(() => props.entityId, () => {
	load(props.entityId)
}, { immediate: true })
</script>
