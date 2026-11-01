import { localizeDigits } from './digits'

/** Display mode mirrors the backend `money.display_mode` setting. */
export type MoneyDisplayMode = 'toman' | 'new_rial'

const DIVISORS: Record<MoneyDisplayMode, bigint> = {
  toman: 10n,
  new_rial: 10_000n,
}

/**
 * Format an amount stored in (current) rials for display.
 * Amounts arrive from the API as strings to avoid float precision issues.
 */
export function formatMoney(
  rials: string | number | bigint,
  lang: string,
  mode: MoneyDisplayMode = 'toman',
): string {
  const value = BigInt(rials) / DIVISORS[mode]
  const grouped = value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, lang === 'fa' ? '٬' : ',')
  const unit =
    mode === 'toman' ? (lang === 'fa' ? 'تومان' : 'Toman') : lang === 'fa' ? 'ریال جدید' : 'new Rial'
  return `${localizeDigits(grouped, lang)} ${unit}`
}
