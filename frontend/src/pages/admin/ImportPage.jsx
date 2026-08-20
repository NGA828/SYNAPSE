import { useRef, useState } from 'react'
import { Download, FileUp, CheckCircle2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listClasses } from '../../services/adminService.js'
import { importRows } from '../../services/importService.js'
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

function parseCsv(text) {
  const lines = text.split(/\r\n|\r|\n/).filter((line) => line.trim() !== '')
  if (lines.length < 2) return []
  const header = lines[0].split(',').map((column) => column.trim().toLowerCase())
  return lines.slice(1).map((line) => {
    const values = line.split(',')
    const row = {}
    header.forEach((column, index) => { row[column] = (values[index] ?? '').trim() })
    return row
  })
}

export default function ImportPage() {
  const { data: classes } = useAsyncList(listClasses)
  const fileRef = useRef(null)

  const [type, setType] = useState('students')
  const [rows, setRows] = useState([])
  const [fileName, setFileName] = useState('')
  const [parseError, setParseError] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [result, setResult] = useState(null)

  const handleFile = (event) => {
    const file = event.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = () => {
      try {
        setRows(parseCsv(String(reader.result)))
        setFileName(file.name)
        setParseError(null)
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

  const classIdFor = (row) => {
    if (row.class_id) return Number(row.class_id)
    if (row.class) return classes?.find((item) => item.name.toLowerCase() === String(row.class).toLowerCase())?.id
    return null
  }

  const handleImport = async () => {
    setSubmitting(true)
    setResult(null)
    const payload = rows.map((row) => ({
      name: row.name,
      email: row.email,
      password: row.password || undefined,
      matricule: type === 'students' ? row.matricule : undefined,
      staff_no: type === 'teachers' ? row.staff_no : undefined,
      class_id: type === 'students' ? classIdFor(row) : undefined,
    }))
    const summary = await importRows(type, payload)
    setResult(summary)
    setSubmitting(false)
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Bulk import" description="Import students or teachers from a CSV file." />

        <Card>
          <CardHeader title="1. Choose what to import" />
          <CardBody className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <Select name="type" label="Import type" className="sm:max-w-xs" value={type} onChange={(event) => { setType(event.target.value); setRows([]); setResult(null) }}>
              <option value="students">Students</option>
              <option value="teachers">Teachers</option>
            </Select>
            <Button variant="secondary" onClick={downloadTemplate}>
              <Download className="size-4" aria-hidden="true" />
              Download CSV template
            </Button>
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="2. Upload your file" description="Columns: name, email, and the fields shown in the template" />
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
          </CardBody>
        </Card>

        {rows.length > 0 ? (
          <Card>
            <CardHeader
              title="3. Review & import"
              description={`${rows.length} row${rows.length === 1 ? '' : 's'} detected`}
              action={<Badge variant="teal" dot>Preview</Badge>}
            />
            <CardBody>
              <div className="scrollbar-thin mb-5 max-h-64 overflow-auto rounded-xl border border-slate-200">
                <table className="w-full min-w-[30rem] text-sm">
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
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((row, index) => (
                      <tr key={index} className="border-b border-slate-50">
                        <td className="px-3 py-2 text-slate-700">{row.name}</td>
                        <td className="px-3 py-2 text-slate-600">{row.email}</td>
                        {type === 'students' ? (
                          <>
                            <td className="px-3 py-2 font-mono text-xs text-slate-500">{row.matricule}</td>
                            <td className="px-3 py-2 text-slate-600">{row.class}</td>
                          </>
                        ) : (
                          <td className="px-3 py-2 text-slate-600">{row.staff_no}</td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <Button onClick={handleImport} loading={submitting}>
                Import {type}
              </Button>

              {result ? (
                <div className="mt-4 space-y-3">
                  <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-700">
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Import complete — {result.created} created, {result.skipped} skipped.
                  </div>
                  {result.errors?.length > 0 ? (
                    <ul className="space-y-1 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                      {result.errors.map((err, index) => (
                        <li key={index}>Row {err.row} ({err.name ?? '—'}): {err.message}</li>
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
