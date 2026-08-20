import { Link } from 'react-router-dom'
import { Logo } from '../brand/Logo.jsx'
import { Reveal } from './motion.jsx'

const COLUMNS = [
  {
    title: 'Product',
    links: [
      { label: 'Features', href: '#features' },
      { label: 'How it works', href: '#how-it-works' },
      { label: 'Pricing', href: '#pricing' },
      { label: 'Roles', href: '#roles' },
    ],
  },
  {
    title: 'Get started',
    links: [
      { label: 'Register your school', to: '/onboarding' },
      { label: 'Sign in', to: '/login' },
      { label: 'About accounts', to: '/register' },
    ],
  },
]

export function Footer() {
  return (
    <footer className="border-t border-slate-200 bg-white">
      <Reveal className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="grid gap-8 md:grid-cols-4">
          <div className="md:col-span-2">
            <Logo />
            <p className="mt-3 max-w-xs text-sm text-slate-500">
              The multi-tenant school management platform. Independent schools, one secure,
              isolated workspace each.
            </p>
          </div>
          {COLUMNS.map((column) => (
            <div key={column.title}>
              <h3 className="text-sm font-semibold text-slate-900">{column.title}</h3>
              <ul className="mt-3 space-y-2">
                {column.links.map((link) =>
                  link.to ? (
                    <li key={link.label}>
                      <Link to={link.to} className="text-sm text-slate-500 hover:text-slate-900">
                        {link.label}
                      </Link>
                    </li>
                  ) : (
                    <li key={link.label}>
                      <a href={link.href} className="text-sm text-slate-500 hover:text-slate-900">
                        {link.label}
                      </a>
                    </li>
                  ),
                )}
              </ul>
            </div>
          ))}
        </div>
        <div className="mt-10 border-t border-slate-100 pt-6 text-sm text-slate-400">
          © 2026 SYNAPSE. All rights reserved.
        </div>
      </Reveal>
    </footer>
  )
}
