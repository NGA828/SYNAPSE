import { useState } from 'react'
import { Award, FileText, GraduationCap, IdCard, ScrollText, UserCheck } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { createRequest, getRequestTypes } from '../../services/requestService.js'
import { cn } from '../../utils/cn.js'
import { Button } from '../ui/Button.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

const TYPE_ICONS = {
  enrollment_certificate: FileText,
  academic_transcript: GraduationCap,
  recommendation_letter: Award,
  good_conduct_certificate: UserCheck,
  transfer_certificate: IdCard,
  school_leaving_certificate: ScrollText,
}

/**
 * The request form.
 *
 * The list of documents comes from the server rather than being repeated here,
 * so the two cannot drift and a student is never offered something the school
 * cannot issue. Options that need a member of staff say so up front, rather
 * than letting a student discover it after they have been waiting.
 */
export function NewRequestForm({ onCreated }) {
  const { data: types, loading, error: typesError } = useAsyncList(getRequestTypes)
  // Only the *override* is state. The effective selection is derived, so the
  // first option is selected as soon as the catalogue lands without an effect
  // writing state back into the render.
  const [override, setOverride] = useState(null)
  const [reason, setReason] = useState('')
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const type = override ?? types?.[0]?.label ?? null

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

  if (loading) {
    return (
      <div className="flex justify-center py-10">
        <Spinner className="size-7" />
      </div>
    )
  }

  if (typesError || !types?.length) {
    return <ErrorDisplay message="The list of available documents could not be loaded." />
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <ErrorDisplay message={error} />

      <fieldset>
        <legend className="mb-1.5 block text-sm font-medium text-slate-700">Request type</legend>
        <div className="grid gap-2">
          {types.map((option) => {
            const Icon = TYPE_ICONS[option.slug] ?? FileText
            const active = type === option.label
            return (
              <button
                key={option.slug}
                type="button"
                onClick={() => setOverride(option.label)}
                className={cn(
                  'flex items-start gap-3 rounded-xl border p-3 text-left transition',
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
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-medium text-slate-800">{option.label}</span>
                  {option.note ? (
                    <span className="mt-0.5 block text-xs text-amber-700">{option.note}</span>
                  ) : (
                    <span className="mt-0.5 block text-xs text-slate-400">Issued automatically</span>
                  )}
                </span>
                <span
                  className={cn(
                    'mt-1 size-4 shrink-0 rounded-full border-2',
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

      <Button type="submit" loading={submitting} disabled={!type} className="w-full">
        Submit request
      </Button>
    </form>
  )
}
