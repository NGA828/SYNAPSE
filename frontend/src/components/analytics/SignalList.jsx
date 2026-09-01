import { CheckCircle2 } from 'lucide-react'
import { SignalBadge } from './SignalBadge.jsx'

/**
 * The reasons a student is on the register, in the words the backend produced.
 *
 * The detail sentence is the point: "attendance is 66.7% against an 80%
 * expectation" tells a form teacher what to raise, where a score does not.
 */
export function SignalList({ signals }) {
  if (!signals?.length) {
    return (
      <p className="flex items-center gap-2 text-sm text-emerald-700">
        <CheckCircle2 className="size-4" aria-hidden="true" />
        Nothing flagged.
      </p>
    )
  }

  return (
    <ul className="space-y-2">
      {signals.map((signal) => (
        <li key={signal.code} className="flex items-start gap-2.5">
          <span className="mt-0.5 shrink-0">
            <SignalBadge severity={signal.severity} />
          </span>
          <span className="min-w-0">
            <span className="block text-sm font-medium text-slate-900">{signal.label}</span>
            <span className="block text-sm text-slate-500">{signal.detail}</span>
          </span>
        </li>
      ))}
    </ul>
  )
}
