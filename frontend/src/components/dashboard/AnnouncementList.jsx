import { Megaphone } from 'lucide-react'
import { formatDate } from '../../utils/formatters.js'
import { Badge } from '../ui/Badge.jsx'
import { EmptyState } from './EmptyState.jsx'

const AUDIENCE_LABELS = {
  all: 'Everyone',
  students: 'Students',
  teachers: 'Teachers',
}

export function AnnouncementList({ announcements = [] }) {
  if (!announcements || announcements.length === 0) {
    return <EmptyState title="No announcements" description="School announcements will appear here." />
  }

  return (
    <ul className="divide-y divide-slate-100">
      {announcements.map((announcement) => (
        <li key={announcement.id} className="flex gap-3 py-3 first:pt-0 last:pb-0">
          <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-500 text-white">
            <Megaphone className="size-4" aria-hidden="true" />
          </span>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <p className="text-sm font-semibold text-slate-800">{announcement.title}</p>
              {announcement.audience ? (
                <Badge variant="neutral">{AUDIENCE_LABELS[announcement.audience] ?? announcement.audience}</Badge>
              ) : null}
            </div>
            <p className="mt-0.5 line-clamp-2 text-sm text-slate-500">{announcement.body}</p>
            <p className="mt-1 text-xs text-slate-400">
              {formatDate(announcement.published_at ?? announcement.date)}
            </p>
          </div>
        </li>
      ))}
    </ul>
  )
}
