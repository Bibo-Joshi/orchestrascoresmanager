<template>
	<ContentStateWrapper
		:loading="loading"
		:error="loadError"
		:isEmpty="groupedFolderCollections.length === 0"
		:errorText="t('Failed to fetch folder collections. Please reload the page.')"
		:emptyText="t('Not in any folder collection')"
		:emptyDescription="emptyDescription">
		<template #emptyIcon>
			<FolderCollectionIcon :size="64" />
		</template>
		<VersionHistoryList
			:groups="groupedFolderCollections"
			:isExpanded="isExpanded"
			@toggle="toggleGroup" />
	</ContentStateWrapper>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import VersionHistoryList from '@/components/VersionHistoryList.vue'
import { useVersionHistory } from '@/composables/useVersionHistory'
import { FolderCollectionIcon } from '@/icons/vue-material'
import { t } from '@/utils/l10n'

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
