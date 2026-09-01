import { useState } from 'react'
import { ClipboardList, EyeOff, Plus, Send, Trash2, Users } from 'lucide-react'
import { usePaginatedList } from '../../hooks/usePaginatedList.js'
import {
  deleteHomework,
  listTeacherHomework,
  publishHomework,
  unpublishHomework,
} from '../../services/homeworkService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody } from '../../components/ui/Card.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'
import { Pagination } from '../../components/ui/Pagination.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { HomeworkForm } from '../../components/homework/HomeworkForm.jsx'
import { SubmissionsPanel } from '../../components/homework/SubmissionsPanel.jsx'
import { formatDate } from '../../utils/formatters.js'

/**
 * Teacher homework workspace: draft → publish → mark → return.
 *
 * `selected` switches the page into the roster/marking view for one item.
 */
export default function TeacherHomeworkPage() {
  const { rows, meta, loading, reload, page, setPage } = usePaginatedList(listTeacherHomework)
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState(null)
  const [selected, setSelected] = useState(null)

  const togglePublish = async (homework) => {
    if (homework.is_published) await unpublishHomework(homework.id)
    else await publishHomework(homework.id)
    reload()
  }

  const remove = async (homework) => {
    if (!window.confirm(`Delete "${homework.title}"? Submissions will be removed too.`)) return
    await deleteHomework(homework.id)
    reload()
  }

  if (selected) {
    return (
      <PageContainer>
        <SubmissionsPanel homeworkId={selected} onBack={() => setSelected(null)} />
      </PageContainer>
    )
  }

  const columns = [
    {
      key: 'title',
      header: 'Homework',
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-slate-800">{row.title}</p>
          <p className="text-xs text-slate-500">
            {row.subject?.name} · {row.class?.name}
          </p>
        </div>
      ),
    },
    {
      key: 'due_at',
      header: 'Due',
      render: (row) => <span className="whitespace-nowrap text-slate-700">{formatDate(row.due_at)}</span>,
    },
    {
      key: 'progress',
      header: 'Marked',
      render: (row) => (
        <span className="whitespace-nowrap text-slate-600">
          {row.graded_count ?? 0}/{row.submissions_count ?? 0}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row) => {
        if (!row.is_published) return <Badge variant="neutral">Draft</Badge>
        return row.is_past_due ? (
          <Badge variant="warning" dot>
            Closed
          </Badge>
        ) : (
          <Badge variant="success" dot>
            Open
          </Badge>
        )
      },
    },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-1">
          <Button size="icon" variant="ghost" title="View submissions" onClick={() => setSelected(row.id)}>
            <Users className="size-4" />
          </Button>
          <Button size="icon" variant="ghost" title="Edit" onClick={() => setEditing(row)}>
            <ClipboardList className="size-4" />
          </Button>
          <Button
            size="icon"
            variant="ghost"
            title={row.is_published ? 'Withdraw' : 'Publish to class'}
            onClick={() => togglePublish(row)}
          >
            {row.is_published ? <EyeOff className="size-4" /> : <Send className="size-4" />}
          </Button>
          <Button size="icon" variant="ghost" title="Delete" onClick={() => remove(row)}>
            <Trash2 className="size-4 text-rose-500" />
          </Button>
        </div>
      ),
    },
  ]

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Homework"
          description="Set work for your classes, then mark and return it."
        >
          <Button onClick={() => setCreating(true)}>
            <Plus className="size-4" />
            Set homework
          </Button>
        </PageHeader>

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : rows.length === 0 ? (
          <Card className="p-12 text-center">
            <p className="text-sm text-slate-500">
              You have not set any homework yet. Start with a draft — it stays hidden from students until you
              publish it.
            </p>
          </Card>
        ) : (
          <Card>
            <CardBody>
              <DataTable columns={columns} rows={rows} loading={loading} />
              <Pagination meta={meta} page={page} onPageChange={setPage} />
            </CardBody>
          </Card>
        )}
      </div>

      <HomeworkForm
        key={editing ? `edit-${editing.id}` : 'create'}
        open={creating || Boolean(editing)}
        onClose={() => {
          setCreating(false)
          setEditing(null)
        }}
        onSaved={reload}
        homework={editing}
      />
    </PageContainer>
  )
}
