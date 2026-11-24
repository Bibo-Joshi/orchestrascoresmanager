/**
 * Evaluate PDF formatting rules in priority order.
 */

import type { TopLevelCondition } from 'json-rules-engine'
import type { Setlist, SetlistEntry, SetlistTemplate } from '@/api/generated/openapi/data-contracts'

import { Engine } from 'json-rules-engine'

type PdfTemplateRule = SetlistTemplate['rules'][number]

interface RuleFacts {
	setlist: Setlist & { tags: string[] }
	setlistentry: SetlistEntry & { tags: string[] }
}

/**
 * Compile rules in priority order and return the first matching rule.
 * @param rules Template rules sorted by ascending order.
 */
export function createRuleMatcher(rules: PdfTemplateRule[]) {
	const compiled = rules.map((rule) => ({
		rule,
		engine: new Engine([{
			conditions: rule.expression as TopLevelCondition,
			event: { type: 'format-row' },
		}]),
	}))

	return async (facts: RuleFacts): Promise<PdfTemplateRule | undefined> => {
		for (const { rule, engine } of compiled) {
			const { events } = await engine.run({ ...facts })
			if (events.length) {
				return rule
			}
		}
	}
}
