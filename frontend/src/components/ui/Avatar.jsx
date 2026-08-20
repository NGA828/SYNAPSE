import { formatInitials } from '../../utils/formatters.js'
import { cn } from '../../utils/cn.js'

const gradients = [
  'from-brand-600 to-violet-600',
  'from-violet-600 to-fuchsia-600',
  'from-teal-500 to-emerald-600',
  'from-amber-500 to-orange-600',
  'from-sky-500 to-blue-600',
  'from-rose-500 to-pink-600',
]

const sizes = {
  sm: 'size-8 text-xs',
  md: 'size-9 text-xs',
  lg: 'size-12 text-sm',
}

function gradientFor(name = '') {
  let hash = 0
  for (let i = 0; i < name.length; i += 1) hash = (hash * 31 + name.charCodeAt(i)) >>> 0
  return gradients[hash % gradients.length]
}

export function Avatar({ name, size = 'md', className }) {
  return (
    <span
      className={cn(
        'flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br font-semibold text-white',
        gradientFor(name),
        sizes[size],
        className,
      )}
      aria-hidden="true"
    >
      {formatInitials(name)}
    </span>
  )
}
