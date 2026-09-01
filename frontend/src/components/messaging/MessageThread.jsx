import { useState } from 'react'
import { Send } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { formatDateTime } from '../../utils/formatters.js'
import { Button } from '../ui/Button.jsx'
import { Textarea } from '../ui/Textarea.jsx'

/**
 * The open thread, oldest first, with the composer underneath.
 *
 * `is_own` comes from the API rather than being recomputed here, so the two
 * cannot disagree about who wrote what.
 */
export function MessageThread({ messages, busy, onSend }) {
  const [draft, setDraft] = useState('')
  const [error, setError] = useState(null)

  const submit = async (event) => {
    event.preventDefault()
    const body = draft.trim()
    if (!body) return

    setError(null)
    try {
      await onSend(body)
      setDraft('')
    } catch (err) {
      setError(err?.response?.data?.message ?? 'Could not send that message.')
    }
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex-1 space-y-3 overflow-y-auto p-4">
        {messages.length === 0 ? (
          <p className="py-10 text-center text-sm text-slate-500">
            No messages yet. Say hello.
          </p>
        ) : null}

        {messages.map((message) => (
          <div
            key={message.id}
            className={cn('flex', message.is_own ? 'justify-end' : 'justify-start')}
          >
            <div
              className={cn(
                'max-w-[78%] rounded-2xl px-3.5 py-2 text-sm shadow-sm',
                message.is_own
                  ? 'rounded-br-sm bg-brand-600 text-white'
                  : 'rounded-bl-sm border border-slate-200 bg-white text-slate-700',
              )}
            >
              {!message.is_own ? (
                <p className="mb-0.5 text-xs font-semibold text-slate-900">
                  {message.sender?.name ?? 'Unknown'}
                </p>
              ) : null}
              <p className="whitespace-pre-wrap break-words">{message.body}</p>
              <p
                className={cn(
                  'mt-1 text-[11px]',
                  message.is_own ? 'text-brand-100' : 'text-slate-400',
                )}
              >
                {formatDateTime(message.created_at)}
                {message.is_own ? (message.read_at ? ' · Read' : ' · Sent') : ''}
              </p>
            </div>
          </div>
        ))}
      </div>

      <form onSubmit={submit} className="border-t border-slate-200 bg-white p-3">
        <Textarea
          label="Message"
          rows={2}
          value={draft}
          error={error}
          placeholder="Write your message…"
          onChange={(event) => setDraft(event.target.value)}
        />
        <div className="mt-2 flex items-center justify-end gap-2">
          <Button type="submit" size="sm" loading={busy} disabled={!draft.trim()}>
            <Send className="size-4" aria-hidden="true" />
            Send
          </Button>
        </div>
      </form>
    </div>
  )
}
