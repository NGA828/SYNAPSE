import { useState } from 'react'
import { Bell, CheckCheck } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getNotifications, markAllRead, markRead } from '../../services/notificationService.js'
import { cn } from '../../utils/cn.js'

export function NotificationBell() {
  const { data, reload } = useAsyncList(getNotifications)
  const [open, setOpen] = useState(false)

  const unread = data?.unread_count ?? 0
  const notifications = data?.data ?? []

  const handleRead = async (id) => {
    await markRead(id)
    reload()
  }

  const handleReadAll = async () => {
    await markAllRead()
    reload()
  }

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        className={cn(
          'relative rounded-xl p-2.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700',
          open && 'bg-slate-100',
        )}
        aria-label="Notifications"
      >
        <Bell className="size-5" />
        {unread > 0 ? (
          <span className="absolute right-1.5 top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white ring-2 ring-white">
            {unread > 9 ? '9+' : unread}
          </span>
        ) : null}
      </button>

      {open ? (
        <>
          <button
            type="button"
            className="fixed inset-0 z-10 cursor-default"
            onClick={() => setOpen(false)}
            aria-label="Close notifications"
          />
          <div className="absolute right-0 z-20 mt-2 w-80 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-lg">
            <div className="flex items-center justify-between px-3 py-2">
              <p className="text-sm font-semibold text-slate-800">Notifications</p>
              {unread > 0 ? (
                <button
                  type="button"
                  onClick={handleReadAll}
                  className="text-xs font-medium text-brand-600 hover:underline"
                >
                  Mark all read
                </button>
              ) : null}
            </div>

            <ul className="max-h-80 divide-y divide-slate-100 overflow-y-auto">
              {notifications.length === 0 ? (
                <li className="px-3 py-6 text-center text-sm text-slate-400">No notifications yet.</li>
              ) : (
                notifications.map((notification) => (
                  <li key={notification.id}>
                    <button
                      type="button"
                      onClick={() => handleRead(notification.id)}
                      className={cn(
                        'flex w-full gap-2.5 px-3 py-3 text-left transition hover:bg-slate-50',
                        !notification.read_at && 'bg-brand-50/40',
                      )}
                    >
                      <span
                        className={cn(
                          'mt-1.5 size-2 shrink-0 rounded-full',
                          notification.read_at ? 'bg-slate-200' : 'bg-brand-500',
                        )}
                      />
                      <span className="min-w-0">
                        <span className="block text-sm font-medium text-slate-800">
                          {notification.title}
                        </span>
                        <span className="block truncate text-xs text-slate-500">
                          {notification.message}
                        </span>
                      </span>
                    </button>
                  </li>
                ))
              )}
            </ul>

            <div className="flex items-center justify-center border-t border-slate-100 px-3 py-2">
              <span className="flex items-center gap-1 text-xs text-slate-400">
                <CheckCheck className="size-3.5" aria-hidden="true" />
                Click a notification to mark it read
              </span>
            </div>
          </div>
        </>
      ) : null}
    </div>
  )
}
