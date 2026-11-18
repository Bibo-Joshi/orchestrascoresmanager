import ncAxios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Shared PDF templates are maintained by OSM editors and readable by all
 * authenticated users. They are not stored in per-user application settings.
 */
export interface PdfTemplateCondition {
	[key: string]: unknown
}

export interface PdfTemplateRule {
	id: string
	order: number
	label: string | null
	expression: PdfTemplateCondition
	backgroundColor: string | null
	cellPadding: number | null
}

export interface PdfTemplateFontSizes {
	title?: number
	subtitle?: number
	colorLegend?: number
	columnTitle?: number
	scoreRow?: number
	breakRow?: number
}

export interface PdfTemplateInput {
	name: string
	description: string | null
	fontSizes: PdfTemplateFontSizes
	margin: number
	cellPadding: number
	rules: PdfTemplateRule[]
}

export interface PdfTemplate extends PdfTemplateInput {
	id: string
	createdBy: string
	createdAt: string
	updatedAt: string
}

interface OcsEnvelope<T> {
	ocs: {
		meta: { statuscode: number, message?: string }
		data: T
	}
}

const endpoint = () => generateOcsUrl('/apps/orchestrascoresmanager/setlist-templates')

export async function fetchPdfTemplates(): Promise<PdfTemplate[]> {
	const response = await ncAxios.get<OcsEnvelope<PdfTemplate[]>>(endpoint())
	return response.data.ocs.data
}

export async function createPdfTemplate(template: PdfTemplateInput): Promise<PdfTemplate> {
	const response = await ncAxios.post<OcsEnvelope<PdfTemplate>>(endpoint(), { template })
	return response.data.ocs.data
}

export async function updatePdfTemplate(id: string, template: PdfTemplateInput): Promise<PdfTemplate> {
	const response = await ncAxios.patch<OcsEnvelope<PdfTemplate>>(`${endpoint()}/${encodeURIComponent(id)}`, { template })
	return response.data.ocs.data
}

export async function deletePdfTemplate(id: string): Promise<void> {
	await ncAxios.delete(`${endpoint()}/${encodeURIComponent(id)}`)
}
