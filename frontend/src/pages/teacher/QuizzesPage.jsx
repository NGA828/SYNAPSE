import { useState } from 'react'
import { BarChart3, EyeOff, Pencil, Plus, Send, Trash2 } from 'lucide-react'
import { usePaginatedList } from '../../hooks/usePaginatedList.js'
import { deleteQuiz, getTeacherQuiz, listTeacherQuizzes, publishQuiz, unpublishQuiz } from '../../services/quizService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody } from '../../components/ui/Card.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'
import { Pagination } from '../../components/ui/Pagination.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { SearchInput } from '../../components/ui/SearchInput.jsx'
import { QuizForm } from '../../components/quizzes/QuizForm.jsx'
import { QuizResultsPanel } from '../../components/quizzes/QuizResultsPanel.jsx'
import { formatDate } from '../../utils/formatters.js'

/**
 * Teacher quiz workspace: build a paper, publish it, then read the auto-marked
 * results.
 */
export default function TeacherQuizzesPage() {
  const { rows, meta, loading, reload, page, setPage, search, setSearch } = usePaginatedList(listTeacherQuizzes)
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState(null)
  const [resultsFor, setResultsFor] = useState(null)

  // The list row carries only a question count; the editor needs the paper
  // itself, including the answer key, so fetch it when a row is opened.
  const openEditor = async (quiz) => setEditing(await getTeacherQuiz(quiz.id))

  const togglePublish = async (quiz) => {
    if (quiz.is_published) await unpublishQuiz(quiz.id)
    else await publishQuiz(quiz.id)
    reload()
  }

  const remove = async (quiz) => {
    if (!window.confirm(`Delete "${quiz.title}"? Every attempt at it will be removed too.`)) return
    await deleteQuiz(quiz.id)
    reload()
  }

  const columns = [
    {
      key: 'title',
      header: 'Quiz',
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-slate-800">{row.title}</p>
          <p className="text-xs text-slate-500">
            {row.subject?.name} · {row.class?.name} · {row.questions_count} question{row.questions_count === 1 ? '' : 's'}
          </p>
        </div>
      ),
    },
    {
      key: 'limits',
      header: 'Limits',
      render: (row) => (
        <span className="text-xs text-slate-500">
          {row.time_limit_minutes ? `${row.time_limit_minutes} min` : 'Untimed'} · {row.attempts_allowed} attempt
          {row.attempts_allowed === 1 ? '' : 's'}
        </span>
      ),
    },
    {
      key: 'attempts_count',
      header: 'Sat',
      render: (row) => <span className="text-slate-600">{row.attempts_count ?? 0}</span>,
    },
    {
      key: 'closes_at',
      header: 'Closes',
      render: (row) =>
        row.closes_at ? (
          <span className="text-slate-600">{formatDate(row.closes_at)}</span>
        ) : (
          <span className="text-slate-400">No deadline</span>
        ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row) =>
        !row.is_published ? (
          <Badge variant="neutral">Draft</Badge>
        ) : row.is_closed ? (
          <Badge variant="danger" dot>
            Closed
          </Badge>
        ) : (
          <Badge variant="success" dot>
            Open
          </Badge>
        ),
    },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-1">
          <Button size="icon" variant="ghost" title="Results" onClick={() => setResultsFor(row)}>
            <BarChart3 className="size-4" />
          </Button>
          <Button size="icon" variant="ghost" title="Edit" onClick={() => openEditor(row)}>
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
        <PageHeader title="Quizzes" description="Auto-marked tests. Students get their mark the moment they submit.">
          <Button onClick={() => setCreating(true)}>
            <Plus className="size-4" />
            New quiz
          </Button>
        </PageHeader>

        <Card>
          <CardBody>
            <div className="mb-4">
              <SearchInput value={search} onChange={setSearch} placeholder="Search quizzes…" />
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
                  emptyTitle="No quizzes yet"
                  emptyDescription="Build your first paper — it stays a draft until you publish it to the class."
                />
                <Pagination meta={meta} page={page} onPageChange={setPage} />
              </>
            )}
          </CardBody>
        </Card>
      </div>

      <QuizForm
        key={editing ? `edit-${editing.id}` : 'create'}
        open={creating || Boolean(editing)}
        onClose={() => {
          setCreating(false)
          setEditing(null)
        }}
        onSaved={reload}
        quiz={editing}
      />

      {resultsFor ? (
        <QuizResultsPanel quizId={resultsFor.id} open onClose={() => setResultsFor(null)} />
      ) : null}
    </PageContainer>
  )
}
