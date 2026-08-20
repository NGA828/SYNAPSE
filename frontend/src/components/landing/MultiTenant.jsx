import { Fingerprint, Layers, Lock } from 'lucide-react'
import multiTenant from '../../assets/multi-tenant.png'

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
    <section id="multi-tenant" className="scroll-mt-20 bg-slate-50 py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid items-center gap-12 lg:grid-cols-2">
          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5">
            <img
              src={multiTenant}
              alt="Multiple isolated schools on one SYNAPSE platform"
              className="block h-auto w-full"
              loading="lazy"
            />
          </div>

          <div>
            <h2 className="text-3xl font-bold tracking-tight text-slate-900">
              Built for many schools, isolated by design
            </h2>
            <p className="mt-3 text-slate-600">
              SYNAPSE SaaS gives every school its own private workspace — while a platform
              super admin manages them all from one place.
            </p>
            <div className="mt-8 space-y-6">
              {POINTS.map(({ icon: Icon, title, description }) => (
                <div key={title} className="flex gap-4">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <Icon className="size-5" aria-hidden="true" />
                  </span>
                  <div>
                    <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                    <p className="mt-1 text-sm leading-relaxed text-slate-500">{description}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
