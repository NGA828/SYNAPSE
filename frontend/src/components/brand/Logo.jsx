import { useId } from 'react'
import { cn } from '../../utils/cn.js'

export function LogoMark({ className }) {
  const gradientId = useId()

  return (
    <svg viewBox="0 0 32 32" fill="none" className={cn('size-8 shrink-0', className)} aria-hidden="true">
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
          <stop stopColor="#6366f1" />
          <stop offset="0.55" stopColor="#7c3aed" />
          <stop offset="1" stopColor="#14b8a6" />
        </linearGradient>
      </defs>
      <rect width="32" height="32" rx="9" fill={`url(#${gradientId})`} />
      <circle cx="10" cy="16" r="3.2" fill="#fff" />
      <circle cx="22" cy="10" r="2.4" fill="#fff" opacity="0.9" />
      <circle cx="22" cy="22" r="2.4" fill="#fff" opacity="0.9" />
      <path
        d="M12.6 14.8 19.8 11.2M12.8 17.4 19.6 20.4"
        stroke="#fff"
        strokeWidth="1.4"
        strokeLinecap="round"
        opacity="0.85"
      />
    </svg>
  )
}

export function Logo({ className, markClassName }) {
  return (
    <span className={cn('inline-flex items-center gap-2.5 text-slate-900', className)}>
      <LogoMark className={markClassName} />
      <span className="text-lg font-bold tracking-tight">Synapse</span>
    </span>
  )
}
