// Nextcloud global type definitions

declare global {
  /**
   * Nextcloud global object
   */
  const OC: {
    t: (text: string, vars?: Record<string, unknown>) => string
    n: (singular: string, plural: string, count: number, vars?: Record<string, unknown>) => string
  }

  /**
   * Nextcloud OCP (OwnCloud Platform) global object
   */
  const OCP: {
    InitialState: {
      [appId: string]: {
        [key: string]: unknown
      }
    }
  }

  /**
   * Window interface extension for Nextcloud globals
   */
  interface Window {
    OCP: {
      InitialState: {
        [appId: string]: {
          [key: string]: unknown
        }
      }
    }
  }

  /**
   * Translation function for Nextcloud
   * @param text - The text to translate
   * @param vars - Optional variables for interpolation
   */
  function t(text: string, vars?: Record<string, unknown>): string

  /**
   * Pluralization function for Nextcloud
   * @param singular - Singular form of the text
   * @param plural - Plural form of the text
   * @param count - Number to determine singular/plural
   * @param vars - Optional variables for interpolation
   */
  function n(singular: string, plural: string, count: number, vars?: Record<string, unknown>): string
}

export {}
