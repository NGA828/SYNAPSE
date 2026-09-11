import { useState } from 'react'
import { Sparkles, X } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { AssistantChat } from './AssistantChat.jsx'

/**
 * The floating study assistant, one click from every student page.
 *
 * Student-only: the widget is the same feature as the assistant page with a
 * smaller frame, so it inherits the same privacy posture — the conversation
 * lives in the component and vanishes when it is closed.
 */
export function AssistantWidget() {
  const { role } = useAuth()
  const [open, setOpen] = useState(false)

  if (role !== 'student') return null

  return (
    <>
      {open ? (
        <div
          className="fixed inset-x-3 bottom-3 z-40 flex h-[min(32rem,calc(100dvh-2rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10 sm:inset-x-auto sm:right-6 sm:bottom-24 sm:h-[min(34rem,calc(100dvh-7rem))] sm:w-96"
          role="dialog"
          aria-label="AI study assistant"
        >
          <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-4 py-3">
            <div className="flex items-center gap-2.5">
              <span className="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-white">
                <Sparkles className="size-4" aria-hidden="true" />
              </span>
              <div>
                <p className="text-sm font-semibold text-slate-900">Study assistant</p>
                <p className="text-[11px] text-slate-500">Ask about your schoolwork</p>
              </div>
            </div>
            <button
              type="button"
              onClick={() => setOpen(false)}
              aria-label="Close the study assistant"
              className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
            >
              <X className="size-4" aria-hidden="true" />
            </button>
          </div>

          <AssistantChat compact />
        </div>
      ) : null}

      <button
        type="button"
        onClick={() => setOpen((current) => !current)}
        aria-label={open ? 'Close the study assistant' : 'Open the study assistant'}
        aria-expanded={open}
        className="fixed right-4 bottom-4 z-40 flex size-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg shadow-brand-600/30 transition hover:scale-105 hover:bg-brand-700 active:scale-95 sm:right-6 sm:bottom-6"
      >
        {open ? <X className="size-5" aria-hidden="true" /> : <Sparkles className="size-5" aria-hidden="true" />}
      </button>
    </>
  )
}
