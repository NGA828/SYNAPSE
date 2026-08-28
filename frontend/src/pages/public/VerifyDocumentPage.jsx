import { useEffect, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { BadgeCheck, ShieldAlert } from 'lucide-react'
import { verifyDocument } from '../../services/authService.js'
import { Logo } from '../../components/brand/Logo.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { formatDate } from '../../utils/formatters.js'

/**
 * Public page behind the verification code printed on every SYNAPSE PDF.
 * Anyone holding the paper can confirm the school really issued it.
 */
export default function VerifyDocumentPage() {
  const { code: codeFromPath } = useParams()
  const [params] = useSearchParams()

  const [code, setCode] = useState(codeFromPath ?? params.get('code') ?? '')
  const [result, setResult] = useState(null)
  const [checked, setChecked] = useState(false)
  const [loading, setLoading] = useState(false)

  const check = async (value) => {
    if (!value) return

    setLoading(true)
    setChecked(false)

    try {
      setResult(await verifyDocument(value))
    } catch (err) {
      setResult(err?.response?.data ?? { valid: false })
    } finally {
      setLoading(false)
      setChecked(true)
    }
  }

  useEffect(() => {
    if (!codeFromPath) return undefined

    let cancelled = false

    Promise.resolve().then(() => {
      if (!cancelled) check(codeFromPath)
    })

    return () => {
      cancelled = true
    }
  }, [codeFromPath])

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12">
      <div className="w-full max-w-lg">
        <div className="flex justify-center">
          <Logo />
        </div>

        <h1 className="mt-8 text-center text-2xl font-bold tracking-tight text-slate-900">
          Verify a document
        </h1>
        <p className="mt-2 text-center text-sm text-slate-500">
          Enter the verification code printed in the footer of the document.
        </p>

        <form
          className="mt-6 flex gap-2"
          onSubmit={(event) => {
            event.preventDefault()
            check(code.trim())
          }}
        >
          <Input
            className="flex-1"
            value={code}
            onChange={(event) => setCode(event.target.value.toUpperCase())}
            placeholder="SYN-XXXX-XXXX"
            aria-label="Verification code"
          />
          <Button type="submit" loading={loading}>
            Verify
          </Button>
        </form>

        {loading ? (
          <div className="mt-8 flex justify-center">
            <Spinner />
          </div>
        ) : null}

        {checked && result?.valid ? (
          <div className="mt-8 rounded-2xl border border-emerald-100 bg-emerald-50 p-6">
            <div className="flex items-center gap-3">
              <BadgeCheck className="size-8 text-emerald-600" aria-hidden="true" />
              <div>
                <p className="font-semibold text-emerald-900">Authentic document</p>
                <p className="text-sm text-emerald-800">Issued through SYNAPSE by {result.school}.</p>
              </div>
            </div>

            <dl className="mt-4 grid gap-2 text-sm">
              {[
                ['Document', result.title],
                ['Issued to', result.issued_to],
                ['Matricule', result.matricule],
                ['Issued on', result.issued_on ? formatDate(result.issued_on) : null],
                ['Code', result.verification_code],
              ]
                .filter(([, value]) => value)
                .map(([label, value]) => (
                  <div key={label} className="flex justify-between gap-4 border-b border-emerald-100 pb-1">
                    <dt className="text-emerald-700">{label}</dt>
                    <dd className="font-medium text-emerald-900">{value}</dd>
                  </div>
                ))}
            </dl>
          </div>
        ) : null}

        {checked && !result?.valid ? (
          <div className="mt-8 flex items-center gap-3 rounded-2xl border border-rose-100 bg-rose-50 p-6">
            <ShieldAlert className="size-8 text-rose-600" aria-hidden="true" />
            <div>
              <p className="font-semibold text-rose-900">No match found</p>
              <p className="text-sm text-rose-800">
                {result?.message ?? 'No document matches this verification code.'}
              </p>
            </div>
          </div>
        ) : null}

        <Link to="/" className="mt-8 block text-center text-sm text-brand-600 hover:underline">
          Back to SYNAPSE
        </Link>
      </div>
    </div>
  )
}
