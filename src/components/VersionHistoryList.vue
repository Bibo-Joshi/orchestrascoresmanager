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
						v-for="entry in collapseVersions(group.versions)"
						:key="entry.lastVersion.id"
						:name="formatCollapsedVersionDateRange(entry)"
						:counter-number="entry.index ?? undefined"
						:bold="false"
						:active="entry.lastVersion.validTo === null"
						:to="{ name: 'foldercollection', params: { id: group.folderCollection.id }, query: { versionId: entry.lastVersion.id } }">
						<template #indicator>
							<CheckCircleIcon v-if="entry.lastVersion.validTo === null" :size="20" :title="t('Active')" />
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
import { formatCollapsedVersionDateRange } from '@/composables/useDateFormatting'
import type { FolderCollectionGroup, VersionEntry } from '@/composables/useVersionHistory'
import type { FolderCollectionVersion } from '@/api/generated/openapi/data-contracts'

defineProps<{
	groups: FolderCollectionGroup[]
	isExpanded: (fcId: number) => boolean
}>()

defineEmits<{
	toggle: [fcId: number]
}>()

interface CollapsedVersionEntry {
	firstVersion: FolderCollectionVersion
	lastVersion: FolderCollectionVersion
	index: number | null
}

function collapseVersions(versions: VersionEntry[]): CollapsedVersionEntry[] {
	const collapsed: CollapsedVersionEntry[] = []

	const DAY_MS = 24 * 60 * 60 * 1000
	const toTime = (date: string | null): number | null => (date === null ? null : new Date(date).getTime())

	// Work on a copy to avoid mutating reactive source data.
	const sorted = [...versions].sort(
		(a, b) => new Date(a.version.validFrom).getTime() - new Date(b.version.validFrom).getTime(),
	)

	let currentGroup: CollapsedVersionEntry | null = null

	for (const entry of sorted) {
		const version = entry.version

		if (currentGroup === null) {
			currentGroup = {
				firstVersion: version,
				lastVersion: version,
				index: entry.index ?? null,
			}
			continue
		}

		const sameIndex = (entry.index ?? null) === currentGroup.index
		const currentLastValidTo = toTime(currentGroup.lastVersion.validTo)
		const nextValidFrom = toTime(version.validFrom)

		const isConsecutiveOrOverlapping
			= currentLastValidTo === null
				? true
				: (nextValidFrom !== null && nextValidFrom <= currentLastValidTo + DAY_MS)

		if (sameIndex && isConsecutiveOrOverlapping) {
			currentGroup.lastVersion = version
		} else {
			collapsed.push(currentGroup)
			currentGroup = {
				firstVersion: version,
				lastVersion: version,
				index: entry.index ?? null,
			}
		}
	}

	if (currentGroup !== null) {
		collapsed.push(currentGroup)
	}

	// keep newest first in UI.
	return collapsed.reverse()
}

</script>
