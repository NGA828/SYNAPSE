import { useState } from 'react'
import { useAuth } from '../../hooks/useAuth.js'
import { cn } from '../../utils/cn.js'
import { Sidebar } from './Sidebar.jsx'
import { TopBar } from './TopBar.jsx'
import { SubscriptionBanner } from '../tenant/SubscriptionBanner.jsx'

export function PageContainer({ children }) {
  const { role } = useAuth()
  const [sidebarOpen, setSidebarOpen] = useState(false)

  return (
    <div className="min-h-screen bg-slate-50">
      <Sidebar
        role={role}
        onNavigate={() => setSidebarOpen(false)}
        className={cn(
          'fixed inset-y-0 left-0 z-30 -translate-x-full transition-transform duration-200 lg:translate-x-0',
          sidebarOpen && 'translate-x-0',
        )}
      />

      {sidebarOpen ? (
        <button
          type="button"
          aria-label="Close navigation"
          className="fixed inset-0 z-20 bg-slate-900/40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      ) : null}

      <div className="lg:pl-64">
        <TopBar onMenuClick={() => setSidebarOpen(true)} />
        <main className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
          <SubscriptionBanner />
          {children}
        </main>
      </div>
    </div>
  )
}
