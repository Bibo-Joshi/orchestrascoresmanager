<template>
	<Layout :title="t('Setlists')">
		<template #content>
			<div class="setlists-page">
				<!-- Drafts Section - visible only to users with edit rights -->
				<template v-if="editable">
					<ExpandableListItem
						:name="t('Drafts')"
						:expanded="expandedSections.drafts"
						:counterNumber="setlistsStore.drafts.length"
						:bold="true"
						@toggle="expandedSections.drafts = !expandedSections.drafts">
						<template #nested>
							<ContentStateWrapper
								:isEmpty="setlistsStore.drafts.length === 0"
								:emptyText="t('No drafts')"
								:emptyDescription="t('Create your first setlist draft')">
								<template #emptyIcon>
									<SetlistIcon :size="64" />
								</template>
								<ul>
									<SetlistListItem
										v-for="setlist in setlistsStore.drafts"
										:key="setlist.id"
										:setlist="setlist"
										:editable="editable" />
								</ul>
							</ContentStateWrapper>
						</template>
					</ExpandableListItem>
				</template>

				<!-- Future Section -->
				<ExpandableListItem
					:name="t('Future')"
					:expanded="expandedSections.future"
					:counterNumber="setlistsStore.futureSetlists.length"
					:bold="true"
					@toggle="expandedSections.future = !expandedSections.future">
					<template #nested>
						<ContentStateWrapper
							:isEmpty="setlistsStore.futureSetlists.length === 0"
							:emptyText="t('No future setlists')"
							:emptyDescription="t('Schedule your next performances')">
							<template #emptyIcon>
								<SetlistIcon :size="64" />
							</template>
							<ul>
								<SetlistListItem
									v-for="setlist in setlistsStore.futureSetlists"
									:key="setlist.id"
									:setlist="setlist"
									:editable="editable" />
							</ul>
						</ContentStateWrapper>
					</template>
				</ExpandableListItem>

				<!-- Unscheduled Section -->
				<ExpandableListItem
					:name="t('Unscheduled')"
					:expanded="expandedSections.unscheduled"
					:counterNumber="setlistsStore.unscheduledSetlists.length"
					:bold="true"
					@toggle="expandedSections.unscheduled = !expandedSections.unscheduled">
					<template #nested>
						<ContentStateWrapper
							:isEmpty="setlistsStore.unscheduledSetlists.length === 0"
							:emptyText="t('No unscheduled setlists')"
							:emptyDescription="t('Setlists without a start date will appear here')">
							<template #emptyIcon>
								<SetlistIcon :size="64" />
							</template>
							<ul>
								<SetlistListItem
									v-for="setlist in setlistsStore.unscheduledSetlists"
									:key="setlist.id"
									:setlist="setlist"
									:editable="editable" />
							</ul>
						</ContentStateWrapper>
					</template>
				</ExpandableListItem>

				<!-- Past Section -->
				<ExpandableListItem
					:name="t('Past')"
					:expanded="expandedSections.past"
					:counterNumber="setlistsStore.pastSetlists.length"
					:bold="true"
					@toggle="expandedSections.past = !expandedSections.past">
					<template #nested>
						<ContentStateWrapper
							:isEmpty="setlistsStore.pastSetlists.length === 0"
							:emptyText="t('No past setlists')"
							:emptyDescription="t('Past performances will appear here')">
							<template #emptyIcon>
								<SetlistIcon :size="64" />
							</template>
							<ul>
								<SetlistListItem
									v-for="setlist in setlistsStore.pastSetlists"
									:key="setlist.id"
									:setlist="setlist"
									:editable="editable" />
							</ul>
						</ContentStateWrapper>
					</template>
				</ExpandableListItem>
			</div>
		</template>

		<template #headerActions>
			<AddSetlistButton :editable="editable" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { onMounted, reactive, ref } from 'vue'
import AddSetlistButton from './components/AddSetlistButton.vue'
import SetlistListItem from './components/SetlistListItem.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import ExpandableListItem from '@/components/ExpandableListItem.vue'
import Layout from '@/components/Layout.vue'
import { SetlistIcon } from '@/icons/vue-material'
import { useSetlistsStore } from '@/stores/setlistsStore'
import { t } from '@/utils/l10n'

const setlistsStore = useSetlistsStore()

// Load initial state from the server
const editable = ref<boolean>(!!loadState('orchestrascoresmanager', 'editable'))

// Track expanded state of each section
const expandedSections = reactive({
	drafts: true,
	future: true,
	unscheduled: false,
	past: false,
})

// Initialize stores on mount
onMounted(async () => {
	await setlistsStore.initialize()
})
</script>

<style lang="scss" scoped>
.setlists-page {
	padding: 16px;
}
</style>
