import { useState } from 'react'
import { NavLink } from 'react-router-dom'
import {
  CreditCard,
  LayoutDashboard,
  LogOut,
  Menu,
  Receipt,
  School,
  Shapes,
} from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { roleMeta } from '../../utils/roleMeta.js'
import { Logo } from '../brand/Logo.jsx'
import { cn } from '../../utils/cn.js'

const NAV = [
  { label: 'Dashboard', icon: LayoutDashboard, to: '/super-admin', end: true },
  { label: 'Schools', icon: School, to: '/super-admin/schools' },
  { label: 'Plans', icon: Shapes, to: '/super-admin/plans' },
  { label: 'Subscriptions', icon: CreditCard, to: '/super-admin/subscriptions' },
  { label: 'Payments', icon: Receipt, to: '/super-admin/payments' },
]

export function SuperAdminLayout({ children }) {
  const { user, logout } = useAuth()
  const [open, setOpen] = useState(false)

  return (
    <div className="min-h-screen bg-slate-50">
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-slate-900 transition-transform duration-200 lg:translate-x-0',
          open && 'translate-x-0',
        )}
      >
        <div className="flex h-16 items-center border-b border-slate-800 px-5">
          <Logo className="text-white" />
        </div>
        <p className="px-5 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
          Platform
        </p>
        <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-2">
          {NAV.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                  isActive
                    ? 'bg-white/10 text-white'
                    : 'text-slate-400 hover:bg-white/5 hover:text-slate-100',
                )
              }
            >
              <item.icon className="size-[18px]" aria-hidden="true" />
              {item.label}
            </NavLink>
          ))}
        </nav>
        <div className="border-t border-slate-800 p-3">
          <button
            type="button"
            onClick={logout}
            className="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-400 transition hover:bg-white/5 hover:text-rose-300"
          >
            <LogOut className="size-4" />
            Sign out
          </button>
        </div>
      </aside>

      {open ? (
        <button
          type="button"
          aria-label="Close navigation"
          className="fixed inset-0 z-20 bg-slate-900/40 lg:hidden"
          onClick={() => setOpen(false)}
        />
      ) : null}

      <div className="lg:pl-64">
        <header className="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6">
          <button
            type="button"
            onClick={() => setOpen(true)}
            className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
            aria-label="Open navigation"
          >
            <Menu className="size-5" />
          </button>
          <div className="flex-1" />
          <span className={cn('flex size-9 items-center justify-center rounded-full bg-gradient-to-br text-xs font-semibold text-white', roleMeta('super_admin').gradient)}>
            {(user?.name ?? 'SA').slice(0, 1)}
          </span>
          <span className="hidden text-sm font-semibold text-slate-800 sm:block">{user?.name}</span>
        </header>
        <main className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">{children}</main>
      </div>
    </div>
  )
}
