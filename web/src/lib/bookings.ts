import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './api'

export const CLINIC_TIMEZONE = 'Asia/Tehran'

export interface MoneyValue {
  rials: string
}

export type BookingLocation = 'clinic' | 'home' | 'online'

export interface Service {
  id: number
  name: string
  duration_minutes: number
  locations: BookingLocation[]
  species: string[]
  price: MoneyValue | null
}

export interface Slot {
  vet_id: number
  vet_name: string | null
  starts_at: string
  ends_at: string
}

export interface Zone {
  id: number
  name: string
  home_visit_fee: MoneyValue
}

export interface Address {
  id: number
  label: string
  line: string
  zone: Zone | null
}

export interface NewAddress {
  label: string
  line: string
  lat: number
  lng: number
}

export interface Hold {
  token: string
  expires_at: string
}

export interface Booking {
  id: number
  status: 'pending_payment' | 'confirmed' | 'cancelled'
  location: BookingLocation
  starts_at: string
  pet: { id: number; name: string }
  service: { id: number; name: string }
  vet_name: string | null
  address: string | null
  service_price: MoneyValue | null
  home_visit_fee: MoneyValue
}

const intlLocale = (lang: string) => (lang === 'fa' ? 'fa-IR-u-ca-persian' : 'en-GB')

/** The next `count` clinic-local days as API dates with display labels. */
export function dayOptions(lang: string, count = 14, from = new Date()): { date: string; label: string }[] {
  const iso = new Intl.DateTimeFormat('en-CA', { timeZone: CLINIC_TIMEZONE })
  const label = new Intl.DateTimeFormat(intlLocale(lang), {
    timeZone: CLINIC_TIMEZONE,
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  })

  return Array.from({ length: count }, (_, i) => {
    const day = new Date(from.getTime() + i * 86_400_000)
    return { date: iso.format(day), label: label.format(day) }
  })
}

export function formatTime(instant: string, lang: string): string {
  return new Intl.DateTimeFormat(intlLocale(lang), {
    timeZone: CLINIC_TIMEZONE,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).format(new Date(instant))
}

export function formatDateTime(instant: string, lang: string): string {
  return new Intl.DateTimeFormat(intlLocale(lang), {
    timeZone: CLINIC_TIMEZONE,
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).format(new Date(instant))
}

/** Services this species can book; an empty species list means all. */
export function servicesFor(services: Service[], species: string): Service[] {
  return services.filter((s) => s.species.length === 0 || s.species.includes(species))
}

export function useServices() {
  return useQuery({
    queryKey: ['services'],
    queryFn: async () => (await api.get<{ data: Service[] }>('/catalog/services')).data.data,
  })
}

export function useSlots(serviceId: number | null, date: string | null) {
  return useQuery({
    queryKey: ['slots', serviceId, date],
    enabled: serviceId !== null && date !== null,
    staleTime: 0,
    queryFn: async () =>
      (await api.get<{ data: Slot[] }>('/slots', { params: { service_id: serviceId, date } })).data.data,
  })
}

export function useAddresses() {
  return useQuery({
    queryKey: ['addresses'],
    queryFn: async () => (await api.get<{ data: Address[] }>('/addresses')).data.data,
  })
}

export function useAddAddress() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: async (address: NewAddress) =>
      (await api.post<{ data: Address }>('/addresses', address)).data.data,
    onSuccess: () => client.invalidateQueries({ queryKey: ['addresses'] }),
  })
}

export async function reverseGeocode(lat: number, lng: number): Promise<string | null> {
  const { data } = await api.get<{ data: { address: string | null } }>('/maps/reverse', { params: { lat, lng } })
  return data.data.address
}

export function useHoldSlot() {
  return useMutation({
    mutationFn: async (input: { service_id: number; vet_id: number; starts_at: string }) =>
      (await api.post<{ data: Hold }>('/slot-holds', input)).data.data,
  })
}

export function useCreateBooking() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: async (input: {
      hold_token: string
      pet_id: number
      location: BookingLocation
      address_id?: number
      notes?: string
    }) => (await api.post<{ data: Booking }>('/bookings', input)).data.data,
    onSuccess: () => client.invalidateQueries({ queryKey: ['bookings'] }),
  })
}

export function useBookings() {
  return useQuery({
    queryKey: ['bookings'],
    queryFn: async () => (await api.get<{ data: Booking[] }>('/bookings')).data.data,
  })
}
