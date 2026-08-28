import { Check, X } from 'lucide-react'
import { clsx } from 'clsx'

/**
 * Live checklist mirroring the server-side rules
 * (Password::min(8)->letters()->numbers()), so users are never surprised by a
 * 422 after submitting.
 */
export function PasswordRules({ value = '', confirmation }) {
  const rules = [
    { label: 'At least 8 characters', ok: value.length >= 8 },
    { label: 'Contains a letter', ok: /\p{L}/u.test(value) },
    { label: 'Contains a number', ok: /\d/.test(value) },
  ]

  if (confirmation !== undefined) {
    rules.push({ label: 'Both entries match', ok: value.length > 0 && value === confirmation })
  }

  return (
    <ul className="space-y-1 rounded-xl bg-slate-50 p-3">
      {rules.map((rule) => (
        <li
          key={rule.label}
          className={clsx('flex items-center gap-2 text-xs', rule.ok ? 'text-emerald-700' : 'text-slate-500')}
        >
          {rule.ok ? (
            <Check className="size-3.5" aria-hidden="true" />
          ) : (
            <X className="size-3.5 text-slate-400" aria-hidden="true" />
          )}
          {rule.label}
        </li>
      ))}
    </ul>
  )
}
