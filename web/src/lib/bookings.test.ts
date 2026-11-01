import { describe, expect, it } from 'vitest'
import { dayOptions, formatTime, servicesFor, type Service } from './bookings'

describe('dayOptions', () => {
  it('rolls to the next clinic day before UTC midnight', () => {
    // 21:00 UTC is already 00:30 the next day in Tehran.
    const [first, second] = dayOptions('en', 2, new Date('2026-11-08T21:00:00Z'))
    expect(first.date).toBe('2026-11-09')
    expect(second.date).toBe('2026-11-10')
  })

  it('labels days in the Persian calendar', () => {
    expect(dayOptions('fa', 1, new Date('2026-11-08T08:00:00Z'))[0].label).toContain('آبان')
  })
})

describe('formatTime', () => {
  it('shows clinic-local time', () => {
    expect(formatTime('2026-11-08T05:30:00Z', 'en')).toBe('09:00')
  })
})

describe('servicesFor', () => {
  const service = (species: string[]) => ({ species }) as Service

  it('keeps services for all species and matching ones', () => {
    const all = service([])
    const cats = service(['cat'])
    expect(servicesFor([all, cats], 'dog')).toEqual([all])
    expect(servicesFor([all, cats], 'cat')).toEqual([all, cats])
  })
})
