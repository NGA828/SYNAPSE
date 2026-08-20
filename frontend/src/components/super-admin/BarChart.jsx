export function BarChart({ data = [] }) {
  const max = Math.max(1, ...data.map((item) => item.value))

  return (
    <div className="flex h-40 items-end gap-3">
      {data.map((item) => (
        <div key={item.label} className="flex flex-1 flex-col items-center gap-1.5">
          <span className="text-xs font-semibold text-slate-700">{item.value}</span>
          <div className="flex w-full flex-1 items-end">
            <div
              className="w-full rounded-t-lg bg-gradient-to-t from-brand-600 to-violet-500"
              style={{ height: `${Math.max(4, Math.round((item.value / max) * 100))}%` }}
            />
          </div>
          <span className="truncate text-[11px] text-slate-500">{item.label}</span>
        </div>
      ))}
    </div>
  )
}
