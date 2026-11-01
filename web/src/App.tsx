import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider, useAuth } from './auth/AuthContext'
import { AddPet } from './pages/AddPet'
import { Dashboard } from './pages/Dashboard'
import { Login } from './pages/Login'
import { PetProfile } from './pages/PetProfile'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, refetchOnWindowFocus: false, staleTime: 30_000 },
  },
})

function RequireAuth({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return null
  return user ? children : <Navigate to="/login" replace />
}

const protectedRoute = (element: ReactNode) => <RequireAuth>{element}</RequireAuth>

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/" element={protectedRoute(<Dashboard />)} />
            <Route path="/pets/new" element={protectedRoute(<AddPet />)} />
            <Route path="/pets/:id" element={protectedRoute(<PetProfile />)} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  )
}
