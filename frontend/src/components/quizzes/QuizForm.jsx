import { useState } from 'react'
import { Modal } from '../ui/Modal.jsx'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Select } from '../ui/Select.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listAssignments } from '../../services/teacherService.js'
import { createQuiz, updateQuiz } from '../../services/quizService.js'
import { AttachmentPicker } from '../homework/AttachmentPicker.jsx'
import { AttachmentList } from '../homework/AttachmentList.jsx'
import { QuizQuestionEditor } from './QuizQuestionEditor.jsx'
import { blankQuestion } from './blankQuestion.js'

/**
 * Create/edit dialog for one quiz, questions included.
 *
 * Remounted per open via `key`, so the seeded state below is the only source of
 * truth and no effect is needed to keep it in sync.
 */
export function QuizForm({ open, onClose, onSaved, quiz }) {
  const { data: assignments } = useAsyncList(listAssignments)
  const editing = Boolean(quiz)

  const [form, setForm] = useState(() =>
    quiz
      ? {
          class_id: String(quiz.class?.id ?? ''),
          subject_id: String(quiz.subject?.id ?? ''),
          title: quiz.title ?? '',
          instructions: quiz.instructions ?? '',
          max_score: String(quiz.max_score ?? 20),
          closes_at: quiz.closes_at ? quiz.closes_at.slice(0, 16) : '',
          time_limit_minutes: quiz.time_limit_minutes ? String(quiz.time_limit_minutes) : '',
          attempts_allowed: String(quiz.attempts_allowed ?? 1),
        }
      : {
          class_id: '', subject_id: '', title: '', instructions: '',
          max_score: '20', closes_at: '', time_limit_minutes: '', attempts_allowed: '1',
        },
  )
  const [questions, setQuestions] = useState(() =>
    quiz?.questions?.length
      ? quiz.questions.map((question) => ({
          prompt: question.prompt,
          options: [...(question.options ?? [])],
          correct_option: question.correct_option ?? 0,
          points: question.points ?? 1,
        }))
      : [blankQuestion()],
  )
  const [files, setFiles] = useState([])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const set = (key) => (event) => setForm((current) => ({ ...current, [key]: event.target.value }))

  const options = assignments ?? []
  const classIds = [...new Set(options.map((item) => item.class?.id).filter(Boolean))]
  const subjectIds = [...new Set(options.map((item) => item.subject?.id).filter(Boolean))]

  const incomplete = questions.some(
    (question) => !question.prompt.trim() || question.options.some((option) => !option.trim()),
  )

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError(null)

    const payload = {
      class_id: Number(form.class_id),
      subject_id: Number(form.subject_id),
      title: form.title,
      instructions: form.instructions || null,
      max_score: Number(form.max_score),
      closes_at: form.closes_at ? new Date(form.closes_at).toISOString() : null,
      time_limit_minutes: form.time_limit_minutes ? Number(form.time_limit_minutes) : null,
      attempts_allowed: Number(form.attempts_allowed),
      questions: questions.map((question, index) => ({
        ...question,
        options: question.options.filter((option) => option.trim() !== ''),
        sequence: index + 1,
      })),
    }

    try {
      const saved = editing
        ? await updateQuiz(quiz.id, payload, files)
        : await createQuiz(payload, files)
      onSaved?.(saved)
      onClose()
    } catch (err) {
      const first = Object.values(err?.response?.data?.errors ?? {})[0]?.[0]
      setError(first ?? err?.response?.data?.message ?? 'Could not save this quiz.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={editing ? 'Edit quiz' : 'New quiz'}
      description={
        editing && quiz.is_locked
          ? 'Students have sat this paper, so the questions are locked.'
          : 'It stays a draft until you publish it.'
      }
    >
      <form onSubmit={handleSubmit} className="mt-4 space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Select label="Class" name="class_id" value={form.class_id} onChange={set('class_id')} disabled={editing} required>
            <option value="">Select a class…</option>
            {classIds.map((id) => (
              <option key={id} value={id}>
                {options.find((item) => item.class?.id === id)?.class?.name}
              </option>
            ))}
          </Select>

          <Select label="Subject" name="subject_id" value={form.subject_id} onChange={set('subject_id')} disabled={editing} required>
            <option value="">Select a subject…</option>
            {subjectIds.map((id) => (
              <option key={id} value={id}>
                {options.find((item) => item.subject?.id === id)?.subject?.name}
              </option>
            ))}
          </Select>
        </div>

        <Input label="Title" name="title" value={form.title} onChange={set('title')} maxLength={180} required />
        <Textarea label="Instructions" name="instructions" value={form.instructions} onChange={set('instructions')} rows={2} maxLength={5000} />

        <div className="grid gap-4 sm:grid-cols-4">
          <Input label="Max score" name="max_score" type="number" min="1" max="100" value={form.max_score} onChange={set('max_score')} required />
          <Input
            label="Time limit"
            name="time_limit_minutes"
            type="number"
            min="1"
            max="300"
            value={form.time_limit_minutes}
            onChange={set('time_limit_minutes')}
            hint="Minutes, or blank"
          />
          <Input label="Attempts" name="attempts_allowed" type="number" min="1" max="5" value={form.attempts_allowed} onChange={set('attempts_allowed')} />
          <Input label="Closes" name="closes_at" type="datetime-local" value={form.closes_at} onChange={set('closes_at')} />
        </div>

        <div>
          <p className="mb-2 text-sm font-medium text-slate-700">Questions</p>
          <QuizQuestionEditor
            questions={questions}
            onChange={setQuestions}
            disabled={editing && quiz?.is_locked}
          />
          {incomplete ? (
            <p className="mt-2 text-xs text-amber-600">Every question needs a prompt and no empty options before you can publish.</p>
          ) : null}
        </div>

        <AttachmentPicker files={files} onChange={setFiles} label={editing ? 'Add more files' : 'Attach a paper or diagram'} />

        {editing && quiz.attachments?.length ? (
          <AttachmentList attachments={quiz.attachments} label="Already attached" />
        ) : null}

        {error ? <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</p> : null}

        <div className="flex justify-end gap-2 pt-1">
          <Button variant="secondary" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button type="submit" loading={saving}>
            {editing ? 'Save changes' : 'Create draft'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
