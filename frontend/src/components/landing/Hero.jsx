import { Link } from 'react-router-dom'
import { ArrowRight, GraduationCap, ShieldCheck, Users } from 'lucide-react'
import { Badge } from '../ui/Badge.jsx'
import { Button } from '../ui/Button.jsx'
import { Reveal, TiltCard } from './motion.jsx'
import heroShowcase from '../../assets/hero-showcase.png'

const SCHOOLS = [
  'AICS Cameroon',
  'Saint Albert CHS',
  'Demo International',
  'Nova Academy',
  'Cambridge Intl',
  'Lycée Bilingue',
]

export function Hero() {
  return (
    <section className="relative overflow-hidden">
      {/* Ambient aurora background */}
      <div className="absolute inset-0 bg-grid-slate" aria-hidden="true" />
      <div
        className="animate-aurora absolute -top-40 left-1/2 h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-brand-300/40 blur-3xl"
        aria-hidden="true"
      />
      <div
        className="animate-aurora-slow absolute -left-40 top-40 h-80 w-80 rounded-full bg-violet-300/30 blur-3xl"
        aria-hidden="true"
      />
      <div
        className="animate-aurora absolute -right-40 top-64 h-80 w-80 rounded-full bg-teal-300/30 blur-3xl"
        aria-hidden="true"
      />

      <div className="relative mx-auto max-w-7xl px-4 pb-16 pt-16 sm:px-6 lg:px-8 lg:pt-24">
        <div className="mx-auto max-w-3xl text-center">
          <Reveal>
            <Badge variant="info">
              <span className="size-1.5 rounded-full bg-brand-500" />
              Multi-tenant SaaS for schools
            </Badge>
          </Reveal>

          <Reveal delay={100}>
            <h1 className="mt-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-6xl">
              One platform for{' '}
              <span className="text-gradient-brand animate-gradient-x">every school</span>
            </h1>
          </Reveal>

          <Reveal delay={200}>
            <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600">
              SYNAPSE runs independent schools on a single multi-tenant platform — each with its
              own isolated students, teachers, grades, requests and billing.
            </p>
          </Reveal>

          <Reveal delay={300}>
            <div className="mt-8 flex flex-wrap justify-center gap-3">
              <Link to="/onboarding">
                <Button size="lg">
                  Start your free trial
                  <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                </Button>
              </Link>
              <a href="#features">
                <Button size="lg" variant="secondary">
                  Explore features
                </Button>
              </a>
            </div>
          </Reveal>
        </div>

        {/* 3D tilting dashboard mockup with floating chips */}
        <Reveal delay={200} variant="scale">
          <div className="relative mx-auto mt-14 max-w-5xl">
            <TiltCard max={7}>
              <div className="overflow-hidden rounded-2xl border border-slate-200 shadow-2xl shadow-slate-900/10">
                <img
                  src={heroShowcase}
                  alt="SYNAPSE school management dashboard"
                  className="block h-auto w-full"
                  loading="eager"
                />
              </div>
            </TiltCard>

            {/* Floating accent chips */}
            <div className="animate-float absolute -left-6 top-10 hidden items-center gap-2 rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-lg backdrop-blur lg:flex">
              <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <ShieldCheck className="size-5" aria-hidden="true" />
              </span>
              <div>
                <p className="text-xs font-semibold text-slate-800">Tenant isolation</p>
                <p className="text-[10px] text-slate-400">Every school is private</p>
              </div>
            </div>

            <div className="animate-float-reverse absolute -right-6 bottom-10 hidden items-center gap-2 rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-lg backdrop-blur lg:flex">
              <span className="flex size-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                <Users className="size-5" aria-hidden="true" />
              </span>
              <div>
                <p className="text-xs font-semibold text-slate-800">All roles</p>
                <p className="text-[10px] text-slate-400">Student · Teacher · Admin</p>
              </div>
            </div>

            <div className="animate-float-slow absolute -top-6 right-10 hidden items-center gap-2 rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-lg backdrop-blur lg:flex">
              <span className="flex size-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                <GraduationCap className="size-5" aria-hidden="true" />
              </span>
              <div>
                <p className="text-xs font-semibold text-slate-800">Academics</p>
                <p className="text-[10px] text-slate-400">Grades · Exams · Timetables</p>
              </div>
            </div>
          </div>
        </Reveal>

        {/* School marquee */}
        <Reveal delay={150}>
          <div className="mt-12">
            <p className="text-center text-xs font-medium uppercase tracking-wider text-slate-400">
              Trusted by schools across Cameroon
            </p>
            <div className="marquee relative mt-4 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_12%,black_88%,transparent)]">
              <div className="marquee-track flex w-max gap-3 pr-3">
                {[...SCHOOLS, ...SCHOOLS].map((school, index) => (
                  <span
                    key={`${school}-${index}`}
                    className="whitespace-nowrap rounded-full border border-slate-200 bg-white px-4 py-1.5 text-sm font-semibold text-slate-500"
                  >
                    {school}
                  </span>
                ))}
              </div>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  )
}
