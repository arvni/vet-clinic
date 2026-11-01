import { isAxiosError } from 'axios'

/** Field errors from a Laravel 422 response, first message per field. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!isAxiosError(error) || error.response?.status !== 422) return {}
  const errors = (error.response.data?.errors ?? {}) as Record<string, string[]>
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
}

/** One human message for a failed request, in the API's language. */
export function errorMessage(error: unknown, fallback: string): string {
  if (isAxiosError(error)) {
    const message = error.response?.data?.message
    if (typeof message === 'string' && message) return message
  }
  return fallback
}
