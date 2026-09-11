import { useEffect, useRef, useState } from 'react'
import { AlertTriangle, SendHorizonal, Sparkles } from 'lucide-react'
import { sendAssistantMessage } from '../../services/assistantService.js'
import { cn } from '../../utils/cn.js'
import { Button } from '../ui/Button.jsx'

const GREETING = {
  local: true,
  role: 'assistant',
  content:
    "Hi! I'm your study assistant. Ask me to explain a lesson, help you revise, or practise a subject with you. What are you working on?",
}

const SUGGESTIONS = [
  'Explain photosynthesis like I am 12',
  'Help me revise for a maths test',
  'Give me a one-week study plan',
  'What is the difference between "à" and "a"?',
]

// Turns replayed to the server with each request. The backend caps this too.
const HISTORY_WINDOW = 12

const GENERIC_ERROR = 'The assistant could not answer just now. Please try again in a moment.'
const UNAVAILABLE_ERROR = 'The AI study assistant is not available right now. Please try again later.'
const QUOTA_ERROR =
  'You have used up your study help for today. Come back tomorrow — or ask your teacher in the meantime.'

function errorFor(status) {
  if (status === 429) return QUOTA_ERROR
  if (status === 503) return UNAVAILABLE_ERROR
  return GENERIC_ERROR
}

/**
 * The conversation surface shared by the assistant page and the floating
 * widget. The transcript lives here, in the component — the backend keeps
 * nothing, so closing the panel or the tab is the same as deleting it.
 */
export function AssistantChat({ compact = false }) {
  const [messages, setMessages] = useState([GREETING])
  const [input, setInput] = useState('')
  const [sending, setSending] = useState(false)
  const [error, setError] = useState(null)
  const [remaining, setRemaining] = useState(null)

  const scrollRef = useRef(null)
  const inputRef = useRef(null)

  useEffect(() => {
    const node = scrollRef.current
    if (node) node.scrollTop = node.scrollHeight
  }, [messages, sending, error])

  useEffect(() => {
    if (!sending) inputRef.current?.focus()
  }, [sending])

  const send = async (raw) => {
    const message = String(raw ?? input).trim()

    if (!message || sending) return

    setError(null)
    setInput('')
    setMessages((current) => [...current, { role: 'user', content: message }])
    setSending(true)

    try {
      const history = messages
        .filter((entry) => !entry.local)
        .slice(-HISTORY_WINDOW)
        .map(({ role, content }) => ({ role, content }))

      const { data } = await sendAssistantMessage({ message, history })

      setRemaining(data.remaining ?? null)
      setMessages((current) => [...current, { role: 'assistant', content: data.reply }])
    } catch (exception) {
      setError(errorFor(exception?.response?.status))
    } finally {
      setSending(false)
    }
  }

  const empty = messages.length === 1

  return (
    <div className="flex h-full min-h-0 flex-col">
      <div
        ref={scrollRef}
        className={cn('min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5')}
        aria-live="polite"
      >
        {messages.map((entry, index) => (
          <div
            key={index}
            className={cn('flex', entry.role === 'user' ? 'justify-end' : 'justify-start')}
          >
            {entry.role === 'assistant' ? (
              <span className="mt-1 mr-2 flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                <Sparkles className="size-3.5" aria-hidden="true" />
              </span>
            ) : null}
            <div
              className={cn(
                'max-w-[85%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed',
                entry.role === 'user'
                  ? 'rounded-br-md bg-brand-600 text-white'
                  : 'rounded-bl-md border border-slate-200 bg-white text-slate-700',
              )}
            >
              {entry.content}
            </div>
          </div>
        ))}

        {sending ? (
          <div className="flex justify-start">
            <span className="mt-1 mr-2 flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
              <Sparkles className="size-3.5" aria-hidden="true" />
            </span>
            <div className="flex items-center gap-1.5 rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3.5">
              {[0, 1, 2].map((dot) => (
                <span
                  key={dot}
                  className="size-1.5 animate-bounce rounded-full bg-slate-400"
                  style={{ animationDelay: `${dot * 150}ms` }}
                />
              ))}
            </div>
          </div>
        ) : null}

        {error ? (
          <div className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-sm text-amber-800">
            <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>{error}</p>
          </div>
        ) : null}
      </div>

      {empty && !sending ? (
        <div className="flex flex-wrap gap-2 px-4 pb-3 sm:px-5">
          {SUGGESTIONS.map((suggestion) => (
            <button
              key={suggestion}
              type="button"
              onClick={() => send(suggestion)}
              className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-left text-xs font-medium text-slate-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
            >
              {suggestion}
            </button>
          ))}
        </div>
      ) : null}

      <form
        onSubmit={(event) => {
          event.preventDefault()
          send()
        }}
        className="border-t border-slate-100 px-4 py-3 sm:px-5"
      >
        <div className="flex items-end gap-2">
          <textarea
            ref={inputRef}
            value={input}
            onChange={(event) => setInput(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault()
                send()
              }
            }}
            rows={compact ? 1 : 2}
            maxLength={2000}
            placeholder="Ask anything about your schoolwork…"
            aria-label="Message the study assistant"
            className="max-h-32 min-h-[2.5rem] flex-1 resize-none rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-100 focus:outline-none"
          />
          <Button
            type="submit"
            size="icon"
            loading={sending}
            disabled={!input.trim()}
            aria-label="Send message"
          >
            {sending ? null : <SendHorizonal className="size-4" aria-hidden="true" />}
          </Button>
        </div>
        <p className="mt-2 text-[11px] leading-relaxed text-slate-400">
          AI can get things wrong — double-check anything important before you rely on it.
          {typeof remaining === 'number' && remaining <= 10
            ? ` ${remaining} message${remaining === 1 ? '' : 's'} left today.`
            : ''}
        </p>
      </form>
    </div>
  )
}
