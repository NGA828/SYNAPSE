import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { CheckCircle2, ImagePlus, Trash2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listPublicPlans, registerSchool } from '../../services/onboardingService.js'
import { Logo } from '../../components/brand/Logo.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { Card, CardBody } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'
import { cn } from '../../utils/cn.js'

const STEPS = ['School', 'Administrator', 'Plan', 'Review']

export default function OnboardingPage() {
  const { data: plans, loading } = useAsyncList(listPublicPlans)
  const logoRef = useRef(null)
  const [step, setStep] = useState(0)
  const [school, setSchool] = useState({ name: '', slug: '', email: '', phone: '', address: '', logo: null })
  const [admin, setAdmin] = useState({ name: '', email: '', password: '' })
  const [planId, setPlanId] = useState(null)
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [done, setDone] = useState(null)

  const setSchoolField = (field) => (event) => setSchool((current) => ({ ...current, [field]: event.target.value }))
  const setAdminField = (field) => (event) => setAdmin((current) => ({ ...current, [field]: event.target.value }))

  const handleLogoFile = (event) => {
    const file = event.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = () => setSchool((current) => ({ ...current, logo: reader.result }))
    reader.readAsDataURL(file)
    event.target.value = ''
  }

  const canContinue =
    (step === 0 && school.name && school.slug) ||
    (step === 1 && admin.name && admin.email && admin.password.length >= 8) ||
    (step === 2 && planId)

  const submit = async () => {
    setSubmitting(true)
    setError(null)
    try {
      const result = await registerSchool({ school, admin, plan_id: planId })
      setDone(result)
      setStep(4)
    } catch (err) {
      setError(err?.response?.data?.message ?? 'Could not register your school.')
    } finally {
      setSubmitting(false)
    }
  }

  if (done) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <Card className="w-full max-w-md p-8 text-center">
          <CheckCircle2 className="mx-auto size-12 text-emerald-500" aria-hidden="true" />
          <h1 className="mt-4 text-2xl font-bold text-slate-900">School created 🎉</h1>
          <p className="mt-2 text-sm text-slate-500">
            <strong>{done.school.name}</strong> is on a free trial. Sign in with your administrator
            account (<span className="font-mono">{done.admin_email}</span>) to get started.
          </p>
          <Link to="/login" className="mt-6 block">
            <Button className="w-full">Go to sign in</Button>
          </Link>
        </Card>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-slate-50 px-4 py-10">
      <div className="mx-auto max-w-xl">
        <div className="flex justify-center">
          <Logo />
        </div>
        <h1 className="mt-6 text-center text-2xl font-bold tracking-tight text-slate-900">
          Register your school on SYNAPSE
        </h1>
        <p className="mt-1 text-center text-sm text-slate-500">
          Set up your school and start a free trial.
        </p>

        <ol className="mt-6 flex items-center justify-between">
          {STEPS.map((label, index) => (
            <li key={label} className="flex flex-1 items-center last:flex-none">
              <span
                className={cn(
                  'flex size-7 items-center justify-center rounded-full text-xs font-semibold',
                  index < step ? 'bg-emerald-500 text-white' : index === step ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500',
                )}
              >
                {index + 1}
              </span>
              <span className={cn('ml-2 hidden text-xs font-medium sm:block', index === step ? 'text-slate-900' : 'text-slate-400')}>
                {label}
              </span>
              {index < STEPS.length - 1 ? <span className="mx-2 h-px flex-1 bg-slate-200" /> : null}
            </li>
          ))}
        </ol>

        <Card className="mt-6">
          <CardBody className="space-y-4">
            <ErrorDisplay message={error} />

            {step === 0 ? (
              <>
                <input
                  ref={logoRef}
                  type="file"
                  accept="image/png,image/jpeg,image/svg+xml"
                  className="hidden"
                  onChange={handleLogoFile}
                />
                <div>
                  <p className="mb-1.5 block text-sm font-medium text-slate-700">School logo</p>
                  <div className="flex items-center gap-4">
                    {school.logo ? (
                      <img
                        src={school.logo}
                        alt="School logo"
                        className="size-16 shrink-0 rounded-2xl border border-slate-200 object-cover"
                      />
                    ) : (
                      <button
                        type="button"
                        onClick={() => logoRef.current?.click()}
                        className="flex size-16 shrink-0 items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 text-slate-400 transition hover:border-brand-400 hover:text-brand-500"
                      >
                        <ImagePlus className="size-6" aria-hidden="true" />
                      </button>
                    )}
                    <div>
                      <Button type="button" variant="secondary" size="sm" onClick={() => logoRef.current?.click()}>
                        <ImagePlus className="size-4" aria-hidden="true" />
                        {school.logo ? 'Change logo' : 'Upload logo'}
                      </Button>
                      <p className="mt-1.5 text-xs text-slate-400">PNG, JPG or SVG — optional</p>
                      {school.logo ? (
                        <button
                          type="button"
                          onClick={() => setSchool((current) => ({ ...current, logo: null }))}
                          className="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-rose-600 hover:underline"
                        >
                          <Trash2 className="size-3.5" aria-hidden="true" />
                          Remove logo
                        </button>
                      ) : null}
                    </div>
                  </div>
                </div>

                <Input label="School name" value={school.name} onChange={setSchoolField('name')} placeholder="AICS Cameroon" />
                <Input label="Slug (URL)" value={school.slug} onChange={setSchoolField('slug')} placeholder="aics" />
                <div className="grid gap-3 sm:grid-cols-2">
                  <Input label="Email" value={school.email} onChange={setSchoolField('email')} />
                  <Input label="Phone" value={school.phone} onChange={setSchoolField('phone')} />
                </div>
                <Input label="Address" value={school.address} onChange={setSchoolField('address')} />
              </>
            ) : null}

            {step === 1 ? (
              <>
                <Input label="Administrator name" value={admin.name} onChange={setAdminField('name')} />
                <Input label="Administrator email" type="email" value={admin.email} onChange={setAdminField('email')} />
                <Input label="Password" type="password" value={admin.password} onChange={setAdminField('password')} hint="At least 8 characters" />
              </>
            ) : null}

            {step === 2 ? (
              loading ? (
                <div className="flex justify-center py-10">
                  <Spinner />
                </div>
              ) : (
                <div className="space-y-3">
                  {plans?.map((plan) => (
                    <button
                      key={plan.id}
                      type="button"
                      onClick={() => setPlanId(plan.id)}
                      className={cn(
                        'w-full rounded-2xl border p-4 text-left transition',
                        planId === plan.id ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-200' : 'border-slate-200 hover:border-slate-300',
                      )}
                    >
                      <div className="flex items-center justify-between">
                        <p className="font-semibold text-slate-900">{plan.name}</p>
                        <p className="text-sm font-semibold text-slate-700">
                          {plan.price} {plan.currency}/{plan.billing_interval}
                        </p>
                      </div>
                      <p className="mt-1 text-xs text-slate-500">{plan.description}</p>
                      <p className="mt-2 text-xs text-slate-500">
                        {plan.max_students ?? 'Unlimited'} students · {plan.max_teachers ?? 'Unlimited'} teachers
                      </p>
                    </button>
                  ))}
                </div>
              )
            ) : null}

            {step === 3 ? (
              <div className="space-y-3 text-sm">
                <div className="flex items-center gap-3">
                  {school.logo ? (
                    <img src={school.logo} alt="School logo" className="size-12 rounded-xl border border-slate-200 object-cover" />
                  ) : (
                    <span className="flex size-12 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-400">
                      {String(school.name).slice(0, 1).toUpperCase()}
                    </span>
                  )}
                  <p><span className="text-slate-400">School:</span> {school.name} ({school.slug})</p>
                </div>
                <p><span className="text-slate-400">Administrator:</span> {admin.name} · {admin.email}</p>
                <p>
                  <span className="text-slate-400">Plan:</span>{' '}
                  {plans?.find((plan) => plan.id === planId)?.name} — free 14-day trial
                </p>
              </div>
            ) : null}

            <div className="flex justify-between pt-2">
              {step > 0 && step < 4 ? (
                <Button variant="secondary" onClick={() => setStep((value) => value - 1)}>
                  Back
                </Button>
              ) : (
                <span />
              )}
              {step < 3 ? (
                <Button disabled={!canContinue} onClick={() => setStep((value) => value + 1)}>
                  Continue
                </Button>
              ) : (
                <Button onClick={submit} loading={submitting}>
                  Create school
                </Button>
              )}
            </div>
          </CardBody>
        </Card>

        <p className="mt-6 text-center text-xs text-slate-400">
          Already have an account?{' '}
          <Link to="/login" className="font-medium text-brand-600 hover:underline">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  )
}
