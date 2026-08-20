import { useState } from 'react'
import { Award, FileText, GraduationCap, MoreHorizontal } from 'lucide-react'
import { createRequest } from '../../services/requestService.js'
import { REQUEST_TYPES } from '../../utils/requests.js'
import { cn } from '../../utils/cn.js'
import { Button } from '../ui/Button.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

const TYPE_ICONS = {
  'Certificate of Enrollment': FileText,
  'Transcript Request': GraduationCap,
  'Recommendation Letter': Award,
  Other: MoreHorizontal,
}

export function NewRequestForm({ onCreated }) {
  const [type, setType] = useState(REQUEST_TYPES[0])
  const [reason, setReason] = useState('')
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      await createRequest({ type, reason })
      setReason('')
      onCreated?.()
    } catch (err) {
      setError(err?.response?.data?.message ?? 'Could not submit the request.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <ErrorDisplay message={error} />

      <fieldset>
        <legend className="mb-1.5 block text-sm font-medium text-slate-700">Request type</legend>
        <div className="grid gap-2">
          {REQUEST_TYPES.map((option) => {
            const Icon = TYPE_ICONS[option] ?? FileText
            const active = type === option
            return (
              <button
                key={option}
                type="button"
                onClick={() => setType(option)}
                className={cn(
                  'flex items-center gap-3 rounded-xl border p-3 text-left transition',
                  active
                    ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-200'
                    : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50',
                )}
                aria-pressed={active}
              >
                <span
                  className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-lg',
                    active ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500',
                  )}
                >
                  <Icon className="size-4" aria-hidden="true" />
                </span>
                <span className="min-w-0">
                  <span className="block text-sm font-medium text-slate-800">{option}</span>
                </span>
                <span
                  className={cn(
                    'ml-auto size-4 shrink-0 rounded-full border-2',
                    active ? 'border-brand-600 bg-brand-600' : 'border-slate-300',
                  )}
                >
                  {active ? <span className="mx-auto mt-[3px] block size-1.5 rounded-full bg-white" /> : null}
                </span>
              </button>
            )
          })}
        </div>
      </fieldset>

      <Textarea
        label="Reason"
        name="reason"
        rows={3}
        value={reason}
        onChange={(event) => setReason(event.target.value)}
        placeholder="Why do you need this document?"
      />

      <Button type="submit" loading={submitting} className="w-full">
        Submit request
      </Button>
    </form>
  )
}
