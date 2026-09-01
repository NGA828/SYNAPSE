import { Plus, Trash2 } from 'lucide-react'
import { Button } from '../ui/Button.jsx'
import { blankQuestion } from './blankQuestion.js'

/**
 * Question list for one quiz.
 *
 * The answer key lives here as an option index, matching what the backend
 * stores — so an option can be reworded without losing which one is right.
 */
export function QuizQuestionEditor({ questions, onChange, disabled = false }) {
  const update = (index, patch) =>
    onChange(questions.map((question, position) => (position === index ? { ...question, ...patch } : question)))

  const setOption = (index, position, value) =>
    update(index, {
      options: questions[index].options.map((option, spot) => (spot === position ? value : option)),
    })

  const addOption = (index) => update(index, { options: [...questions[index].options, ''] })

  const removeOption = (index, position) => {
    const options = questions[index].options.filter((_, spot) => spot !== position)
    const correct = questions[index].correct_option
    update(index, {
      options,
      correct_option: correct === position ? 0 : correct > position ? correct - 1 : correct,
    })
  }

  return (
    <div className="space-y-4">
      {questions.map((question, index) => (
        <div key={index} className="rounded-xl border border-slate-200 p-4">
          <div className="mb-3 flex items-start justify-between gap-2">
            <p className="text-sm font-semibold text-slate-700">Question {index + 1}</p>
            <div className="flex items-center gap-2">
              <label className="flex items-center gap-1.5 text-xs text-slate-500">
                Points
                <input
                  type="number"
                  min="1"
                  max="100"
                  value={question.points}
                  disabled={disabled}
                  onChange={(event) => update(index, { points: Number(event.target.value) || 1 })}
                  className="w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm focus:border-brand-500 focus:outline-none"
                />
              </label>
              {questions.length > 1 ? (
                <Button
                  size="icon"
                  variant="ghost"
                  title="Remove question"
                  disabled={disabled}
                  onClick={() => onChange(questions.filter((_, position) => position !== index))}
                >
                  <Trash2 className="size-4 text-rose-500" />
                </Button>
              ) : null}
            </div>
          </div>

          <textarea
            rows={2}
            value={question.prompt}
            disabled={disabled}
            onChange={(event) => update(index, { prompt: event.target.value })}
            placeholder="Write the question…"
            className="mb-3 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
          />

          <div className="space-y-2">
            {question.options.map((option, position) => (
              <div key={position} className="flex items-center gap-2">
                <input
                  type="radio"
                  name={`correct-${index}`}
                  checked={question.correct_option === position}
                  disabled={disabled}
                  onChange={() => update(index, { correct_option: position })}
                  className="size-4 accent-brand-600"
                  title="Mark as the correct answer"
                />
                <input
                  value={option}
                  disabled={disabled}
                  onChange={(event) => setOption(index, position, event.target.value)}
                  placeholder={`Option ${position + 1}`}
                  className="flex-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none"
                />
                {question.options.length > 2 ? (
                  <Button
                    size="icon"
                    variant="ghost"
                    title="Remove option"
                    disabled={disabled}
                    onClick={() => removeOption(index, position)}
                  >
                    <Trash2 className="size-3.5 text-slate-400" />
                  </Button>
                ) : null}
              </div>
            ))}
          </div>

          {question.options.length < 6 ? (
            <Button size="sm" variant="ghost" className="mt-2" disabled={disabled} onClick={() => addOption(index)}>
              <Plus className="size-3.5" />
              Add option
            </Button>
          ) : null}

          <p className="mt-2 text-xs text-slate-400">The filled radio button marks the correct answer.</p>
        </div>
      ))}

      <Button
        variant="secondary"
        disabled={disabled || questions.length >= 50}
        onClick={() => onChange([...questions, blankQuestion()])}
      >
        <Plus className="size-4" />
        Add question
      </Button>
    </div>
  )
}
