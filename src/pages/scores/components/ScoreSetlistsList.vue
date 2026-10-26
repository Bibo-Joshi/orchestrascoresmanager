<template>
	<ContentStateWrapper
		:loading="isLoading"
		:error="!isLoading && !isLoaded"
		:isEmpty="setlists.length === 0"
		:errorText="t('Failed to fetch setlists. Please reload the page.')"
		:emptyText="t('Not used in any setlist')"
		:emptyDescription="t('This score has not appeared in any setlist')">
		<template #emptyIcon>
			<SetlistIcon :size="64" />
		</template>
		<p class="summary">
			{{ n('This score is part of %n setlist', 'This score is part of %n setlists', total) }}
		</p>
		<ul>
			<NcListItem
				v-for="setlist in setlists"
				:key="setlist.id"
				:name="setlist.title"
				:details="setlist.startDateTime ? formatDateTimeStr(setlist.startDateTime) : ''"
				:to="{ name: 'setlist', params: { id: setlist.id } }">
				<template v-if="setlist.isDraft" #indicator>
					<PencilCircleIcon :size="20" :title="t('Draft')" />
				</template>
			</NcListItem>
		</ul>
		<div v-if="hasMore" class="load-more">
			<NcButton :disabled="isLoadingMore" @click="emit('loadMore')">
				{{ t('Load more') }}
			</NcButton>
		</div>
	</ContentStateWrapper>
</template>

<script setup lang="ts">
import type { Setlist } from '@/api/generated/openapi/data-contracts'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import { formatDateTimeStr } from '@/composables/useDateFormatting'
import { PencilCircleIcon, SetlistIcon } from '@/icons/vue-material'
import { n, t } from '@/utils/l10n'

interface Props {
	setlists: Setlist[]
	total: number
	isLoading: boolean
	isLoaded: boolean
	isLoadingMore: boolean
	hasMore: boolean
}

defineProps<Props>()

const emit = defineEmits<{
	loadMore: []
}>()
</script>

<style lang="scss" scoped>
.summary {
	padding: 0 10px 10px;
	color: var(--color-text-maxcontrast);
}

.load-more {
	display: flex;
	justify-content: center;
	padding: 10px 0;
}
</style>
