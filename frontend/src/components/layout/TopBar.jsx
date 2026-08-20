import { useState } from 'react'
import { ChevronDown, LogOut, Menu } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { useTenant } from '../../hooks/useTenant.js'
import { formatInitials } from '../../utils/formatters.js'
import { roleMeta } from '../../utils/roleMeta.js'
import { cn } from '../../utils/cn.js'
import { NotificationBell } from './NotificationBell.jsx'

const ROLE_LABELS = {
  super_admin: 'Super Admin',
  admin: 'Administrator',
  teacher: 'Teacher',
  student: 'Student',
}

export function TopBar({ onMenuClick }) {
  const { user, role, logout } = useAuth()
  const { school } = useTenant()
  const [menuOpen, setMenuOpen] = useState(false)

  return (
    <header className="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6">
      <button
        type="button"
        onClick={onMenuClick}
        className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
        aria-label="Open navigation"
      >
        <Menu className="size-5" />
      </button>

      {school ? (
        <span className="hidden items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 md:flex">
          <span className="size-2 rounded-full bg-emerald-500" aria-hidden="true" />
          <span className="text-xs font-semibold text-slate-600">{school.name}</span>
        </span>
      ) : null}

      <div className="flex-1" />

      <NotificationBell />

      <div className="relative">
        <button
          type="button"
          onClick={() => setMenuOpen((open) => !open)}
          className={cn(
            'flex items-center gap-2.5 rounded-xl p-1.5 transition hover:bg-slate-100',
            menuOpen && 'bg-slate-100',
          )}
        >
          <span className={cn('flex size-9 items-center justify-center rounded-full bg-gradient-to-br text-xs font-semibold text-white', roleMeta(role).gradient)}>
            {formatInitials(user?.name)}
          </span>
          <span className="hidden text-left sm:block">
            <span className="block max-w-[10rem] truncate text-sm font-semibold text-slate-800">
              {user?.name}
            </span>
            <span className="block text-xs text-slate-500">{ROLE_LABELS[role] ?? role}</span>
          </span>
          <ChevronDown className="size-4 text-slate-400" />
        </button>

        {menuOpen ? (
          <>
            <button
              type="button"
              className="fixed inset-0 z-10 cursor-default"
              onClick={() => setMenuOpen(false)}
              aria-label="Close menu"
            />
            <div className="absolute right-0 z-20 mt-2 w-60 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-lg">
              <div className="border-b border-slate-100 px-3 py-2.5">
                <p className="truncate text-sm font-semibold text-slate-800">{user?.name}</p>
                <p className="truncate text-xs text-slate-500">{user?.email}</p>
              </div>
              <button
                type="button"
                onClick={logout}
                className="mt-1 flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50"
              >
                <LogOut className="size-4" />
                Sign out
              </button>
            </div>
          </>
        ) : null}
      </div>
    </header>
  )
}
