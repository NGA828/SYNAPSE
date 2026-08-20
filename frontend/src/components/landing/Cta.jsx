import { Link } from 'react-router-dom'
import { Button } from '../ui/Button.jsx'
import { Reveal } from './motion.jsx'

export function Cta() {
  return (
    <section className="bg-white px-4 pb-20 sm:px-6 lg:px-8">
      <Reveal variant="scale">
        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 via-violet-700 to-brand-900 px-6 py-16 text-center">
          <div className="absolute inset-0 bg-grid-slate opacity-40" aria-hidden="true" />
          <div
            className="animate-aurora absolute -left-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"
            aria-hidden="true"
          />
          <div
            className="animate-aurora-slow absolute -bottom-24 -right-16 h-72 w-72 rounded-full bg-teal-300/20 blur-3xl"
            aria-hidden="true"
          />
          <div className="relative">
            <h2 className="mx-auto max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-4xl">
              Bring every school onto one platform
            </h2>
            <p className="mx-auto mt-4 max-w-xl text-brand-100">
              Register your school and start a free 14-day trial. Students, teachers and
              administrators can sign in the same day.
            </p>
            <div className="mt-8 flex flex-wrap justify-center gap-3">
              <Link to="/onboarding">
                <Button size="lg" className="bg-white text-brand-700 transition-transform hover:scale-[1.03] hover:bg-brand-50">
                  Start your free trial
                </Button>
              </Link>
              <Link to="/login">
                <Button
                  size="lg"
                  className="border border-white/30 bg-white/10 text-white transition-transform hover:scale-[1.03] hover:bg-white/20"
                >
                  Sign in
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </Reveal>
    </section>
  )
}
