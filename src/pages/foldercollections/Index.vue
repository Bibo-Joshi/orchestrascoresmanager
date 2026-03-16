<template>
	<Layout :title="t('Folder Collections')">
		<template #content>
			<ContentStateWrapper
				:loading="folderCollectionsStore.isLoading"
				:is-empty="folderCollections.length === 0"
				:empty-text="t('No folder collections yet')"
				:empty-description="t('Create your first folder collection to organize your scores')">
				<template #empty-icon>
					<FolderCollectionIcon :size="64" />
				</template>
				<ul class="folder-collections-list">
					<NcListItem
						v-for="fc in folderCollections"
						:key="fc.id"
						:name="fc.title"
						:counter-number="fc.scoreCount || 0"
						:details="fc.collectionType === 'indexed' ? t('Indexed') : t('Alphabetical')"
						:to="{ name: 'foldercollection', params: { id: fc.id } }"
						:bold="false">
						<template #subname>
							{{ fc.description || '' }}
						</template>
						<template v-if="editable" #actions>
							<NcActionButton
								:aria-label="t('Delete folder collection')"
								@click="handleDeleteClick(fc)">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
								{{ t('Delete') }}
							</NcActionButton>
						</template>
					</NcListItem>
				</ul>
			</ContentStateWrapper>
		</template>

		<template #header-actions>
			<AddFolderCollectionButton :editable="editable" />
		</template>
	</Layout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import Layout from '@/components/Layout.vue'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { FolderCollectionIcon, DeleteIcon } from '@/icons/vue-material'
import AddFolderCollectionButton from './components/AddFolderCollectionButton.vue'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import { t } from '@/utils/l10n'
import { tryShowError } from '@/utils/errorHandling'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import type { FolderCollection } from '@/api/generated/openapi/data-contracts'

const folderCollectionsStore = useFolderCollectionsStore()

// Load initial state from the server
const editable = !!loadState('orchestrascoresmanager', 'editable')

// Use computed to maintain reactivity with the store
const folderCollections = computed(() => folderCollectionsStore.folderCollections)

// Initialize the store on mount
onMounted(() => {
	folderCollectionsStore.initialize()
})

async function handleDeleteClick(fc: FolderCollection) {
	const result = await spawnDialog(
		ConfirmationDialog,
		{
			title: t('Delete Folder Collection'),
			message: t('Are you sure you want to delete this folder collection? This action cannot be undone.'),
		},
	)

	if (result) {
		await tryShowError(
			async () => await folderCollectionsStore.deleteFolderCollection(fc.id),
			t('Failed to delete folder collection: '),
		)
	}
}
</script>

<style lang="scss" scoped>
.folder-collections-list {
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 8px;
}
</style>
