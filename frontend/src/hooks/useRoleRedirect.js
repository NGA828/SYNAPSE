import { useNavigate } from 'react-router-dom'

const ROLE_PATHS = {
  super_admin: '/super-admin',
  admin: '/admin',
  teacher: '/teacher',
  student: '/student',
}

/**
 * Map a role to its dashboard path.
 */
export function getRolePath(role) {
  return ROLE_PATHS[role] ?? '/student'
}

/**
 * Post-login routing: navigate to the dashboard matching a role.
 */
export function useRoleRedirect() {
  const navigate = useNavigate()

  const redirectByRole = (role) => {
    navigate(getRolePath(role), { replace: true })
  }

  return { redirectByRole }
}
