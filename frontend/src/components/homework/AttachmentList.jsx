import { Download, FileText } from 'lucide-react'
import { downloadAttachment } from '../../services/homeworkService.js'
import { Button } from '../ui/Button.jsx'

const iconFor = (fileName) => {
  const ext = String(fileName).split('.').pop()?.toLowerCase()
  return ext
}

/**
 * Renders a list of uploaded files with a download button each.
 *
 * Downloads go through the authorized endpoint — the file id alone grants
 * nothing, the backend re-checks on every request.
 */
export function AttachmentList({ attachments, label = 'Attachments', emptyHint }) {
  if (!attachments || attachments.length === 0) {
    return emptyHint ? <p className="text-xs text-slate-400">{emptyHint}</p> : null
  }

  return (
    <div>
      <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
      <ul className="space-y-1.5">
        {attachments.map((file) => (
          <li
            key={file.id}
            className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5"
          >
            <span className="flex min-w-0 items-center gap-2">
              <FileText className="size-4 shrink-0 text-brand-500" aria-hidden="true" />
              <span className="truncate text-sm text-slate-700">{file.file_name}</span>
              <span className="shrink-0 text-xs text-slate-400">{file.size_label}</span>
            </span>
            <Button
              size="sm"
              variant="ghost"
              onClick={() => downloadAttachment(file)}
              title={`Download ${file.file_name}`}
            >
              <Download className="size-4" />
              <span className="hidden sm:inline">Download</span>
            </Button>
          </li>
        ))}
      </ul>
      <p className="mt-1 text-[11px] text-slate-400">
        {attachments.map((file) => iconFor(file.file_name)).filter(Boolean).join(' · ')}
      </p>
    </div>
  )
}
