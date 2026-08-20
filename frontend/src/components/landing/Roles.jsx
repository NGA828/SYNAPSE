import { Link } from 'react-router-dom'
import { Crown, GraduationCap, ShieldCheck, Users } from 'lucide-react'
import { Badge } from '../ui/Badge.jsx'
import { Button } from '../ui/Button.jsx'
import { Reveal } from './motion.jsx'

const ROLES = [
  {
    icon: Crown,
    name: 'Super Admin',
    accent: 'bg-amber-50 text-amber-600',
    badge: 'platform',
    points: ['Manage every school', 'Plans & subscriptions', 'Platform analytics'],
  },
  {
    icon: ShieldCheck,
    name: 'Administrators',
    accent: 'bg-teal-50 text-teal-600',
    badge: 'school',
    points: ['Manage school structure', 'Register students & teachers', 'Billing & settings'],
  },
  {
    icon: Users,
    name: 'Teachers',
    accent: 'bg-violet-50 text-violet-600',
    badge: 'teacher',
    points: ['See teaching assignments', 'Enter grades for their classes', 'Access only assigned data'],
  },
  {
    icon: GraduationCap,
    name: 'Students',
    accent: 'bg-brand-50 text-brand-600',
    badge: 'student',
    points: ['View grades & report card', 'Check timetable', 'Submit requests & documents'],
  },
]

export function Roles() {
  return (
    <section id="roles" className="scroll-mt-20 bg-white py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Reveal className="mx-auto max-w-2xl text-center">
          <h2 className="text-3xl font-bold tracking-tight text-slate-900">Built for every role</h2>
          <p className="mt-3 text-slate-600">
            From platform super admin to student — permissions follow you everywhere.
          </p>
        </Reveal>

        <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {ROLES.map(({ icon: Icon, name, accent, badge, points }, index) => (
            <Reveal key={name} delay={index * 100}>
              <div className="group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-brand-600/5">
                <span className={`flex size-11 items-center justify-center rounded-xl ${accent} transition-transform duration-300 group-hover:scale-110`}>
                  <Icon className="size-5" aria-hidden="true" />
                </span>
                <div className="mt-4 flex items-center gap-2">
                  <h3 className="text-base font-semibold text-slate-900">{name}</h3>
                  <Badge variant="neutral">{badge}</Badge>
                </div>
                <ul className="mt-4 space-y-2 text-sm text-slate-500">
                  {points.map((point) => (
                    <li key={point} className="flex items-start gap-2">
                      <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-400" />
                      {point}
                    </li>
                  ))}
                </ul>
                <div className="mt-6 flex-1" />
                <Link to="/login">
                  <Button variant="secondary" size="sm" className="w-full">
                    Sign in
                  </Button>
                </Link>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}
