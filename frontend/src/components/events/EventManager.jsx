import { useState } from 'react'
import { CalendarDays, Plus } from 'lucide-react'
import { usePaginatedList } from '../../hooks/usePaginatedList.js'
import {
  deleteEvent,
  listAdminEvents,
  publishEvent,
  unpublishEvent,
} from '../../services/eventService.js'
import { formatDateTime } from '../../utils/formatters.js'
import { audienceLabel, typeLabel } from './eventOptions.js'
import { EventForm } from './EventForm.jsx'
import { Button } from '../ui/Button.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { DataTable } from '../ui/DataTable.jsx'
import { Pagination } from '../ui/Pagination.jsx'
import { SearchInput } from '../ui/SearchInput.jsx'
import { EmptyState } from '../dashboard/EmptyState.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

const columns = [
  { key: 'title', header: 'Event', render: (row) => <span className="font-medium text-slate-900">{row.title}</span> },
  { key: 'type', header: 'Type', render: (row) => <Badge variant="neutral">{typeLabel(row.type)}</Badge> },
  {
    key: 'starts_at',
    header: 'When',
    render: (row) => (
      <span className="text-slate-600">
        {row.all_day ? formatDateTime(row.starts_at)?.slice(0, 10) : formatDateTime(row.starts_at)}
      </span>
    ),
  },
  { key: 'audience', header: 'Audience', render: (row) => audienceLabel(row.audience) },
  {
    key: 'is_published',
    header: 'Status',
    render: (row) => (
      <Badge variant={row.is_published ? 'success' : 'warning'} dot>
        {row.is_published ? 'Published' : 'Draft'}
      </Badge>
    ),
  },
]

/**
 * Admin event manager.
 *
 * Publishing is separate from saving because it is the moment the audience is
 * notified — an administrator should be able to write an event early without
 * telling anyone about it yet.
 */
export function EventManager() {
  const [editing, setEditing] = useState(null)
  const [creating, setCreating] = useState(false)
  const [busyId, setBusyId] = useState(null)
  const [error, setError] = useState(null)

  const { rows, meta, page, setPage, search, setSearch, loading, reload } =
    usePaginatedList(listAdminEvents)

  const act = async (id, action, message) => {
    setBusyId(id)
    setError(null)
    try {
      await action(id)
      await reload()
    } catch (err) {
      setError(err?.response?.data?.message ?? message)
    } finally {
      setBusyId(null)
    }
  }

  const actionColumn = {
    key: 'actions',
    header: '',
    align: 'right',
    render: (row) => (
      <div className="flex items-center justify-end gap-1.5">
        {row.is_published ? (
          <Button
            variant="secondary"
            size="sm"
            loading={busyId === row.id}
            onClick={() => act(row.id, unpublishEvent, 'Could not unpublish that event.')}
          >
            Unpublish
          </Button>
        ) : (
          <Button
            variant="soft"
            size="sm"
            loading={busyId === row.id}
            onClick={() => act(row.id, publishEvent, 'Could not publish that event.')}
          >
            Publish
          </Button>
        )}
        <Button variant="secondary" size="sm" onClick={() => setEditing(row)}>
          Edit
        </Button>
        <Button
          variant="dangerSoft"
          size="sm"
          loading={busyId === row.id}
          onClick={() => act(row.id, deleteEvent, 'Could not delete that event.')}
        >
          Delete
        </Button>
      </div>
    ),
  }

  const handleSaved = async () => {
    setCreating(false)
    setEditing(null)
    await reload()
  }

  return (
    <Card>
      <CardHeader
        title="School events"
        description="Assemblies, exams, holidays and meetings. Drafts stay private until published."
        action={
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="size-4" aria-hidden="true" />
            New event
          </Button>
        }
      />
      <CardBody className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <SearchInput value={search} onChange={setSearch} placeholder="Search events…" />
        </div>

        <ErrorDisplay message={error} />

        {!loading && rows.length === 0 && !search ? (
          <EmptyState
            icon={CalendarDays}
            title="No events yet"
            description="Create an event and it will appear on everyone’s calendar once published."
            action={
              <Button size="sm" onClick={() => setCreating(true)}>
                New event
              </Button>
            }
          />
        ) : (
          <>
            <DataTable
              columns={[...columns, actionColumn]}
              rows={rows}
              keyField="id"
              loading={loading}
              emptyTitle="No events"
              emptyDescription="Nothing matches that search."
            />
            <Pagination meta={meta} page={page} onPageChange={setPage} />
          </>
        )}
      </CardBody>

      {creating ? (
        <EventForm key="new" open onClose={() => setCreating(false)} event={null} onSaved={handleSaved} />
      ) : null}

      {editing ? (
        <EventForm
          key={editing.id}
          open
          onClose={() => setEditing(null)}
          event={editing}
          onSaved={handleSaved}
        />
      ) : null}
    </Card>
  )
}
