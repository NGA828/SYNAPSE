import {
  BookMarked,
  ClipboardList,
  CreditCard,
  Network,
  Palette,
  ShieldCheck,
} from 'lucide-react'
import { Card } from '../ui/Card.jsx'

const FEATURES = [
  {
    icon: Network,
    title: 'Multi-tenant isolation',
    description:
      'Every school gets its own isolated workspace — students, classes, grades and requests are never shared across tenants.',
  },
  {
    icon: ShieldCheck,
    title: 'Platform super admin',
    description:
      'Manage every school, subscription and plan from one dashboard while school admins manage only their own.',
  },
  {
    icon: CreditCard,
    title: 'Subscriptions & billing',
    description:
      'Configurable plans with student/teacher limits, free trials, upgrades and Cameroon-first payments (MTN MoMo, Orange Money).',
  },
  {
    icon: BookMarked,
    title: 'Teaching assignments',
    description:
      'Teachers only access the class + subject combinations assigned to them — enforced server-side.',
  },
  {
    icon: ClipboardList,
    title: 'Requests & documents',
    description:
      'Students submit document requests online and track them from submission to ready, then download.',
  },
  {
    icon: Palette,
    title: 'White-label branding',
    description:
      'Each school sees its own name, logo and colors while the underlying platform remains SYNAPSE.',
  },
]

export function Features() {
  return (
    <section id="features" className="scroll-mt-20 bg-white py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <h2 className="text-3xl font-bold tracking-tight text-slate-900">
            A complete SaaS platform for every school
          </h2>
          <p className="mt-3 text-slate-600">
            From tenant isolation to billing — everything a modern school platform needs.
          </p>
        </div>

        <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map(({ icon: Icon, title, description }) => (
            <Card key={title} className="p-6 transition hover:-translate-y-0.5 hover:shadow-md">
              <span className="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                <Icon className="size-5" aria-hidden="true" />
              </span>
              <h3 className="mt-4 text-base font-semibold text-slate-900">{title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-slate-500">{description}</p>
            </Card>
          ))}
        </div>
      </div>
    </section>
  )
}
