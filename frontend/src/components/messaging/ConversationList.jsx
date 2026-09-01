import { MessageSquare } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { formatDateTime } from '../../utils/formatters.js'
import { Avatar } from '../ui/Avatar.jsx'
import { EmptyState } from '../dashboard/EmptyState.jsx'

const roleLabel = (role) =>
  role === 'teacher' ? 'Teacher' : role === 'admin' ? 'Administrator' : 'Student'

/**
 * The left rail: one row per thread, newest activity first, with an unread
 * count so a waiting reply is visible without opening anything.
 */
export function ConversationList({ conversations, activeId, onSelect, loading }) {
  if (loading) {
    return <p className="px-4 py-8 text-center text-sm text-slate-500">Loading conversations…</p>
  }

  if (!conversations?.length) {
    return (
      <div className="px-4 py-6">
        <EmptyState
          icon={MessageSquare}
          title="No conversations yet"
          description="Start a message to ask about homework, a grade or anything else at school."
        />
      </div>
    )
  }

  return (
    <ul className="divide-y divide-slate-100">
      {conversations.map((conversation) => {
        const person = conversation.participant
        const unread = conversation.unread_count ?? 0

        return (
          <li key={conversation.id}>
            <button
              type="button"
              onClick={() => onSelect(conversation.id)}
              className={cn(
                'flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-slate-50',
                Number(activeId) === Number(conversation.id) && 'bg-brand-50/70 hover:bg-brand-50',
              )}
            >
              <Avatar name={person?.name ?? 'Unknown'} size="md" />

              <span className="min-w-0 flex-1">
                <span className="flex items-center justify-between gap-2">
                  <span className="truncate text-sm font-semibold text-slate-900">
                    {person?.name ?? 'Unknown'}
                  </span>
                  {conversation.last_message_at ? (
                    <span className="shrink-0 text-[11px] text-slate-400">
                      {formatDateTime(conversation.last_message_at)}
                    </span>
                  ) : null}
                </span>
                <span className="mt-0.5 block truncate text-xs text-slate-500">
                  {roleLabel(person?.role)}
                </span>
              </span>

              {unread > 0 ? (
                <span className="mt-1 inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 px-1.5 py-0.5 text-[11px] font-semibold text-white">
                  {unread}
                </span>
              ) : null}
            </button>
          </li>
        )
      })}
    </ul>
  )
}
