import type { ScoreAudio, ScoreAudioApiPostScoreAudioExamplePayload } from '@/api/generated/openapi/data-contracts'

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClients } from '@/api/client'

/**
 * Pinia store for the audio examples of the currently displayed score.
 *
 * All API calls for audio examples are centralized here.
 */
export const useScoreAudiosStore = defineStore('scoreAudios', () => {
	const audios = ref<ScoreAudio[]>([])
	const currentScoreId = ref<number | null>(null)
	const isLoading = ref(false)
	const loadError = ref(false)

	// Guards against responses of an outdated score overwriting newer state
	let requestToken = 0

	/**
	 * Load the audio examples of a score, discarding previously loaded data.
	 *
	 * @param scoreId - ID of the score
	 */
	async function loadForScore(scoreId: number): Promise<void> {
		const token = ++requestToken
		currentScoreId.value = scoreId
		audios.value = []
		loadError.value = false
		isLoading.value = true
		try {
			const response = await apiClients.default.scoreAudioApiGetScoreAudioExamples(scoreId)
			if (token === requestToken) {
				audios.value = response.data.ocs.data
			}
		} catch (error) {
			if (token === requestToken) {
				console.error('Failed to fetch audio examples:', error)
				loadError.value = true
			}
		} finally {
			if (token === requestToken) {
				isLoading.value = false
			}
		}
	}

	/**
	 * Create an audio example via API and add it to the local state.
	 *
	 * @param scoreId - ID of the score
	 * @param payload - Source (link or provider with provider ID) and optional comment
	 * @return The created audio example
	 * @throws { Error } if the API call fails
	 */
	async function createAudio(scoreId: number, payload: ScoreAudioApiPostScoreAudioExamplePayload): Promise<ScoreAudio> {
		const response = await apiClients.default.scoreAudioApiPostScoreAudioExample(scoreId, payload)
		const created = response.data.ocs.data
		if (created.scoreId === currentScoreId.value) {
			audios.value.push(created)
		}
		return created
	}

	/**
	 * Delete an audio example via API and remove it from the local state.
	 *
	 * @param id - ID of the audio example
	 * @throws { Error } if the API call fails
	 */
	async function deleteAudio(id: number): Promise<void> {
		await apiClients.default.scoreAudioApiDeleteAudioExample(id)
		audios.value = audios.value.filter((audio) => audio.id !== id)
	}

	return {
		audios,
		isLoading,
		loadError,
		loadForScore,
		createAudio,
		deleteAudio,
	}
})
