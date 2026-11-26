import type { Score, ScoreBook, ScoreBookIndexed, ScoreIndexed } from '@/api/generated/openapi/data-contracts'

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClients } from '@/api/client'
import { useFolderCollectionsStore } from '@/stores/folderCollectionsStore'
import { useScoreBooksStore } from '@/stores/scoreBooksStore'
import { useScoresStore } from '@/stores/scoresStore'

type MemberScore = Score | ScoreIndexed
type MemberScoreBook = ScoreBook | ScoreBookIndexed

interface Members {
	versionId: number | null
	scores: MemberScore[]
	scoreBooks: MemberScoreBook[]
}

/**
 * Pinia store for the members (scores and score books) of a folder collection version.
 *
 * The rows returned by the API are only used as membership information and for the
 * collection-specific fields (`index`, `viaScoreBook`, `scoreBook` of score book members).
 * The entity data is always resolved from the scores and score books stores, so edits
 * made anywhere in the app are reflected immediately.
 */
export const useFolderCollectionMembersStore = defineStore('folderCollectionMembers', () => {
	const scoresStore = useScoresStore()
	const scoreBooksStore = useScoreBooksStore()
	const folderCollectionsStore = useFolderCollectionsStore()

	// Members of the most recently loaded version, keyed by folder collection ID
	const membersByFolderCollection = ref<Record<number, Members>>({})

	/**
	 * Get the scores of a folder collection (direct members and those via score books), unsorted
	 *
	 * @param folderCollectionId - The folder collection ID
	 */
	function getScores(folderCollectionId: number): MemberScore[] {
		return (membersByFolderCollection.value[folderCollectionId]?.scores ?? []).map((member) => {
			const entity = scoresStore.getScoreById(member.id)
			if (!entity) {
				return member
			}
			const merged: MemberScore = { ...entity }
			if ('index' in member) {
				(merged as ScoreIndexed).index = member.index
			}
			if (member.viaScoreBook) {
				merged.viaScoreBook = true
				merged.scoreBook = member.scoreBook
			}
			return merged
		})
	}

	/**
	 * Get the score books of a folder collection, unsorted
	 *
	 * @param folderCollectionId - The folder collection ID
	 */
	function getScoreBooks(folderCollectionId: number): MemberScoreBook[] {
		return (membersByFolderCollection.value[folderCollectionId]?.scoreBooks ?? []).map((member) => {
			const entity = scoreBooksStore.getScoreBookById(member.id)
			if (!entity) {
				return member
			}
			return 'index' in member ? { ...entity, index: member.index } : { ...entity }
		})
	}

	/**
	 * Load the members of a folder collection version, replacing previously loaded members
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param versionId - The version ID, or null for the active version
	 * @throws { Error } if an API call fails
	 */
	async function loadMembers(folderCollectionId: number, versionId: number | null): Promise<void> {
		await Promise.all([scoresStore.initialize(), scoreBooksStore.initialize()])
		const params = { versionId: versionId ?? undefined }
		const [scoresResponse, scoreBooksResponse] = await Promise.all([
			apiClients.default.folderCollectionApiGetFolderCollectionScores(folderCollectionId, params),
			apiClients.default.folderCollectionApiGetFolderCollectionScoreBooks(folderCollectionId, params),
		])
		membersByFolderCollection.value[folderCollectionId] = {
			versionId,
			scores: (scoresResponse.data.ocs.data || []) as MemberScore[],
			scoreBooks: (scoreBooksResponse.data.ocs.data || []) as MemberScoreBook[],
		}
	}

	/**
	 * Drop the loaded members of a folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 */
	function clearMembers(folderCollectionId: number): void {
		delete membersByFolderCollection.value[folderCollectionId]
	}

	/**
	 * Add a score to the (active version of the) folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param score - The score to add
	 * @param index - The index within the collection (indexed collections only)
	 * @throws { Error } if API call fails
	 */
	async function addScore(folderCollectionId: number, score: Score, index?: number): Promise<void> {
		await apiClients.default.folderCollectionApiPostFolderCollectionScore(
			folderCollectionId,
			{ scoreId: score.id, ...(index !== undefined && { index }) },
		)
		const members = membersByFolderCollection.value[folderCollectionId]
		if (members) {
			members.scores.push(index !== undefined ? { ...score, index } : score)
		}
		folderCollectionsStore.incrementScoreCount(folderCollectionId)
	}

	/**
	 * Add a score book to the (active version of the) folder collection and reload the members,
	 * as the scores of the score book become members as well
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param scoreBookId - The ID of the score book to add
	 * @param index - The index within the collection (indexed collections only)
	 * @throws { Error } if an API call fails
	 */
	async function addScoreBook(folderCollectionId: number, scoreBookId: number, index?: number): Promise<void> {
		await apiClients.default.folderCollectionApiPostFolderCollectionScoreBook(
			folderCollectionId,
			{ scoreBookId, ...(index !== undefined && { index }) },
		)
		await loadMembers(folderCollectionId, membersByFolderCollection.value[folderCollectionId]?.versionId ?? null)
	}

	/**
	 * Remove a directly added score from the folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param scoreId - The ID of the score to remove
	 * @throws { Error } if API call fails
	 */
	async function removeScore(folderCollectionId: number, scoreId: number): Promise<void> {
		await apiClients.default.folderCollectionApiDeleteFolderCollectionScore(folderCollectionId, scoreId)
		const members = membersByFolderCollection.value[folderCollectionId]
		if (members) {
			members.scores = members.scores.filter((s) => s.id !== scoreId)
		}
		folderCollectionsStore.decrementScoreCount(folderCollectionId)
	}

	/**
	 * Remove a score book, including its scores, from the folder collection
	 *
	 * @param folderCollectionId - The folder collection ID
	 * @param scoreBookId - The ID of the score book to remove
	 * @throws { Error } if API call fails
	 */
	async function removeScoreBook(folderCollectionId: number, scoreBookId: number): Promise<void> {
		await apiClients.default.folderCollectionApiDeleteFolderCollectionScoreBook(folderCollectionId, scoreBookId)
		const members = membersByFolderCollection.value[folderCollectionId]
		if (!members) {
			return
		}
		const removed = members.scores.filter((s) => s.viaScoreBook && s.scoreBook?.id === scoreBookId)
		members.scoreBooks = members.scoreBooks.filter((sb) => sb.id !== scoreBookId)
		members.scores = members.scores.filter((s) => !removed.includes(s))
		removed.forEach(() => folderCollectionsStore.decrementScoreCount(folderCollectionId))
	}

	return {
		getScores,
		getScoreBooks,
		loadMembers,
		clearMembers,
		addScore,
		addScoreBook,
		removeScore,
		removeScoreBook,
	}
})
