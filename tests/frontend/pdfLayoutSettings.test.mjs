/**
 * Dependency-free unit tests for the PDF layout profile helpers.
 * Run from the OSM repository root:
 *   npm run test:frontend
 *
 * Uses esbuild to bundle the TypeScript dependencies before running with Node.js.
 */

import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import {
  cloneProfile,
  DEFAULT_PDF_PROFILE,
  hexToRgb,
  matchesTags,
  normalizeProfiles,
} from '../../src/utils/pdfLayoutSettings.ts'

function profile(overrides = {}) {
  return {
    ...structuredClone(DEFAULT_PDF_PROFILE),
    id: 'test-layout',
    name: 'Test layout',
    ...overrides,
  }
}

describe('matchesTags – color rule expressions', () => {
  it('matches a single tag exactly', () => {
    assert.equal(matchesTags(['Male', 'Vocal'], 'Male'), true)
    assert.equal(matchesTags(['Female'], 'Male'), false)
    assert.equal(matchesTags(['Female'], 'Male & Female'), false)
  })

  it('is case-insensitive and trims tag whitespace', () => {
    assert.equal(matchesTags(['  female ', 'VOCAL'], ' Female & vocal '), true)
  })

  it('requires every tag in an AND expression', () => {
    assert.equal(matchesTags(['Male', 'Female', 'Christmas'], 'Male & Female'), true)
    assert.equal(matchesTags(['Male', 'Christmas'], 'Male & Female'), false)
  })

  it('accepts either side of an OR expression', () => {
    assert.equal(matchesTags(['Duet'], 'Duett | Duet'), true)
    assert.equal(matchesTags(['Duett'], 'Duett | Duet'), true)
    assert.equal(matchesTags(['Instrumental'], 'Duett | Duet'), false)
  })

  it('evaluates AND within each OR alternative', () => {
    assert.equal(matchesTags(['Male', 'Female'], 'Male & Female | Duett'), true)
    assert.equal(matchesTags(['Duett'], 'Male & Female | Duett'), true)
    assert.equal(matchesTags(['Male'], 'Male & Female | Duett'), false)
  })

  it('does not use substring matching', () => {
    assert.equal(matchesTags(['Male Vocal'], 'Male'), false)
    assert.equal(matchesTags(['Instrumental'], 'Mental'), false)
  })

  it('does not match empty expressions or empty alternatives', () => {
    assert.equal(matchesTags(['Male'], ''), false)
    assert.equal(matchesTags(['Male'], ' | & '), false)
    assert.equal(matchesTags([], 'Male'), false)
  })
})

describe('hexToRgb – PDF color conversion', () => {
  it('converts valid hexadecimal colors to RGB channels', () => {
    assert.deepEqual(hexToRgb('#FFE0B2'), [255, 224, 178])
    assert.deepEqual(hexToRgb('#00aBcD'), [0, 171, 205])
    assert.deepEqual(hexToRgb('#000000'), [0, 0, 0])
  })

  it('uses a safe white fallback for invalid colors', () => {
    assert.deepEqual(hexToRgb('red'), [255, 255, 255])
    assert.deepEqual(hexToRgb('#FFF'), [255, 255, 255])
    assert.deepEqual(hexToRgb('#GGGGGG'), [255, 255, 255])
  })
})

describe('cloneProfile – independent editor state', () => {
  it('clones nested rules rather than sharing references', () => {
    const source = profile({
      rules: [{ id: 'a', label: 'Duet', expression: 'Male & Female', color: '#FFE0B2' }],
    })
    const cloned = cloneProfile(source)
    cloned.name = 'Changed'
    cloned.rules[0].color = '#000000'
    assert.equal(source.name, 'Test layout')
    assert.equal(source.rules[0].color, '#FFE0B2')
  })
})

describe('normalizeProfiles – server response and input validation', () => {
  it('returns an empty list for missing/invalid data and preserves an intentionally empty list', () => {
    assert.deepEqual(normalizeProfiles(null), [])
    assert.deepEqual(normalizeProfiles({ profiles: [] }), [])
    assert.deepEqual(normalizeProfiles([]), [])
  })

  it('normalizes a valid profile and retains rule order', () => {
    const input = profile({
      name: '  Stage  ',
      rules: [
        { id: 'duet', label: 'Duet', expression: ' Male & Female ', color: '#ffe0b2' },
        { id: 'male', label: 'Male', expression: 'Male', color: '#dceeff' },
      ],
    })
    const [result] = normalizeProfiles([input])
    assert.equal(result.name, 'Stage')
    assert.deepEqual(result.rules.map(r => r.id), ['duet', 'male'])
    assert.deepEqual(result.rules.map(r => r.expression), ['Male & Female', 'Male'])
    assert.deepEqual(result.rules.map(r => r.color), ['#FFE0B2', '#DCEEFF'])
  })

  it('skips invalid, reserved or duplicate layout IDs', () => {
    const input = [
      profile({ id: 'standard', name: 'Reserved' }),
      profile({ id: '', name: 'Empty ID' }),
      profile({ id: 'valid' }),
      profile({ id: 'valid', name: 'Duplicate' }),
      profile({ id: 'another', name: '  ' }),
    ]
    assert.deepEqual(normalizeProfiles(input).map(p => p.id), ['valid'])
  })

  it('enforces numeric ranges and uses default values when invalid', () => {
    const [result] = normalizeProfiles([profile({
      titleFontSize: 200,
      tableFontSize: -4,
      margin: Number.NaN,
      cellPadding: 0,
      breakPadding: 100,
      sectionFontSize: 12,
    })])
    assert.equal(result.titleFontSize, DEFAULT_PDF_PROFILE.titleFontSize)
    assert.equal(result.tableFontSize, DEFAULT_PDF_PROFILE.tableFontSize)
    assert.equal(result.margin, DEFAULT_PDF_PROFILE.margin)
    assert.equal(result.cellPadding, DEFAULT_PDF_PROFILE.cellPadding)
    assert.equal(result.breakPadding, DEFAULT_PDF_PROFILE.breakPadding)
    assert.equal(result.sectionFontSize, 12)
  })

  it('normalizes an invalid break color to the profile default', () => {
    const [result] = normalizeProfiles([profile({ breakColor: 'not-a-color' })])
    assert.equal(result.breakColor, DEFAULT_PDF_PROFILE.breakColor)
  })

  it('skips incomplete rules and replaces invalid rule colors', () => {
    const [result] = normalizeProfiles([profile({ rules: [
      { id: 'one', label: 'A', expression: 'Vocal', color: 'bad' },
      { id: 'two', label: '', expression: 'Female', color: '#123456' },
      { id: 'three', label: 'C', expression: '', color: '#123456' },
    ] })])
    assert.equal(result.rules.length, 1)
    assert.equal(result.rules[0].color, '#FFFFFF')
  })

  it('limits the number of profiles and rules', () => {
    const manyRules = Array.from({ length: 35 }, (_, i) => ({
      id: `r${i}`, label: `Rule ${i}`, expression: `Tag ${i}`, color: '#123456',
    }))
    const input = Array.from({ length: 15 }, (_, i) => profile({ id: `p${i}`, rules: manyRules }))
    const result = normalizeProfiles(input)
    assert.equal(result.length, 12)
    assert.ok(result.every(p => p.rules.length === 30))
  })
})
