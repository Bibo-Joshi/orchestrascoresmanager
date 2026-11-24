import type {
	SetlistTemplate,
	SetlistTemplateApiPatchTemplatePayload,
	SetlistTemplateApiPostTemplatePayload,
} from '@/api/generated/openapi/data-contracts'

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClients } from '@/api/client'

export const usePdfTemplatesStore = defineStore('pdfTemplates', () => {
	const templates = ref<SetlistTemplate[]>([])
	const isLoaded = ref(false)
	const isLoading = ref(false)

	let initialization: Promise<void> | undefined

	async function initialize(): Promise<void> {
		if (isLoaded.value) {
			return
		}

		if (initialization) {
			return initialization
		}

		isLoading.value = true

		initialization = apiClients.default.setlistTemplateApiGetTemplates()
			.then((response) => {
				templates.value = response.data.ocs.data
				isLoaded.value = true
			})
			.finally(() => {
				isLoading.value = false
				initialization = undefined
			})

		return initialization
	}

	function getTemplateById(id: number): SetlistTemplate | undefined {
		return templates.value.find((template) => template.id === id)
	}

	async function createTemplate(template: SetlistTemplateApiPostTemplatePayload['template']): Promise<SetlistTemplate> {
		const response = await apiClients.default.setlistTemplateApiPostTemplate({ template })
		const created = response.data.ocs.data
		templates.value.push(created)
		return created
	}

	async function updateTemplate(
		id: number,
		template: SetlistTemplateApiPatchTemplatePayload['template'],
	): Promise<SetlistTemplate> {
		const response = await apiClients.default.setlistTemplateApiPatchTemplate(id, { template })
		const updated = response.data.ocs.data
		const index = templates.value.findIndex((item) => item.id === id)

		if (index !== -1) {
			templates.value[index] = updated
		}

		return updated
	}

	async function deleteTemplate(id: number): Promise<void> {
		await apiClients.default.setlistTemplateApiDeleteTemplate(id)
		templates.value = templates.value.filter((template) => template.id !== id)
	}

	return {
		templates,
		isLoaded,
		isLoading,
		initialize,
		getTemplateById,
		createTemplate,
		updateTemplate,
		deleteTemplate,
	}
})
