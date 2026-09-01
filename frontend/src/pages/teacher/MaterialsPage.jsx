import { useState } from 'react'
import { EyeOff, Pencil, Plus, Send, Trash2 } from 'lucide-react'
import { usePaginatedList } from '../../hooks/usePaginatedList.js'
import { deleteLesson, listTeacherLessons, publishLesson, unpublishLesson } from '../../services/lessonService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody } from '../../components/ui/Card.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'
import { Pagination } from '../../components/ui/Pagination.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { SearchInput } from '../../components/ui/SearchInput.jsx'
import { LessonForm } from '../../components/materials/LessonForm.jsx'
import { formatDate } from '../../utils/formatters.js'

/**
 * Teacher course-materials workspace: write a lesson, attach files, publish it
 * to the class.
 */
export default function TeacherMaterialsPage() {
  const { rows, meta, loading, reload, page, setPage, search, setSearch } = usePaginatedList(listTeacherLessons)
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState(null)

  const togglePublish = async (lesson) => {
    if (lesson.is_published) await unpublishLesson(lesson.id)
    else await publishLesson(lesson.id)
    reload()
  }

  const remove = async (lesson) => {
    if (!window.confirm(`Delete "${lesson.title}"? Its attached files will be removed too.`)) return
    await deleteLesson(lesson.id)
    reload()
  }

  const columns = [
    {
      key: 'title',
      header: 'Lesson',
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-slate-800">{row.title}</p>
          <p className="text-xs text-slate-500">
            {row.subject?.name} · {row.class?.name}
            {row.topic ? ` · ${row.topic}` : ''}
          </p>
        </div>
      ),
    },
    {
      key: 'attachments',
      header: 'Files',
      render: (row) => <span className="text-slate-600">{row.attachments?.length ?? 0}</span>,
    },
    {
      key: 'minutes',
      header: 'Read',
      render: (row) => (row.minutes ? `${row.minutes} min` : <span className="text-slate-400">—</span>),
    },
    {
      key: 'published_at',
      header: 'Published',
      render: (row) => (row.published_at ? formatDate(row.published_at) : <span className="text-slate-400">Draft</span>),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row) =>
        row.is_published ? (
          <Badge variant="success" dot>
            Published
          </Badge>
        ) : (
          <Badge variant="neutral">Draft</Badge>
        ),
    },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-1">
          <Button size="icon" variant="ghost" title="Edit" onClick={() => setEditing(row)}>
            <Pencil className="size-4" />
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
        <PageHeader title="Course materials" description="Lessons and resources your classes can read and download.">
          <Button onClick={() => setCreating(true)}>
            <Plus className="size-4" />
            New lesson
          </Button>
        </PageHeader>

        <Card>
          <CardBody>
            <div className="mb-4">
              <SearchInput value={search} onChange={setSearch} placeholder="Search lessons…" />
            </div>

            {loading ? (
              <div className="flex justify-center py-16">
                <Spinner className="size-8" />
              </div>
            ) : (
              <>
                <DataTable
                  columns={columns}
                  rows={rows}
                  loading={false}
                  emptyTitle="No lessons yet"
                  emptyDescription="Write your first lesson — it stays a draft until you publish it to the class."
                />
                <Pagination meta={meta} page={page} onPageChange={setPage} />
              </>
            )}
          </CardBody>
        </Card>
      </div>

      <LessonForm
        key={editing ? `edit-${editing.id}` : 'create'}
        open={creating || Boolean(editing)}
        onClose={() => {
          setCreating(false)
          setEditing(null)
        }}
        onSaved={reload}
        lesson={editing}
      />
    </PageContainer>
  )
}
