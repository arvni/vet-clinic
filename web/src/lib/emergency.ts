import { api } from './api'

export interface EmergencyTicket {
  id: number
  status: 'open' | 'acknowledged' | 'resolved'
  phone: string | null
}

/** Never waits long: a slow GPS fix must not delay the alert. */
export function quickPosition(timeoutMs = 3000): Promise<{ lat: number; lng: number } | null> {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve(null)
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => resolve({ lat: coords.latitude, lng: coords.longitude }),
      () => resolve(null),
      { timeout: timeoutMs, maximumAge: 60_000 },
    )
  })
}

export async function fetchClinicPhone(): Promise<string | null> {
  const { data } = await api.get<{ data: { phone: string | null } }>('/clinic/contact')
  return data.data.phone
}

export async function raiseEmergency(position: { lat: number; lng: number } | null): Promise<EmergencyTicket> {
  const { data } = await api.post<{ data: EmergencyTicket }>('/emergencies', position ?? {})
  return data.data
}
