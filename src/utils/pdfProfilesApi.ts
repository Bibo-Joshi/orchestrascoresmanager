import type { PdfLayoutProfile } from '@/utils/pdfLayoutSettings'

import ncAxios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { cloneProfile, DEFAULT_PDF_PROFILE, normalizeProfiles } from '@/utils/pdfLayoutSettings'

const url = () => generateOcsUrl('/apps/orchestrascoresmanager/settings/user/pdf-layouts')

interface OcsEnvelope {
	ocs: { data: { profiles: unknown } }
}

/** First use offers a neutral layout; an intentionally empty saved profile list stays empty. */
export async function fetchPdfProfiles(): Promise<PdfLayoutProfile[]> {
	const response = await ncAxios.get<OcsEnvelope>(url())
	const stored = response.data.ocs.data.profiles
	return stored === null ? [cloneProfile(DEFAULT_PDF_PROFILE)] : normalizeProfiles(stored)
}

/**
 * Save all profiles in one transaction. Server owns persistence, not browser localStorage.
 * @param profiles
 */
export async function putPdfProfiles(profiles: PdfLayoutProfile[]): Promise<PdfLayoutProfile[]> {
	const normalized = normalizeProfiles(profiles)
	const response = await ncAxios.put<OcsEnvelope>(url(), { profiles: normalized })
	return normalizeProfiles(response.data.ocs.data.profiles)
}
