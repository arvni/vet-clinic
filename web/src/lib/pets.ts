import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api } from './api'

export interface Labeled {
  value: string
  label: string
}

export interface PetWeight {
  id: number
  kg: string
  measured_at: string
  measured_at_jalali: string
  source: 'owner' | 'vet'
}

export interface PetAllergy {
  id: number
  type: Labeled
  substance: string
  note: string | null
}

export interface PetDocument {
  id: number
  kind: Labeled
  number: string | null
  issued_at: string | null
  verified: boolean
  verified_at_jalali: string | null
  file_url: string | null
  file_mime: string | null
}

export interface Pet {
  id: number
  name: string
  species: Labeled
  breed: { id: number | null; name: string | null }
  sex: Labeled
  birth_date: string | null
  birth_date_jalali: string | null
  birth_date_estimated: boolean
  age: { years: number; months: number; estimated: boolean } | null
  neutered: boolean | null
  color: string | null
  microchip_no: string | null
  photo_url: string | null
  latest_weight?: PetWeight | null
  allergies?: PetAllergy[]
  documents?: PetDocument[]
}

export interface NewPet {
  name: string
  species: string
  breed_id?: number | null
  breed_text?: string
  sex: string
  birth_date_jalali?: string
  birth_date_estimated?: boolean
  neutered?: boolean | null
  weight_kg?: string
}

/** Catalog lists change with the language, so the key includes it. */
export function useSpecies() {
  const { i18n } = useTranslation()
  return useQuery({
    queryKey: ['species', i18n.language],
    queryFn: async () => (await api.get<{ data: Labeled[] }>('/catalog/species')).data.data,
    staleTime: Infinity,
  })
}

export function useBreeds(species: string | undefined) {
  const { i18n } = useTranslation()
  return useQuery({
    queryKey: ['breeds', species, i18n.language],
    enabled: !!species,
    queryFn: async () =>
      (await api.get<{ data: { id: number; name: string }[] }>('/catalog/breeds', { params: { species } })).data.data,
    staleTime: Infinity,
  })
}

export function usePets() {
  const { i18n } = useTranslation()
  return useQuery({
    queryKey: ['pets', i18n.language],
    queryFn: async () => (await api.get<{ data: Pet[] }>('/pets')).data.data,
  })
}

export function usePet(id: number) {
  const { i18n } = useTranslation()
  return useQuery({
    queryKey: ['pet', id, i18n.language],
    queryFn: async () => (await api.get<{ data: Pet }>(`/pets/${id}`)).data.data,
  })
}

export function usePetWeights(id: number) {
  return useQuery({
    queryKey: ['pet-weights', id],
    queryFn: async () => (await api.get<{ data: PetWeight[] }>(`/pets/${id}/weights`)).data.data,
  })
}

export function useCreatePet() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (pet: NewPet) => (await api.post<{ data: Pet }>('/pets', pet)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['pets'] }),
  })
}

export function useAddWeight(petId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (weight_kg: string) => api.post(`/pets/${petId}/weights`, { weight_kg }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['pet-weights', petId] })
      void qc.invalidateQueries({ queryKey: ['pet', petId] })
      void qc.invalidateQueries({ queryKey: ['pets'] })
    },
  })
}

export function useAddAllergy(petId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: { type: string; substance: string }) => api.post(`/pets/${petId}/allergies`, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['pet', petId] }),
  })
}

export function useRemoveAllergy(petId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (allergyId: number) => api.delete(`/pets/${petId}/allergies/${allergyId}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['pet', petId] }),
  })
}

export function useUploadDocument(petId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: { kind: string; number?: string; file?: File }) => {
      const form = new FormData()
      form.append('kind', data.kind)
      if (data.number) form.append('number', data.number)
      if (data.file) form.append('file', data.file)
      return api.post(`/pets/${petId}/documents`, form)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['pet', petId] }),
  })
}

export function useUploadPhoto(petId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (file: File) => {
      const form = new FormData()
      form.append('photo', file)
      return api.post(`/pets/${petId}/photo`, form)
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['pet', petId] })
      void qc.invalidateQueries({ queryKey: ['pets'] })
    },
  })
}
