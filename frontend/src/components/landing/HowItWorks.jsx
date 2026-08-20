import { BookMarked, Rocket, School } from 'lucide-react'

const STEPS = [
  {
    icon: School,
    step: '01',
    title: 'Register your school',
    description:
      'Create your school and administrator account, then pick a plan — you start on a free 14-day trial.',
  },
  {
    icon: BookMarked,
    step: '02',
    title: 'Set up your structure',
    description:
      'Add academic years, classes and subjects, register students and teachers, then assign teachers.',
  },
  {
    icon: Rocket,
    step: '03',
    title: 'Go live',
    description:
      'Students and teachers sign in to their own dashboards — grades, timetables and requests start flowing.',
  },
]

export function HowItWorks() {
  return (
    <section id="how-it-works" className="scroll-mt-20 bg-slate-50 py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <h2 className="text-3xl font-bold tracking-tight text-slate-900">How SYNAPSE works</h2>
          <p className="mt-3 text-slate-600">
            From registration to a running school in three steps.
          </p>
        </div>

        <div className="mt-12 grid gap-6 md:grid-cols-3">
          {STEPS.map(({ icon: Icon, step, title, description }) => (
            <div key={step} className="relative rounded-2xl border border-slate-200 bg-white p-6">
              <div className="flex items-center justify-between">
                <span className="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand-600 to-violet-600 text-white">
                  <Icon className="size-5" aria-hidden="true" />
                </span>
                <span className="text-3xl font-bold text-slate-200">{step}</span>
              </div>
              <h3 className="mt-4 text-base font-semibold text-slate-900">{title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-slate-500">{description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
