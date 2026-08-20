import { Badge } from '../ui/Badge.jsx'

const VARIANTS = {
  active: 'success',
  trial: 'info',
  suspended: 'danger',
  expired: 'warning',
  past_due: 'warning',
  cancelled: 'neutral',
  succeeded: 'success',
  pending: 'warning',
  failed: 'danger',
  refunded: 'neutral',
  super_admin: 'warning',
  admin: 'teal',
  teacher: 'violet',
  student: 'info',
}

const LABELS = {
  super_admin: 'Super Admin',
  admin: 'Administrator',
  teacher: 'Teacher',
  student: 'Student',
}

export function StatusBadge({ status }) {
  return (
    <Badge variant={VARIANTS[status] ?? 'neutral'} dot>
      {LABELS[status] ?? status}
    </Badge>
  )
}
