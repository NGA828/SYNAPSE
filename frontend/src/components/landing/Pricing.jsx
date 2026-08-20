import { Link } from 'react-router-dom'
import { Check } from 'lucide-react'
import { Button } from '../ui/Button.jsx'
import { Badge } from '../ui/Badge.jsx'

const PLANS = [
  {
    name: 'Starter',
    price: '15,000',
    tagline: 'For small schools getting started.',
    features: ['Up to 500 students', 'Up to 20 teachers', 'Grades & report cards', 'Notifications'],
    featured: false,
  },
  {
    name: 'Professional',
    price: '25,000',
    tagline: 'For growing schools with full document management.',
    features: [
      'Up to 2,000 students',
      'Up to 100 teachers',
      'Document management',
      'Custom branding',
      'Everything in Starter',
    ],
    featured: true,
  },
  {
    name: 'Enterprise',
    price: '60,000',
    tagline: 'Custom limits and advanced analytics.',
    features: [
      'Unlimited students & teachers',
      'Advanced analytics',
      'Custom branding',
      'Priority support',
    ],
    featured: false,
  },
]

export function Pricing() {
  return (
    <section id="pricing" className="scroll-mt-20 bg-white py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <h2 className="text-3xl font-bold tracking-tight text-slate-900">
            Simple, per-school pricing
          </h2>
          <p className="mt-3 text-slate-600">
            Every plan starts with a free 14-day trial. No credit card required.
          </p>
        </div>

        <div className="mt-12 grid gap-6 lg:grid-cols-3">
          {PLANS.map((plan) => (
            <div
              key={plan.name}
              className={`relative flex flex-col rounded-2xl border p-6 ${
                plan.featured
                  ? 'border-brand-500 bg-gradient-to-b from-brand-50/60 to-white shadow-xl shadow-brand-600/5'
                  : 'border-slate-200 bg-white'
              }`}
            >
              {plan.featured ? (
                <span className="absolute -top-3 left-1/2 -translate-x-1/2">
                  <Badge variant="info">Most popular</Badge>
                </span>
              ) : null}
              <h3 className="text-base font-semibold text-slate-900">{plan.name}</h3>
              <p className="mt-1 text-sm text-slate-500">{plan.tagline}</p>
              <p className="mt-4 text-4xl font-bold tracking-tight text-slate-900">
                {plan.price}
                <span className="text-sm font-normal text-slate-400"> XAF/mo</span>
              </p>
              <ul className="mt-6 flex-1 space-y-3 text-sm text-slate-600">
                {plan.features.map((feature) => (
                  <li key={feature} className="flex items-start gap-2">
                    <Check className="mt-0.5 size-4 shrink-0 text-emerald-500" aria-hidden="true" />
                    {feature}
                  </li>
                ))}
              </ul>
              <Link to="/onboarding" className="mt-6 block">
                <Button variant={plan.featured ? 'primary' : 'secondary'} className="w-full">
                  Start free trial
                </Button>
              </Link>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
