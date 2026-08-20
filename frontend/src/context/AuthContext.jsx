/* eslint-disable react-refresh/only-export-components */
// The context object and its provider intentionally live together so the
// provider remains the single wiring point for authentication state.
import { createContext, useCallback, useEffect, useMemo, useState } from 'react'
import { TOKEN_KEY } from '../services/apiClient.js'
import { getUser as fetchUser, login as loginRequest, logout as logoutRequest } from '../services/authService.js'

export const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(() => Boolean(localStorage.getItem(TOKEN_KEY)))

  useEffect(() => {
    const token = localStorage.getItem(TOKEN_KEY)

    if (!token) return

    fetchUser()
      .then(({ user: fetched }) => setUser(fetched))
      .catch(() => localStorage.removeItem(TOKEN_KEY))
      .finally(() => setLoading(false))
  }, [])

  const login = useCallback(async (credentials) => {
    const { token, user: authenticated } = await loginRequest(credentials)
    localStorage.setItem(TOKEN_KEY, token)
    setUser(authenticated)
    return authenticated
  }, [])

  const logout = useCallback(async () => {
    try {
      await logoutRequest()
    } finally {
      localStorage.removeItem(TOKEN_KEY)
      setUser(null)
    }
  }, [])

  const value = useMemo(
    () => ({
      user,
      role: user?.role ?? null,
      isAuthenticated: Boolean(user),
      loading,
      login,
      logout,
    }),
    [user, loading, login, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
