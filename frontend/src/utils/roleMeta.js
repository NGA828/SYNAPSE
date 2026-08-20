/**
 * Per-role visual identity — gives each user type its own accent so the
 * experience feels tailored (student indigo, teacher violet, admin teal,
 * super admin amber).
 */
export const ROLE_META = {
  super_admin: {
    label: 'Super Admin',
    badge: 'warning',
    gradient: 'from-amber-500 to-orange-600',
    dot: 'bg-amber-500',
  },
  admin: {
    label: 'Administrator',
    badge: 'teal',
    gradient: 'from-teal-500 to-emerald-600',
    dot: 'bg-teal-500',
  },
  teacher: {
    label: 'Teacher',
    badge: 'violet',
    gradient: 'from-violet-600 to-fuchsia-600',
    dot: 'bg-violet-500',
  },
  student: {
    label: 'Student',
    badge: 'info',
    gradient: 'from-brand-600 to-violet-600',
    dot: 'bg-brand-500',
  },
}

export function roleMeta(role) {
  return ROLE_META[role] ?? ROLE_META.student
}
