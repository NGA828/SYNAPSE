import { useEffect } from 'react'
import { X } from 'lucide-react'

export function Modal({ open, onClose, title, description, children }) {
  useEffect(() => {
    if (!open) return undefined

    const onKey = (event) => {
      if (event.key === 'Escape') onClose?.()
    }

    document.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'

    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = ''
    }
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
      <button
        type="button"
        aria-label="Close dialog"
        className="animate-fade-in absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
        onClick={onClose}
      />
      <div className="animate-scale-in relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold tracking-tight text-slate-900">{title}</h2>
            {description ? <p className="mt-0.5 text-sm text-slate-500">{description}</p> : null}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
            aria-label="Close dialog"
          >
            <X className="size-5" />
          </button>
        </div>
        <div className="mt-4">{children}</div>
      </div>
    </div>
  )
}
