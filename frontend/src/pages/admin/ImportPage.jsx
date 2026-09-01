import { useRef, useState } from 'react'
import { Download, FileUp, CheckCircle2, AlertTriangle, ArrowRight } from 'lucide-react'
import { importRows, parseCsvText, previewImport } from '../../services/importService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Select } from '../../components/ui/Select.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'

const TEMPLATES = {
  students: 'name,email,matricule,class,password\nJane Doe,jane@school.edu,ST2026-100,Level 3A,password123',
  teachers: 'name,email,staff_no,password\nMr. James,james@school.edu,TCH-100,password123',
}

const CONFIDENCE_META = {
  exact: { label: 'Matched', variant: 'success' },
  fuzzy: { label: 'Guessed — check this', variant: 'warning' },
  suggested: { label: 'AI suggestion — check this', variant: 'warning' },
}

const FIELD_LABELS = {
  name: 'Name',
  email: 'Email',
  matricule: 'Matricule',
  staff_no: 'Staff no.',
  class: 'Class',
  academic_year: 'Academic year',
  phone: 'Phone',
  password: 'Password',
}

const REQUIRED_FIELDS = {
  students: ['name', 'email'],
  teachers: ['name', 'email'],
}

export default function ImportPage() {
  const fileRef = useRef(null)

  const [type, setType] = useState('students')
  const [rows, setRows] = useState([])
  const [fileName, setFileName] = useState('')
  const [parseError, setParseError] = useState(null)

  const [preview, setPreview] = useState(null)
  const [previewing, setPreviewing] = useState(false)
  const [previewError, setPreviewError] = useState(null)

  const [submitting, setSubmitting] = useState(false)
  const [result, setResult] = useState(null)

  const reset = () => {
    setRows([])
    setFileName('')
    setPreview(null)
    setPreviewError(null)
    setResult(null)
  }

  /*
   * The file is parsed into rows keyed by its own header text and sent to the
   * server as-is. The server decides what each column means — doing it here used
   * to lowercase the headers, which is what made a French export unreadable.
   */
  const handleFile = (event) => {
    const file = event.target.files?.[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = () => {
      try {
        const { headers, rows: parsedRows } = parseCsvText(String(reader.result))
        if (headers.length === 0 || parsedRows.length === 0) {
          setParseError('That file has no header row and data row to read.')
          return
        }
        setRows(parsedRows)
        setFileName(file.name)
        setParseError(null)
        setPreview(null)
        setResult(null)
      } catch {
        setParseError('Could not parse that CSV file.')
      }
    }
    reader.readAsText(file)
    event.target.value = ''
  }

  const downloadTemplate = () => {
    const blob = new Blob([TEMPLATES[type]], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `${type}-template.csv`
    anchor.click()
    URL.revokeObjectURL(url)
  }

  const handlePreview = async () => {
    setPreviewing(true)
    setPreviewError(null)
    setResult(null)
    try {
      setPreview(await previewImport({ type, rows }))
    } catch (err) {
      setPreviewError(err?.response?.data?.message ?? 'Could not read that file.')
    } finally {
      setPreviewing(false)
    }
  }

  const handleImport = async () => {
    if (!preview) return
    setSubmitting(true)
    setResult(null)
    try {
      // The confirmed mapping goes back with the rows, so the import cannot
      // reinterpret a column differently from the preview the admin just read.
      setResult(await importRows(type, rows, preview.mapping))
    } catch (err) {
      setResult({ created: 0, skipped: 0, errors: [{ row: null, message: err?.response?.data?.message ?? 'Import failed.' }] })
    } finally {
      setSubmitting(false)
    }
  }

  const missingRequired = preview
    ? REQUIRED_FIELDS[type].filter((field) => !preview.mapping[field])
    : []
  const blocked = missingRequired.length > 0
  const needsAttention = preview?.summary?.needs_attention ?? 0

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Bulk import" description="Import students or teachers from a CSV file." />

        <Card>
          <CardHeader title="1. Choose what to import" />
          <CardBody className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <Select
              name="type"
              label="Import type"
              className="sm:max-w-xs"
              value={type}
              onChange={(event) => {
                setType(event.target.value)
                reset()
              }}
            >
              <option value="students">Students</option>
              <option value="teachers">Teachers</option>
            </Select>
            <Button variant="secondary" onClick={downloadTemplate}>
              <Download className="size-4" aria-hidden="true" />
              Download CSV template
            </Button>
            <p className="text-xs text-slate-400 sm:mb-2.5">
              Your file does not have to use these column names — “Nom”, “Courriel” and “Classe”
              are understood.
            </p>
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="2. Upload your file" description="A CSV with a header row. Columns are matched by name, in English or French." />
          <CardBody>
            <input ref={fileRef} type="file" accept=".csv,text/csv" className="hidden" onChange={handleFile} />
            <div
              onClick={() => fileRef.current?.click()}
              className="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 px-6 py-10 text-center transition hover:border-brand-400 hover:bg-brand-50/30"
            >
              <FileUp className="size-8 text-slate-400" aria-hidden="true" />
              <p className="mt-3 text-sm font-semibold text-slate-700">
                {fileName || 'Click to choose a CSV file'}
              </p>
              <p className="mt-1 text-xs text-slate-400">CSV files only</p>
            </div>
            <div className="mt-3"><ErrorDisplay message={parseError} /></div>

            {rows.length > 0 && !preview ? (
              <div className="mt-4 flex justify-end">
                <Button onClick={handlePreview} loading={previewing}>
                  <ArrowRight className="size-4" aria-hidden="true" />
                  Check the file
                </Button>
              </div>
            ) : null}
            <div className="mt-3"><ErrorDisplay message={previewError} /></div>
          </CardBody>
        </Card>

        {preview ? (
          <Card>
            <CardHeader
              title="3. Check the columns"
              description={`${preview.summary.total} row${preview.summary.total === 1 ? '' : 's'} read. Nothing has been created yet.`}
              action={
                <Badge variant={preview.source === 'deterministic' ? 'neutral' : 'info'} dot>
                  {preview.source === 'deterministic' ? 'Matched by rule' : 'AI-assisted'}
                </Badge>
              }
            />
            <CardBody className="space-y-4">
              <div className="grid gap-2 sm:grid-cols-2">
                {preview.fields.map((field) => {
                  const header = preview.mapping[field]
                  const confidence = preview.confidence[field]
                  const meta = CONFIDENCE_META[confidence]
                  const required = REQUIRED_FIELDS[type].includes(field)

                  return (
                    <div
                      key={field}
                      className="flex items-center justify-between gap-2 rounded-xl border border-slate-200 px-3.5 py-2.5"
                    >
                      <span className="text-sm text-slate-700">
                        {FIELD_LABELS[field] ?? field}
                        {required ? <span className="text-rose-500"> *</span> : null}
                      </span>
                      {header ? (
                        <span className="flex min-w-0 items-center gap-2">
                          <span className="truncate font-mono text-xs text-slate-500">{header}</span>
                          {meta ? (
                            <Badge variant={meta.variant}>{meta.label}</Badge>
                          ) : null}
                        </span>
                      ) : (
                        <Badge variant={required ? 'danger' : 'neutral'}>
                          {required ? 'Not found' : 'Not used'}
                        </Badge>
                      )}
                    </div>
                  )
                })}
              </div>

              {preview.unmapped.length > 0 ? (
                <p className="flex items-start gap-2 text-xs text-slate-500">
                  <AlertTriangle className="mt-0.5 size-3.5 shrink-0 text-amber-500" aria-hidden="true" />
                  These columns will be ignored: {preview.unmapped.join(', ')}.
                </p>
              ) : null}

              {blocked ? (
                <p className="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-3 text-sm text-rose-700">
                  <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                  No column was matched to {missingRequired.join(' and ')}, so nothing can be
                  imported. Add that column to the file and check it again.
                </p>
              ) : null}

              <div className="scrollbar-thin max-h-72 overflow-auto rounded-xl border border-slate-200">
                <table className="w-full min-w-[34rem] text-sm">
                  <thead className="sticky top-0 bg-slate-50">
                    <tr className="text-left text-xs uppercase tracking-wide text-slate-400">
                      <th className="px-3 py-2 font-semibold">Name</th>
                      <th className="px-3 py-2 font-semibold">Email</th>
                      {type === 'students' ? (
                        <>
                          <th className="px-3 py-2 font-semibold">Matricule</th>
                          <th className="px-3 py-2 font-semibold">Class</th>
                        </>
                      ) : (
                        <th className="px-3 py-2 font-semibold">Staff no.</th>
                      )}
                      <th className="px-3 py-2 font-semibold">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {preview.rows.map((row) => (
                      <tr key={row.row} className="border-b border-slate-50 align-top">
                        <td className="px-3 py-2 text-slate-700">{row.values.name ?? '—'}</td>
                        <td className="px-3 py-2 text-slate-600">{row.values.email ?? '—'}</td>
                        {type === 'students' ? (
                          <>
                            <td className="px-3 py-2 font-mono text-xs text-slate-500">
                              {row.values.matricule ?? '—'}
                            </td>
                            <td className="px-3 py-2 text-slate-600">
                              {row.class.matched ?? row.class.label ?? '—'}
                              {row.class.label && row.class.matched && row.class.label !== row.class.matched ? (
                                <span className="block text-xs text-slate-400">
                                  from “{row.class.label}”
                                </span>
                              ) : null}
                            </td>
                          </>
                        ) : (
                          <td className="px-3 py-2 text-slate-600">{row.values.staff_no ?? '—'}</td>
                        )}
                        <td className="px-3 py-2">
                          {row.warnings.length === 0 ? (
                            <Badge variant="success">Ready</Badge>
                          ) : (
                            <ul className="space-y-1 text-xs text-amber-700">
                              {row.warnings.map((warning) => (
                                <li key={warning}>{warning}</li>
                              ))}
                            </ul>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-slate-500">
                  {preview.summary.importable} of {preview.summary.total} ready
                  {needsAttention > 0 ? ` · ${needsAttention} need attention` : ''}
                </p>
                <Button onClick={handleImport} loading={submitting} disabled={blocked}>
                  Import {preview.summary.importable} {type}
                </Button>
              </div>

              {result ? (
                <div className="space-y-3">
                  <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-700">
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Import complete — {result.created} created, {result.skipped} skipped.
                  </div>
                  {result.errors?.length > 0 ? (
                    <ul className="space-y-1 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                      {result.errors.map((err, index) => (
                        <li key={index}>
                          {err.row ? `Row ${err.row}` : 'Import'}
                          {err.name ? ` (${err.name})` : ''}: {err.message}
                        </li>
                      ))}
                    </ul>
                  ) : null}
                </div>
              ) : null}
            </CardBody>
          </Card>
        ) : null}
      </div>
    </PageContainer>
  )
}
