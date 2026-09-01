import { AlertTriangle, CircleAlert } from 'lucide-react'
import { Badge } from '../ui/Badge.jsx'

const styles = {
  critical: { variant: 'danger', Icon: CircleAlert },
  warning: { variant: 'warning', Icon: AlertTriangle },
}

/** Severity marker for one signal, or for a whole register row. */
export function SignalBadge({ severity }) {
  const style = styles[severity] ?? styles.warning

  return (
    <Badge variant={style.variant} dot>
      <style.Icon className="size-3" aria-hidden="true" />
      {severity === 'critical' ? 'Critical' : 'Warning'}
    </Badge>
  )
}
