const PERSIAN = '۰۱۲۳۴۵۶۷۸۹'
const ARABIC = '٠١٢٣٤٥٦٧٨٩'

/** Convert Persian and Arabic-Indic digits to ASCII digits. */
export function toLatinDigits(input: string): string {
  return input.replace(/[۰-۹٠-٩]/g, (d) => {
    const p = PERSIAN.indexOf(d)
    return String(p >= 0 ? p : ARABIC.indexOf(d))
  })
}

/** Convert ASCII digits to Persian digits for display. */
export function toPersianDigits(input: string | number): string {
  return String(input).replace(/[0-9]/g, (d) => PERSIAN.charAt(Number(d)))
}

/** Localize digits for the active language. */
export function localizeDigits(input: string | number, lang: string): string {
  return lang === 'fa' ? toPersianDigits(input) : String(input)
}

/**
 * Normalize an Iranian mobile number to E.164 (+989xxxxxxxxx).
 * Returns null when the input is not a valid Iranian mobile.
 */
export function normalizeIranMobile(input: string): string | null {
  const digits = toLatinDigits(input).replace(/[\s\-()]/g, '')
  const match = digits.match(/^(?:\+98|0098|98|0)?(9\d{9})$/)
  return match ? `+98${match[1]}` : null
}
