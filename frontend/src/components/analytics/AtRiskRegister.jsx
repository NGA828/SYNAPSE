import { useState } from 'react'
import { ShieldAlert } from 'lucide-react'
import { usePaginatedList } from '../../hooks/usePaginatedList.js'
import { getAtRisk, getStudent } from '../../services/analyticsService.js'
import { SignalBadge } from './SignalBadge.jsx'
import { SignalList } from './SignalList.jsx'
import { Modal } from '../ui/Modal.jsx'
import { Button } from '../ui/Button.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { DataTable } from '../ui/DataTable.jsx'
import { Pagination } from '../ui/Pagination.jsx'
import { SearchInput } from '../ui/SearchInput.jsx'
import { Select } from '../ui/Select.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { EmptyState } from '../dashboard/EmptyState.jsx'
import { formatDecimal } from '../../utils/formatters.js'

const SEVERITIES = [
  { value: '', label: 'Any severity' },
  { value: 'critical', label: 'Critical only' },
  { value: 'warning', label: 'Warnings only' },
]

const columns = [
  {
    key: 'student',
    header: 'Student',
    render: (row) => (
      <span>
        <span className="block font-medium text-slate-900">{row.student.name}</span>
        <span className="block text-xs text-slate-500">
          {row.student.matricule} · {row.student.class?.name ?? 'Unassigned'}
        </span>
      </span>
    ),
  },
  {
    key: 'severity',
    header: 'Severity',
    render: (row) => <SignalBadge severity={row.severity} />,
  },
  {
    key: 'average',
    header: 'Average',
    align: 'right',
    render: (row) => (row.average === null ? '—' : formatDecimal(row.average, 2)),
  },
  {
    key: 'attendance',
    header: 'Attendance',
    align: 'right',
    render: (row) => (row.attendance === null ? '—' : `${row.attendance}%`),
  },
  {
    key: 'signals',
    header: 'Why',
    render: (row) => (
      <span className="flex flex-wrap gap-1">
        {row.signals.map((signal) => (
          <Badge key={signal.code} variant={signal.severity === 'critical' ? 'danger' : 'warning'}>
            {signal.label}
          </Badge>
        ))}
      </span>
    ),
  },
]

/**
 * The pastoral register: who needs attention, worst first, with the reason.
 *
 * `path` selects the caller's own numbers — `/admin` for the school, `/teacher`
 * for the classes that teacher holds.
 */
export function AtRiskRegister({ path }) {
  const [severity, setSeverity] = useState('')
  const [openId, setOpenId] = useState(null)
  const [detail, setDetail] = useState(null)
  const [loadingDetail, setLoadingDetail] = useState(false)

  const { rows, meta, page, setPage, search, setSearch, loading, reload } = usePaginatedList(
    (params) => getAtRisk({ path, ...(severity ? { severity } : {}), ...params }),
    { refreshKey: severity },
  )

  const open = async (id) => {
    setOpenId(id)
    setDetail(null)
    setLoadingDetail(true)
    try {
      setDetail(await getStudent(id, path))
    } finally {
      setLoadingDetail(false)
    }
  }

  const actionColumn = {
    key: 'actions',
    header: '',
    align: 'right',
    render: (row) => (
      <Button variant="secondary" size="sm" onClick={() => open(row.student.id)}>
        Review
      </Button>
    ),
  }

  return (
    <Card>
      <CardHeader
        title="Pastoral register"
        description="Computed from grades, homework, quizzes and attendance — nothing is stored, so it is never out of date."
        action={
          <Button variant="secondary" size="sm" onClick={reload}>
            Refresh
          </Button>
        }
      />
      <CardBody className="space-y-4">
        <div className="flex flex-wrap items-center gap-3">
          <SearchInput value={search} onChange={setSearch} placeholder="Search by name…" />
          <Select
            label="Severity"
            value={severity}
            onChange={(event) => {
              setSeverity(event.target.value)
              setPage(1)
            }}
            className="w-44"
          >
            {SEVERITIES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        </div>

        {!loading && rows.length === 0 ? (
          <EmptyState
            icon={ShieldAlert}
            title="Nobody is flagged"
            description={
              search || severity
                ? 'No student matches those filters right now.'
                : 'No student currently meets any of the at-risk thresholds.'
            }
          />
        ) : (
          <>
            <DataTable
              columns={[...columns, actionColumn]}
              rows={rows}
              keyField="id"
              loading={loading}
              emptyTitle="No flagged students"
              emptyDescription="Nothing matches those filters."
            />
            <Pagination meta={meta} page={page} onPageChange={setPage} />
          </>
        )}
      </CardBody>

      {openId ? (
        <Modal
          open
          onClose={() => setOpenId(null)}
          title={detail?.data?.student?.name ?? 'Student'}
          description={
            detail?.data?.student?.class?.name
              ? `${detail.data.student.matricule} · ${detail.data.student.class.name}`
              : undefined
          }
        >
          {loadingDetail ? (
            <div className="flex justify-center py-10">
              <Spinner />
            </div>
          ) : (
            <div className="space-y-4">
              <SignalList signals={detail?.data?.signals ?? []} />
              <dl className="grid grid-cols-3 gap-3 border-t border-slate-100 pt-3 text-sm">
                <div>
                  <dt className="text-xs text-slate-500">Average</dt>
                  <dd className="font-semibold text-slate-900">
                    {detail?.data?.average ?? '—'}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-slate-500">Attendance</dt>
                  <dd className="font-semibold text-slate-900">
                    {detail?.data?.attendance === null || detail?.data?.attendance === undefined
                      ? '—'
                      : `${detail.data.attendance}%`}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-slate-500">Homework</dt>
                  <dd className="font-semibold text-slate-900">
                    {detail?.data?.homework
                      ? `${detail.data.homework.submitted}/${detail.data.homework.published}`
                      : '—'}
                  </dd>
                </div>
              </dl>
            </div>
          )}
        </Modal>
      ) : null}
    </Card>
  )
}
