import { Fingerprint, Layers, Lock } from 'lucide-react'
import multiTenant from '../../assets/multi-tenant.png'
import { Reveal, TiltCard } from './motion.jsx'

const POINTS = [
  {
    icon: Lock,
    title: 'Row-level isolation',
    description:
      'Every record carries a school_id and every query is scoped server-side — School A can never touch School B’s data, even by changing URLs or IDs.',
  },
  {
    icon: Layers,
    title: 'One codebase, many tenants',
    description:
      'A single shared application and database serve hundreds of schools, each with its own academic years, classes, subjects and grades.',
  },
  {
    icon: Fingerprint,
    title: 'Enforced in Laravel',
    description:
      'Tenant middleware, global scopes and policies guarantee isolation. The React UI is never treated as a security boundary.',
  },
]

export function MultiTenant() {
  return (
    <section id="multi-tenant" className="scroll-mt-20 overflow-hidden bg-slate-50 py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid items-center gap-12 lg:grid-cols-2">
          <Reveal variant="left">
            <TiltCard max={6}>
              <div className="animate-float-slow overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5">
                <img
                  src={multiTenant}
                  alt="Multiple isolated schools on one SYNAPSE platform"
                  className="block h-auto w-full"
                  loading="lazy"
                />
              </div>
            </TiltCard>
          </Reveal>

          <div>
            <Reveal>
              <h2 className="text-3xl font-bold tracking-tight text-slate-900">
                Built for many schools, isolated by design
              </h2>
            </Reveal>
            <Reveal delay={100}>
              <p className="mt-3 text-slate-600">
                SYNAPSE SaaS gives every school its own private workspace — while a platform
                super admin manages them all from one place.
              </p>
            </Reveal>
            <div className="mt-8 space-y-6">
              {POINTS.map(({ icon: Icon, title, description }, index) => (
                <Reveal key={title} delay={150 + index * 120} variant="right">
                  <div className="group flex gap-4">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition-transform duration-300 group-hover:scale-110">
                      <Icon className="size-5" aria-hidden="true" />
                    </span>
                    <div>
                      <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                      <p className="mt-1 text-sm leading-relaxed text-slate-500">{description}</p>
                    </div>
                  </div>
                </Reveal>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
