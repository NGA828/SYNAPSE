import { Loader2 } from 'lucide-react'
import { cn } from '../../utils/cn.js'

export function Spinner({ className }) {
  return <Loader2 className={cn('size-5 animate-spin text-brand-600', className)} aria-hidden="true" />
}
