import { AlertCircle } from 'lucide-react'

export function ErrorDisplay({ message }) {
  if (!message) return null

  return (
    <div
      role="alert"
      className="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-3 text-sm text-rose-700"
    >
      <AlertCircle className="mt-0.5 size-4 shrink-0" />
      <span>{message}</span>
    </div>
  )
}
