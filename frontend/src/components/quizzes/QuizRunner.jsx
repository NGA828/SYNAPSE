import { useCallback, useEffect, useState } from 'react'
import { CheckCircle2, XCircle } from 'lucide-react'
import { Modal } from '../ui/Modal.jsx'
import { Button } from '../ui/Button.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { getQuizPaper, submitQuiz } from '../../services/quizService.js'

/**
 * Sits one quiz.
 *
 * The paper arrives with no answer key — options are rendered in the order the
 * server sent them and recorded by index, so nothing here can reveal which one
 * is right.
 *
 * The timer keeps a deadline timestamp rather than a decrementing counter, so
 * the effect only ever schedules work and never calls setState synchronously.
 */
export function QuizRunner({ quizId, onClose, onSubmitted }) {
  const [paper, setPaper] = useState(null)
  const [answers, setAnswers] = useState({})
  const [result, setResult] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState(null)
  const [deadline, setDeadline] = useState(null)
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    let active = true

    getQuizPaper(quizId)
      .then((data) => {
        if (!active) return
        setPaper(data)
        if (data.quiz.time_limit_minutes) {
          setDeadline(Date.now() + data.quiz.time_limit_minutes * 60_000)
        }
      })
      .catch((err) => {
        if (active) setError(err?.response?.data?.message ?? 'Could not load this quiz.')
      })

    return () => {
      active = false
    }
  }, [quizId])

  useEffect(() => {
    if (!deadline) return undefined

    const timer = setInterval(() => setNow(Date.now()), 1000)
    return () => clearInterval(timer)
  }, [deadline])

  const questions = paper?.questions ?? []
  const answered = questions.filter((question) => answers[question.id] !== undefined).length

  const submit = useCallback(async () => {
    setSubmitting(true)
    setError(null)

    try {
      const attempt = await submitQuiz(quizId, answers)
      setResult(attempt)
      onSubmitted?.(attempt)
    } catch (err) {
      setError(err?.response?.data?.message ?? 'Could not submit this quiz.')
    } finally {
      setSubmitting(false)
    }
  }, [answers, onSubmitted, quizId])

  const secondsLeft = deadline ? Math.max(0, Math.ceil((deadline - now) / 1000)) : null
  // Time up is a rendered state the student confirms, not a side effect fired
  // from the clock. The server enforces the same limit either way.
  const timedOut = secondsLeft === 0
  const minutes = secondsLeft === null ? null : Math.floor(secondsLeft / 60)
  const seconds = secondsLeft === null ? null : String(secondsLeft % 60).padStart(2, '0')

  return (
    <Modal
      open
      onClose={onClose}
      title={result ? 'Result' : paper?.quiz?.title ?? 'Quiz'}
      description={
        result
          ? undefined
          : `${questions.length} question${questions.length === 1 ? '' : 's'}${minutes !== null ? ` · ${minutes}:${seconds} left` : ''}`
      }
    >
      {result ? (
        <div className="space-y-4 text-center">
          <p className="text-4xl font-bold text-slate-900">
            {Number(result.score).toFixed(2)}
            <span className="text-lg font-medium text-slate-400"> / {result.max_score}</span>
          </p>
          <p className="text-sm text-slate-600">
            {result.correct_count} of {result.total_questions} correct
            {result.percentage !== null ? ` · ${result.percentage}%` : ''}
          </p>
          <p className="text-xs text-slate-400">Marked automatically. Your teacher can add feedback later.</p>
          <Button onClick={onClose}>Done</Button>
        </div>
      ) : paper ? (
        <div className="space-y-4">
          {paper.quiz.instructions ? (
            <p className="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{paper.quiz.instructions}</p>
          ) : null}

          <div className="flex items-center justify-between text-xs text-slate-500">
            <span>
              {answered} of {questions.length} answered
            </span>
            <Badge variant={secondsLeft !== null && secondsLeft < 60 ? 'warning' : 'neutral'}>
              {paper.attempts_remaining} attempt{paper.attempts_remaining === 1 ? '' : 's'} left
            </Badge>
          </div>

          <div className="max-h-96 space-y-4 overflow-y-auto pr-1">
            {questions.map((question, index) => (
              <div key={question.id} className="rounded-xl border border-slate-200 p-3">
                <p className="mb-2 text-sm font-medium text-slate-800">
                  {index + 1}. {question.prompt}
                  <span className="ml-1 text-xs font-normal text-slate-400">
                    ({question.points} pt{question.points === 1 ? '' : 's'})
                  </span>
                </p>
                <div className="space-y-1.5">
                  {question.options.map((option, position) => (
                    <label
                      key={position}
                      className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                    >
                      <input
                        type="radio"
                        name={`question-${question.id}`}
                        checked={answers[question.id] === position}
                        onChange={() => setAnswers((current) => ({ ...current, [question.id]: position }))}
                        className="size-4 accent-brand-600"
                      />
                      {option}
                    </label>
                  ))}
                </div>
              </div>
            ))}
          </div>

          {timedOut ? (
            <p className="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-700">
              The time limit has elapsed. Submit now — the server will not accept a later answer.
            </p>
          ) : null}

          {error ? <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</p> : null}

          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={onClose} type="button">
              Cancel
            </Button>
            <Button onClick={submit} loading={submitting} variant={timedOut ? 'danger' : 'primary'}>
              {timedOut ? "Time's up — submit now" : 'Submit answers'}
            </Button>
          </div>
        </div>
      ) : error ? (
        <div className="space-y-3">
          <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</p>
          <div className="flex justify-end">
            <Button variant="secondary" onClick={onClose}>
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
  )
}

/** Per-question correctness for the review screen. */
export function ReviewMarkers({ questions }) {
  return (
    <div className="space-y-3">
      {questions.map((question, index) => (
        <div key={question.id} className="rounded-xl border border-slate-200 p-3">
          <div className="mb-2 flex items-start gap-2">
            {question.is_correct ? (
              <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" />
            ) : (
              <XCircle className="mt-0.5 size-4 shrink-0 text-rose-500" />
            )}
            <p className="text-sm font-medium text-slate-800">
              {index + 1}. {question.prompt}
            </p>
          </div>
          <div className="ml-6 space-y-1">
            {question.options.map((option, position) => (
              <p
                key={position}
                className={
                  position === question.correct_option
                    ? 'text-sm font-medium text-emerald-700'
                    : position === question.chosen
                      ? 'text-sm text-rose-600 line-through'
                      : 'text-sm text-slate-500'
                }
              >
                {option}
                {position === question.correct_option ? ' — correct' : ''}
              </p>
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}
