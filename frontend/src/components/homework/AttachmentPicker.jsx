import { useRef } from 'react'
import { Paperclip, X } from 'lucide-react'
import { Button } from '../ui/Button.jsx'

const ACCEPT = '.pdf,.doc,.docx,.odt,.rtf,.txt,.png,.jpg,.jpeg'

const sizeLabel = (bytes) => {
  if (bytes >= 1048576) return `${Math.round((bytes / 1048576) * 10) / 10} MB`
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`
  return `${bytes} B`
}

/**
 * File chooser for uploads. Held as a File[] in the parent's state so it can be
 * posted as multipart; nothing is uploaded until the form is submitted.
 */
export function AttachmentPicker({ files, onChange, max = 5, label = 'Attach files' }) {
  const inputRef = useRef(null)

  const add = (event) => {
    const picked = Array.from(event.target.files ?? [])
    onChange([...files, ...picked].slice(0, max))
    // Clearing the input lets the same file be chosen again after removal.
    event.target.value = ''
  }

  const remove = (index) => onChange(files.filter((_, i) => i !== index))

  return (
    <div>
      <p className="mb-1.5 text-sm font-medium text-slate-700">{label}</p>

      <input
        ref={inputRef}
        type="file"
        accept={ACCEPT}
        multiple
        className="hidden"
        onChange={add}
        aria-label={label}
      />

      <Button type="button" variant="secondary" size="sm" onClick={() => inputRef.current?.click()}>
        <Paperclip className="size-4" />
        Choose files
      </Button>

      {files.length > 0 ? (
        <ul className="mt-2 space-y-1.5">
          {files.map((file, index) => (
            <li
              key={`${file.name}-${index}`}
              className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5"
            >
              <span className="min-w-0 truncate text-sm text-slate-700">{file.name}</span>
              <span className="flex shrink-0 items-center gap-2">
                <span className="text-xs text-slate-400">{sizeLabel(file.size)}</span>
                <button
                  type="button"
                  onClick={() => remove(index)}
                  className="rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600"
                  aria-label={`Remove ${file.name}`}
                >
                  <X className="size-4" />
                </button>
              </span>
            </li>
          ))}
        </ul>
      ) : null}

      <p className="mt-1.5 text-xs text-slate-500">
        PDF, Word, ODT, RTF, TXT, PNG or JPG · up to 10 MB each · {max} files max
      </p>
    </div>
  )
}
