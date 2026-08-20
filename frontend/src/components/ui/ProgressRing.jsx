import { cn } from '../../utils/cn.js'

function ringColor(pct) {
  if (pct >= 80) return '#10b981'
  if (pct >= 60) return '#6366f1'
  if (pct >= 40) return '#f59e0b'
  return '#f43f5e'
}

export function ProgressRing({
  value = 0,
  max = 20,
  size = 128,
  strokeWidth = 10,
  label,
  sublabel,
  className,
}) {
  const pct = Math.max(0, Math.min(100, (Number(value) / Number(max)) * 100))
  const radius = (size - strokeWidth) / 2
  const circumference = 2 * Math.PI * radius
  const offset = circumference - (pct / 100) * circumference

  return (
    <div className={cn('flex flex-col items-center', className)}>
      <div className="relative" style={{ width: size, height: size }}>
        <svg width={size} height={size} className="-rotate-90">
          <circle
            cx={size / 2}
            cy={size / 2}
            r={radius}
            fill="none"
            stroke="#e2e8f0"
            strokeWidth={strokeWidth}
          />
          <circle
            cx={size / 2}
            cy={size / 2}
            r={radius}
            fill="none"
            stroke={ringColor(pct)}
            strokeWidth={strokeWidth}
            strokeLinecap="round"
            strokeDasharray={circumference}
            strokeDashoffset={offset}
            className="transition-all duration-700 ease-out"
          />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-2xl font-bold tracking-tight text-slate-900">{value}</span>
          <span className="text-xs text-slate-400">/ {max}</span>
        </div>
      </div>
      {label ? <p className="mt-3 text-sm font-semibold text-slate-800">{label}</p> : null}
      {sublabel ? <p className="text-xs text-slate-400">{sublabel}</p> : null}
    </div>
  )
}
