import { describe, expect, it } from 'vitest'
import { dayOptions, formatTime, nextBooking, servicesFor, type Booking, type Service } from './bookings'

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

describe('nextBooking', () => {
  const booking = (starts_at: string, status: Booking['status'] = 'confirmed') => ({ starts_at, status }) as Booking
  const now = new Date('2026-11-08T08:00:00Z')

  it('picks the soonest live future booking', () => {
    const soon = booking('2026-11-09T08:00:00Z')
    const list = [
      booking('2026-11-01T08:00:00Z'),
      booking('2026-11-08T09:00:00Z', 'cancelled'),
      booking('2026-11-20T08:00:00Z'),
      soon,
    ]
    expect(nextBooking(list, now)).toBe(soon)
  })

  it('returns null when nothing is coming up', () => {
    expect(nextBooking([booking('2026-11-01T08:00:00Z')], now)).toBeNull()
  })
})
