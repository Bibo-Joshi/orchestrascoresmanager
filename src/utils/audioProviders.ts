import type { Component } from 'vue'
import type { ScoreAudio } from '@/api/generated/openapi/data-contracts'

import { AppleMusicIcon, AudioIcon, SpotifyIcon, YoutubeIcon } from '@/icons/vue-material'

export type AudioProviderId = 'youtube' | 'spotify' | 'apple-music' | 'deezer' | 'amazon-music'

export interface ParsedAudioSource {
	provider: AudioProviderId
	providerId: string
}

interface AudioProviderConfig {
	id: AudioProviderId
	name: string
	icon: Component
	/** Build the external URL from a provider-specific ID */
	buildUrl: (providerId: string) => string
	/** Extract the provider-specific ID from a parsed URL, or null if the URL does not belong to the provider */
	extractId: (url: URL) => string | null
}

const MAX_PROVIDER_ID_LENGTH = 255

/**
 * Split the path of a URL into non-empty segments.
 *
 * @param url - The URL to split
 */
function pathSegments(url: URL): string[] {
	return url.pathname.split('/').filter((segment) => segment !== '')
}

/**
 * Drop an optional locale segment like `intl-de` or `de` from the beginning of the path segments.
 *
 * @param segments - Path segments
 * @param pattern - Pattern matching the locale segment
 */
function dropLocale(segments: string[], pattern: RegExp): string[] {
	return segments.length > 0 && pattern.test(segments[0]) ? segments.slice(1) : segments
}

/**
 * Configuration of all supported providers. Adding a provider only requires a new entry here
 * (and the matching case in the backend enum) - no schema change.
 */
const PROVIDERS: AudioProviderConfig[] = [
	{
		id: 'youtube',
		name: 'YouTube',
		icon: YoutubeIcon,
		buildUrl: (id) => `https://www.youtube.com/watch?v=${id}`,
		extractId: (url) => {
			const host = url.hostname.replace(/^(www\.|m\.|music\.)/, '')
			let id: string | null = null
			if (host === 'youtu.be') {
				id = pathSegments(url)[0] ?? null
			} else if (host === 'youtube.com') {
				const segments = pathSegments(url)
				if (url.pathname === '/watch') {
					id = url.searchParams.get('v')
				} else if (['embed', 'shorts', 'live', 'v'].includes(segments[0])) {
					id = segments[1] ?? null
				}
			}
			return id !== null && /^[A-Za-z0-9_-]{11}$/.test(id) ? id : null
		},
	},
	{
		id: 'spotify',
		name: 'Spotify',
		icon: SpotifyIcon,
		buildUrl: (id) => `https://open.spotify.com/${id}`,
		extractId: (url) => {
			if (url.hostname !== 'open.spotify.com') {
				return null
			}
			const [type, id] = dropLocale(pathSegments(url), /^intl-[a-z]{2}(-[A-Za-z]+)?$/)
			if (['track', 'album', 'playlist', 'episode', 'show', 'artist'].includes(type) && /^[A-Za-z0-9]{22}$/.test(id ?? '')) {
				return `${type}/${id}`
			}
			return null
		},
	},
	{
		id: 'apple-music',
		name: 'Apple Music',
		icon: AppleMusicIcon,
		buildUrl: (id) => `https://music.apple.com/${id}`,
		extractId: (url) => {
			if (url.hostname !== 'music.apple.com') {
				return null
			}
			const segments = pathSegments(url)
			if (!/^[a-z]{2}$/.test(segments[0] ?? '') || !['album', 'playlist', 'song', 'artist', 'music-video'].includes(segments[1])) {
				return null
			}
			if (segments.length < 3 || segments.length > 4) {
				return null
			}
			const track = url.searchParams.get('i')
			const id = segments.join('/') + (track !== null && /^[0-9]+$/.test(track) ? `?i=${track}` : '')
			return /^[A-Za-z0-9_\-.~/?=&%]+$/.test(id) ? id : null
		},
	},
	{
		id: 'deezer',
		name: 'Deezer',
		icon: AudioIcon,
		buildUrl: (id) => id.startsWith('s/') ? `https://link.deezer.com/${id}` : `https://www.deezer.com/${id}`,
		extractId: (url) => {
			if (url.hostname === 'link.deezer.com') {
				// Short share links cannot be resolved to a content ID without network access, so they are kept as-is
				const [type, id] = pathSegments(url)
				return type === 's' && /^[A-Za-z0-9]+$/.test(id ?? '') ? `s/${id}` : null
			}
			if (url.hostname !== 'www.deezer.com' && url.hostname !== 'deezer.com') {
				return null
			}
			const [type, id] = dropLocale(pathSegments(url), /^[a-z]{2}(-[a-z]{2})?$/)
			if (['track', 'album', 'playlist', 'artist', 'episode', 'show'].includes(type) && /^[0-9]+$/.test(id ?? '')) {
				return `${type}/${id}`
			}
			return null
		},
	},
	{
		id: 'amazon-music',
		name: 'Amazon Music',
		icon: AudioIcon,
		buildUrl: (id) => `https://music.amazon.com/${id}`,
		extractId: (url) => {
			if (!/^music\.amazon\.[a-z]{2,3}(\.[a-z]{2})?$/.test(url.hostname)) {
				return null
			}
			const [type, id] = pathSegments(url)
			if (['albums', 'tracks', 'playlists', 'artists'].includes(type) && /^[A-Za-z0-9]+$/.test(id ?? '')) {
				return `${type}/${id}`
			}
			return null
		},
	},
]

