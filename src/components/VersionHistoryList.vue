<template>
	<ul class="version-history-list">
		<!-- Folder Collection Header (Collapsible) -->
		<ExpandableListItem
			v-for="group in groups"
			:key="group.folderCollection.id"
			:name="group.folderCollection.title"
			:expanded="isExpanded(group.folderCollection.id)"
			:bold="true"
			:counter-number="group.activeIndex ?? undefined"
			:active="group.hasActiveVersion"
			@toggle="$emit('toggle', group.folderCollection.id)">
			<template #icon>
				<FolderCollectionIcon :size="20" />
			</template>
			<template #indicator>
				<CheckCircleIcon v-if="group.hasActiveVersion" :size="20" :title="t('Active')" />
				<HistoryIcon v-else :size="20" :title="t('History')" />
			</template>
			<template #subname>
				{{ group.folderCollection.description || '' }}
			</template>
			<!-- Nested Versions -->
			<template #nested>
				<ul>
					<NcListItem
						v-for="entry in group.versions"
						:key="entry.version.id"
						:name="formatVersionDateRange(entry.version)"
						:counter-number="entry.index ?? undefined"
						:bold="false"
						:active="entry.version.validTo === null"
						:to="{ name: 'foldercollection', params: { id: group.folderCollection.id }, query: { versionId: entry.version.id } }">
						<template #indicator>
							<CheckCircleIcon v-if="entry.version.validTo === null" :size="20" :title="t('Active')" />
							<HistoryIcon v-else :size="20" />
						</template>
					</NcListItem>
				</ul>
			</template>
		</ExpandableListItem>
	</ul>
</template>

<script setup lang="ts">
import NcListItem from '@nextcloud/vue/components/NcListItem'
import ExpandableListItem from '@/components/ExpandableListItem.vue'
import { FolderCollectionIcon, CheckCircleIcon, HistoryIcon } from '@/icons/vue-material'
import { t } from '@/utils/l10n'
import { formatVersionDateRange } from '@/composables/useDateFormatting'
import type { FolderCollectionGroup } from '@/composables/useVersionHistory'

defineProps<{
	groups: FolderCollectionGroup[]
	isExpanded: (fcId: number) => boolean
}>()

defineEmits<{
	toggle: [fcId: number]
}>()
</script>
