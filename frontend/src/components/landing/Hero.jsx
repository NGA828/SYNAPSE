import { Link } from 'react-router-dom'
import { ArrowRight } from 'lucide-react'
import { Badge } from '../ui/Badge.jsx'
import { Button } from '../ui/Button.jsx'
import heroShowcase from '../../assets/hero-showcase.png'

const SCHOOLS = ['AICS Cameroon', 'Saint Albert CHS', 'Demo International', 'Nova Academy']

export function Hero() {
  return (
    <section className="relative overflow-hidden">
      <div className="absolute inset-0 bg-grid-slate" aria-hidden="true" />
      <div
        className="absolute -top-40 left-1/2 h-96 w-[40rem] -translate-x-1/2 rounded-full bg-brand-200/40 blur-3xl"
        aria-hidden="true"
      />

      <div className="relative mx-auto max-w-7xl px-4 pb-16 pt-16 sm:px-6 lg:px-8 lg:pt-24">
        <div className="mx-auto max-w-3xl text-center">
          <Badge variant="info">
            <span className="size-1.5 rounded-full bg-brand-500" />
            Multi-tenant SaaS for schools
          </Badge>
          <h1 className="mt-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-6xl">
            One platform for{' '}
            <span className="text-gradient-brand">every school</span>
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600">
            SYNAPSE runs independent schools on a single multi-tenant platform — each with its
            own isolated students, teachers, grades, requests and billing.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link to="/onboarding">
              <Button size="lg">
                Start your free trial
                <ArrowRight className="size-4" aria-hidden="true" />
              </Button>
            </Link>
            <a href="#features">
              <Button size="lg" variant="secondary">
                Explore features
              </Button>
            </a>
          </div>
        </div>

        <div className="mx-auto mt-14 max-w-5xl">
          <div className="overflow-hidden rounded-2xl border border-slate-200 shadow-2xl shadow-slate-900/10">
            <img
              src={heroShowcase}
              alt="SYNAPSE school management dashboard"
              className="block h-auto w-full"
              loading="eager"
            />
          </div>

          <div className="mt-8 flex flex-col items-center gap-3">
            <p className="text-xs font-medium uppercase tracking-wider text-slate-400">
              Trusted by schools across Cameroon
            </p>
            <div className="flex flex-wrap justify-center gap-2">
              {SCHOOLS.map((school) => (
                <span
                  key={school}
                  className="rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-slate-500"
                >
                  {school}
                </span>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
