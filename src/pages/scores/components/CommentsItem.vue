<template>
	<div class="comment-item">
		<div class="comment-avatar">
			<NcAvatar
				:user="comment.author.userId"
				:size="32"
				:disable-menu="true"
				:show-user-status="false" />
		</div>
		<div class="comment-body">
			<div class="comment-header">
				<span class="comment-author">{{ comment.author.displayName ?? t('Unknown user') }}</span>
				<div class="comment-header-right">
					<NcDateTime
						:timestamp="comment.creationDate"
						class="comment-date" />
					<NcActions>
						<NcActionButton
							:aria-label="t('Delete comment')"
							@click="handleDeleteClick">
							<template #icon>
								<DeleteIcon :size="20" />
							</template>
							{{ t('Delete') }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>
			<NcRichText
				:text="comment.content"
				:autolink="true"
				:use-markdown="true"
				:use-extended-markdown="true"
				:interactive="false"
				class="comment-content" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/utils/l10n.ts'
import { tryShowError } from '@/utils/errorHandling'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { DeleteIcon } from '@/icons/vue-material'
import ConfirmationDialog from '@/components/ConfirmationDialog.vue'
import type { Comment } from '@/api/generated/openapi/data-contracts'
import { apiClients } from '@/api/client'

interface Props {
	comment: Comment
}

interface Emits {
	(e: 'comment-deleted', commentId: number): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const isDeleting = ref(false)

async function handleDeleteClick() {
	const result = await spawnDialog(
		ConfirmationDialog,
		{
			title: t('Delete Comment'),
			message: t('Are you sure you want to delete this comment? This action cannot be undone.'),
			countdown: 'short',
		},
	)

	if (result) {
		await deleteComment()
	}
}

async function deleteComment() {
	if (isDeleting.value) {
		return
	}

	isDeleting.value = true

	await tryShowError(
		async () => {
			await apiClients.default.commentApiDeleteComment(props.comment.id)
			// Emit event to parent to remove the comment from the list
			emit('comment-deleted', props.comment.id)
		},
		t('Failed to delete comment: '),
	)

	isDeleting.value = false
}

</script>

<style lang="scss" scoped>
.comment-item {
	display: flex;
	gap: 12px;
	padding: 12px 0;

	&:not(:last-child) {
		border-bottom: 1px solid var(--color-border);
	}
}

.comment-avatar {
	flex-shrink: 0;
}

.comment-body {
	flex: 1;
	min-width: 0;
}

.comment-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 4px;
}

.comment-header-right {
	display: flex;
	align-items: center;
	gap: 8px;
}

.comment-author {
	font-weight: bold;
}

.comment-date {
	color: var(--color-text-maxcontrast);
}

.comment-content {
	word-wrap: break-word;
}
</style>
