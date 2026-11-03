import type { NtsoPdfProfile } from '@/utils/ntsoPdfSettings'

import ncAxios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { cloneProfile, DEFAULT_NTSO_PROFILE, normalizeProfiles } from '@/utils/ntsoPdfSettings'

const url = () => generateOcsUrl('/apps/orchestrascoresmanager/settings/user/pdf-layouts')

interface OcsEnvelope {
	ocs: { data: { profiles: unknown } }
}

/** First use starts with NTSO; an intentionally empty saved profile list stays empty. */
export async function fetchPdfProfiles(): Promise<NtsoPdfProfile[]> {
	const response = await ncAxios.get<OcsEnvelope>(url())
	const stored = response.data.ocs.data.profiles
	return stored === null ? [cloneProfile(DEFAULT_NTSO_PROFILE)] : normalizeProfiles(stored)
}

/**
 * Save all profiles in one transaction. Server owns persistence, not browser localStorage.
 * @param profiles
 */
export async function putPdfProfiles(profiles: NtsoPdfProfile[]): Promise<NtsoPdfProfile[]> {
	const normalized = normalizeProfiles(profiles)
	const response = await ncAxios.put<OcsEnvelope>(url(), { profiles: normalized })
	return normalizeProfiles(response.data.ocs.data.profiles)
}
