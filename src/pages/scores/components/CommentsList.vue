<template>
	<ContentStateWrapper
		:loading="loading"
		:error="loadError"
		:is-empty="comments.length === 0"
		:error-text="t('Failed to fetch comments. Please reload the page.')"
		:empty-text="t('No comments yet')"
		:show-above-content-on-empty="true">
		<template #empty-icon>
			<CommentIcon />
		</template>
		<template #above-content>
			<!-- New Comment Input -->
			<div class="new-comment-container">
				<NcTextArea
					v-model="newCommentContent"
					:placeholder="t('Write a new comment …')"
					:rows="1"
					resize="vertical"
					class="comment-textarea"
					@keydown.enter.exact.prevent />
				<div class="comment-actions">
					<p class="markdown-hint">
						{{ t('GitHub flavored markdown is supported.') }}
						<a href="https://github.github.com/gfm/" target="_blank" rel="noopener noreferrer">
							{{ t('Learn more') }}
							<OpenExternalIcon :size="12" />
						</a>
					</p>
					<NcButton
						:disabled="submitting || !newCommentContent.trim()"
						variant="primary"
						:aria-label="t('Submit comment')"
						@click="submitComment">
						<template #icon>
							<NcLoadingIcon v-if="submitting" :size="20" />
							<ConfirmIcon v-else :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
		</template>
		<template #default>
			<!-- Comments List -->
			<CommentsItem
				v-for="comment in comments"
				:key="comment.id"
				:comment="comment"
				@comment-deleted="handleCommentDeleted" />
		</template>
	</ContentStateWrapper>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { t } from '@/utils/l10n.ts'
import { showError } from '@nextcloud/dialogs'
import { tryShowError } from '@/utils/errorHandling'
import { getCurrentUser } from '@nextcloud/auth'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcButton from '@nextcloud/vue/components/NcButton'
import ContentStateWrapper from '@/components/ContentStateWrapper.vue'
import { CommentIcon, ConfirmIcon, OpenExternalIcon } from '@/icons/vue-material'
import CommentsItem from './CommentsItem.vue'
import type { Comment } from '@/api/generated/openapi/data-contracts'
import { apiClients } from '@/api/client'

interface Props {
	scoreId: number | null
}

const props = defineProps<Props>()

const comments = ref<Comment[]>([])
const loading = ref(false)
const loadError = ref(false)
const newCommentContent = ref('')
const submitting = ref(false)

async function fetchComments() {
	if (!props.scoreId) {
		comments.value = []
		loadError.value = false
		return
	}

	loading.value = true
	loadError.value = false
	try {
		const response = await apiClients.default.scoreApiGetScoreComments(props.scoreId)
		comments.value = response.data.ocs.data
	} catch (error) {
		console.error('Failed to fetch comments:', error)
		comments.value = []
		loadError.value = true
	} finally {
		loading.value = false
	}
}

async function submitComment() {
	if (!newCommentContent.value.trim() || !props.scoreId) {
		return
	}

	const currentUser = getCurrentUser()
	if (!currentUser) {
		showError(t('Unable to determine current user'))
		return
	}

	submitting.value = true

	await tryShowError(
		async () => {
			const response = await apiClients.default.scoreApiPostScoreComment(
				String(props.scoreId),
				{
					content: newCommentContent.value.trim(),
					userId: currentUser.uid,
					creationDate: Date.now(),
				},
			)

			// Add the new comment to the list at first position
			comments.value.unshift(response.data.ocs.data)

			// Clear the input
			newCommentContent.value = ''
		},
		t('Failed to submit comment: '),
	)

	submitting.value = false
}

function handleCommentDeleted(commentId: number) {
	comments.value = comments.value.filter(comment => comment.id !== commentId)
}

// Watch for scoreId changes and fetch comments
watch(() => props.scoreId, () => {
	// Clear the input when switching to a different score
	newCommentContent.value = ''
	fetchComments()
}, { immediate: true })
</script>

<style lang="scss" scoped>
.new-comment-container {
	display: block;

	:deep(.textarea__main-wrapper) {
		height: auto !important;
	}
}

.comment-actions {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
}

.markdown-hint {
	margin: 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	flex: 1;

	.material-design-icon {
		vertical-align: middle;
		display: inline-block;
	}
}
</style>
