import { describe, expect, it } from 'vitest'
import { normalizeIranMobile, toLatinDigits, toPersianDigits } from './digits'
import { formatMoney } from './money'

describe('digits', () => {
  it('converts Persian and Arabic digits to Latin', () => {
    expect(toLatinDigits('۰۹۱۲٣٤٥')).toBe('0912345')
  })

  it('converts Latin digits to Persian', () => {
    expect(toPersianDigits(2026)).toBe('۲۰۲۶')
  })

  it.each([
    ['09121234567', '+989121234567'],
    ['۰۹۱۲۱۲۳۴۵۶۷', '+989121234567'],
    ['+98 912 123 4567', '+989121234567'],
    ['00989121234567', '+989121234567'],
    ['9121234567', '+989121234567'],
  ])('normalizes %s', (input, expected) => {
    expect(normalizeIranMobile(input)).toBe(expected)
  })

  it.each(['0812345678', '091212345', 'abc'])('rejects %s', (input) => {
    expect(normalizeIranMobile(input)).toBeNull()
  })
})

describe('money', () => {
  it('shows rials as toman in Persian', () => {
    expect(formatMoney('2960000', 'fa')).toBe('۲۹۶٬۰۰۰ تومان')
  })

  it('shows rials as toman in English', () => {
    expect(formatMoney('2960000', 'en')).toBe('296,000 Toman')
  })

  it('supports the redenominated unit', () => {
    expect(formatMoney('2960000', 'en', 'new_rial')).toBe('296 new Rial')
  })
})
