/** PDF layout profiles. The built-in `standard` layout is never stored. */
export interface PdfTagColorRule {
	id: string
	label: string
	/** AND: '&', OR: '|'  (e.g. 'Male & Female | Duett') */
	expression: string
	color: string
}

export interface PdfLayoutSettings {
	titleFontSize: number
	sectionFontSize: number
	tableFontSize: number
	margin: number
	cellPadding: number
	breakPadding: number
	breakColor: string
	rules: PdfTagColorRule[]
}

export interface PdfLayoutProfile extends PdfLayoutSettings {
	id: string
	name: string
}

export const DEFAULT_PDF_PROFILE: PdfLayoutProfile = {
	id: 'stage',
	name: 'Stage layout',
	titleFontSize: 16,
	sectionFontSize: 11,
	tableFontSize: 8,
	margin: 8,
	cellPadding: 1.5,
	breakPadding: 3,
	breakColor: '#354354',
	rules: [],
}

export function cloneProfile(profile: PdfLayoutProfile): PdfLayoutProfile {
	return { ...profile, rules: profile.rules.map((rule) => ({ ...rule })) }
}

function bounded(value: unknown, fallback: number, min: number, max: number): number {
	const n = Number(value)
	return Number.isFinite(n) && n >= min && n <= max ? n : fallback
}

function color(value: unknown, fallback: string): string {
	return typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value) ? value.toUpperCase() : fallback
}

/**
 * Normalize untrusted responses and user input, preserve rule order (priority).
 * @param raw
 */
export function normalizeProfiles(raw: unknown): PdfLayoutProfile[] {
	const input = Array.isArray(raw) ? raw.slice(0, 12) : []
	const seen = new Set<string>()
	const result: PdfLayoutProfile[] = []
	for (const item of input) {
		if (!item || typeof item !== 'object') {
			continue
		}
		const p = item as Partial<PdfLayoutProfile>
		const id = typeof p.id === 'string' ? p.id.trim().slice(0, 64) : ''
		const name = typeof p.name === 'string' ? p.name.trim().slice(0, 80) : ''
		if (!id || !name || id === 'standard' || seen.has(id)) {
			continue
		}
		seen.add(id)
		const defaults = DEFAULT_PDF_PROFILE
		const rules = Array.isArray(p.rules)
			? p.rules.slice(0, 30).flatMap((rule, i) => {
					if (!rule || typeof rule !== 'object') {
						return []
					}
					const expression = typeof rule.expression === 'string' ? rule.expression.trim().slice(0, 240) : ''
					const label = typeof rule.label === 'string' ? rule.label.trim().slice(0, 60) : ''
					if (!expression || !label) {
						return []
					}
					return [{
						id: typeof rule.id === 'string' && rule.id ? rule.id.slice(0, 64) : String(i),
						label,
						expression,
						color: color(rule.color, '#FFFFFF'),
					}]
				})
			: []
		result.push({
			id,
			name,
			titleFontSize: bounded(p.titleFontSize, defaults.titleFontSize, 8, 36),
			sectionFontSize: bounded(p.sectionFontSize, defaults.sectionFontSize, 7, 24),
			tableFontSize: bounded(p.tableFontSize, defaults.tableFontSize, 6, 18),
			margin: bounded(p.margin, defaults.margin, 3, 30),
			cellPadding: bounded(p.cellPadding, defaults.cellPadding, 0.3, 8),
			breakPadding: bounded(p.breakPadding, defaults.breakPadding, 1, 10),
			breakColor: color(p.breakColor, defaults.breakColor),
			rules,
		})
	}
	return result
}

export function hexToRgb(hex: string): [number, number, number] {
	const safe = color(hex, '#FFFFFF')
	return [1, 3, 5].map((i) => Number.parseInt(safe.slice(i, i + 2), 16)) as [number, number, number]
}

/**
 * Exact, case-insensitive, tag-name matching. First matching color rule wins.
 * @param tags
 * @param expression
 */
export function matchesTags(tags: string[], expression: string): boolean {
	const names = new Set(tags.map((tag) => tag.trim().toLocaleLowerCase()))
	return expression.split('|').some((group) => {
		const required = group.split('&').map((tag) => tag.trim().toLocaleLowerCase()).filter(Boolean)
		return required.length > 0 && required.every((tag) => names.has(tag))
	})
}
