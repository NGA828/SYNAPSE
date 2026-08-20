import { BookMarked, Rocket, School } from 'lucide-react'
import { Reveal } from './motion.jsx'

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
        <Reveal className="mx-auto max-w-2xl text-center">
          <h2 className="text-3xl font-bold tracking-tight text-slate-900">How SYNAPSE works</h2>
          <p className="mt-3 text-slate-600">
            From registration to a running school in three steps.
          </p>
        </Reveal>

        <div className="mt-12 grid gap-6 md:grid-cols-3">
          {STEPS.map(({ icon: Icon, step, title, description }, index) => (
            <Reveal key={step} delay={index * 130}>
              <div className="group relative h-full overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-brand-600/5">
                <div className="absolute -right-6 -top-6 size-24 rounded-full bg-brand-50 transition-transform duration-500 group-hover:scale-[2.5]" aria-hidden="true" />
                <div className="relative flex items-center justify-between">
                  <span className="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand-600 to-violet-600 text-white transition-transform duration-300 group-hover:scale-110 group-hover:rotate-6">
                    <Icon className="size-5" aria-hidden="true" />
                  </span>
                  <span className="text-3xl font-bold text-slate-200 transition-colors duration-300 group-hover:text-brand-200">
                    {step}
                  </span>
                </div>
                <h3 className="relative mt-4 text-base font-semibold text-slate-900">{title}</h3>
                <p className="relative mt-2 text-sm leading-relaxed text-slate-500">{description}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}