/**
 * Parse a string as an http(s) URL.
 *
 * @param value - The string to parse
 * @return The parsed URL or null if the string is no http(s) URL
 */
function parseHttpUrl(value: string): URL | null {
	try {
		const url = new URL(value.trim())
		return url.protocol === 'http:' || url.protocol === 'https:' ? url : null
	} catch {
		return null
	}
}

/**
 * Check whether a string is a valid http(s) URL.
 *
 * @param value - The string to check
 */
export function isValidHttpUrl(value: string): boolean {
	return parseHttpUrl(value) !== null
}

/**
 * Detect a known provider and its provider-specific ID from a URL.
 *
 * @param value - The URL entered by the user
 * @return The detected source or null if the URL is not recognized as a known provider
 */
export function parseAudioUrl(value: string): ParsedAudioSource | null {
	const url = parseHttpUrl(value)
	if (url === null) {
		return null
	}
	for (const provider of PROVIDERS) {
		const providerId = provider.extractId(url)
		if (providerId !== null && providerId.length <= MAX_PROVIDER_ID_LENGTH) {
			return { provider: provider.id, providerId }
		}
	}
	return null
}

/**
 * Get the configuration of a provider by its identifier.
 *
 * @param provider - The provider identifier
 */
export function getAudioProvider(provider: string | null): AudioProviderConfig | null {
	return PROVIDERS.find((candidate) => candidate.id === provider) ?? null
}

/**
 * Get the external URL of an audio example. Provider-specific examples are mapped from provider and
 * provider ID, other examples use the stored link.
 *
 * @param audio - The audio example
 * @return The URL or null if the audio example cannot be mapped to a safe http(s) URL
 */
export function getAudioUrl(audio: ScoreAudio): string | null {
	const provider = getAudioProvider(audio.provider)
	const url = provider !== null && audio.providerId ? provider.buildUrl(audio.providerId) : audio.link
	return url && isValidHttpUrl(url) ? url : null
}

/**
 * Get the icon of an audio example. Unknown providers and generic links use the generic link icon.
 *
 * @param audio - The audio example
 */
export function getAudioIcon(audio: ScoreAudio): Component {
	return getAudioProvider(audio.provider)?.icon ?? AudioIcon
}

/**
 * Get a human-readable title of an audio example: the provider name or the host of the generic link.
 *
 * @param audio - The audio example
 */
export function getAudioTitle(audio: ScoreAudio): string {
	const provider = getAudioProvider(audio.provider)
	if (provider !== null) {
		return provider.name
	}
	return parseHttpUrl(audio.link ?? '')?.hostname ?? audio.link ?? audio.provider ?? ''
}
