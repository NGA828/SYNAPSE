import { useState } from 'react'
import { ClipboardCheck, ListChecks, PlayCircle, TrendingUp } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getAttemptReview, listStudentQuizzes } from '../../services/quizService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { StatCard } from '../../components/dashboard/StatCard.jsx'
import { EmptyState } from '../../components/dashboard/EmptyState.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { Modal } from '../../components/ui/Modal.jsx'
import { AttachmentList } from '../../components/homework/AttachmentList.jsx'
import { QuizRunner, ReviewMarkers } from '../../components/quizzes/QuizRunner.jsx'
import { formatDateTime } from '../../utils/formatters.js'

/**
 * Student quizzes: what is open, what has been marked, and a per-question
 * review of any attempt already submitted.
 */
export default function StudentQuizzesPage() {
  const { data, loading, reload } = useAsyncList(listStudentQuizzes)
  const [sittingId, setSittingId] = useState(null)
  const [review, setReview] = useState(null)

  const rows = data?.data ?? []
  const summary = data?.summary ?? null

  const openReview = async (attemptId) => setReview(await getAttemptReview(attemptId))

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Quizzes" description="Tests that mark themselves the moment you submit." />

        {summary ? (
          <div className="grid gap-4 sm:grid-cols-3">
            <StatCard label="Quizzes available" value={summary.available} icon={ListChecks} tone="brand" />
            <StatCard label="Completed" value={summary.completed} icon={ClipboardCheck} tone="emerald" />
            <StatCard
              label="Average mark"
              value={summary.average === null ? '—' : Number(summary.average).toFixed(2)}
              icon={TrendingUp}
              tone="violet"
            />
          </div>
        ) : null}

        {loading ? (
          <div className="flex justify-center py-16">
            <Spinner className="size-8" />
          </div>
        ) : rows.length === 0 ? (
          <Card>
            <CardBody>
              <EmptyState
                icon={ListChecks}
                title="No quizzes yet"
                description="When a teacher publishes a quiz to your class, it will appear here."
              />
            </CardBody>
          </Card>
        ) : (
          <div className="grid gap-3 lg:grid-cols-2">
            {rows.map((row) => {
              const remaining = (row.quiz.attempts_allowed ?? 1) - row.attempts_used
              const canSit = row.quiz.is_open && !row.quiz.is_closed && remaining > 0

              return (
                <Card key={row.quiz.id}>
                  <CardHeader
                    title={row.quiz.title}
                    description={row.quiz.instructions ?? undefined}
                    action={
                      canSit ? (
                        <Button size="sm" onClick={() => setSittingId(row.quiz.id)}>
                          <PlayCircle className="size-4" />
                          Start
                        </Button>
                      ) : row.attempt ? (
                        <Button size="sm" variant="secondary" onClick={() => openReview(row.attempt.id)}>
                          Review
                        </Button>
                      ) : null
                    }
                  />
                  <CardBody>
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                      <Badge variant="info">
                        {row.quiz.questions_count} question{row.quiz.questions_count === 1 ? '' : 's'}
                      </Badge>
                      {row.quiz.time_limit_minutes ? (
                        <Badge variant="neutral">{row.quiz.time_limit_minutes} min</Badge>
                      ) : null}
                      {row.quiz.is_closed ? (
                        <Badge variant="danger">Closed</Badge>
                      ) : (
                        <Badge variant="neutral">
                          {remaining} attempt{remaining === 1 ? '' : 's'} left
                        </Badge>
                      )}
                      {row.attempt ? (
                        <Badge variant="success" dot>
                          {Number(row.attempt.score).toFixed(2)} / {row.attempt.max_score}
                        </Badge>
                      ) : null}
                      {row.attempt?.is_reviewed ? <Badge variant="violet">Feedback ready</Badge> : null}
                    </div>

                    <p className="text-xs text-slate-500">
                      {row.quiz.subject?.name} · {row.quiz.class?.name}
                      {row.attempt?.submitted_at ? ` · submitted ${formatDateTime(row.attempt.submitted_at)}` : ''}
                    </p>

                    {row.attempt?.feedback ? (
                      <p className="mt-2 rounded-xl bg-violet-50 px-3 py-2 text-sm text-violet-800">
                        {row.attempt.feedback}
                      </p>
                    ) : null}

                    <div className="mt-3">
                      <AttachmentList attachments={row.quiz.attachments} label="Attached material" />
                    </div>
                  </CardBody>
                </Card>
              )
            })}
          </div>
        )}
      </div>

      {sittingId ? (
        <QuizRunner
          quizId={sittingId}
          onClose={() => setSittingId(null)}
          onSubmitted={() => reload()}
        />
      ) : null}

      <Modal
        open={Boolean(review)}
        onClose={() => setReview(null)}
        title={review ? `Review: ${review.quiz.title}` : 'Review'}
        description={
          review?.attempt
            ? `${review.attempt.correct_count}/${review.attempt.total_questions} correct · ${Number(review.attempt.score).toFixed(2)} / ${review.attempt.max_score}`
            : undefined
        }
      >
        {review ? (
          <div className="mt-4 space-y-4">
            {review.attempt.feedback ? (
              <p className="rounded-xl bg-violet-50 px-3 py-2 text-sm text-violet-800">{review.attempt.feedback}</p>
            ) : null}

            <div className="max-h-96 overflow-y-auto pr-1">
              <ReviewMarkers questions={review.questions} />
            </div>

            <div className="flex justify-end">
              <Button variant="secondary" onClick={() => setReview(null)}>
                Close
              </Button>
            </div>
          </div>
        ) : (
          <div className="flex justify-center py-10">
            <Spinner className="size-7" />
          </div>
        )}
      </Modal>
    </PageContainer>
  )
}
