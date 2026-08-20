import { useParams } from 'react-router-dom'
import { Mail, Phone, MapPin } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getSchool, getSchoolUsers, setSchoolStatus } from '../../services/schoolService.js'
import { SuperAdminLayout } from '../../components/layout/SuperAdminLayout.jsx'
import { StatusBadge } from '../../components/super-admin/StatusBadge.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'
import { Avatar } from '../../components/ui/Avatar.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'

export default function SchoolDetailPage() {
  const { id } = useParams()
  const { data, loading, error, reload } = useAsyncList(() => getSchool(id), [id])
  const { data: users } = useAsyncList(() => getSchoolUsers(id), [id])

  const school = data?.data
  const userCount = school?.users_count ?? users?.length ?? 0

  const changeStatus = (status) => async () => {
    await setSchoolStatus(id, status)
    reload()
  }

  const columns = [
    {
      key: 'user',
      header: 'User',
      render: (user) => (
        <span className="flex items-center gap-3">
          <Avatar name={user.name} size="sm" />
          <span className="font-medium text-slate-800">{user.name}</span>
        </span>
      ),
    },
    { key: 'email', header: 'Email' },
    { key: 'role', header: 'Role', render: (user) => <StatusBadge status={user.role} /> },
  ]

  return (
    <SuperAdminLayout>
      <PageHeader title="School details" description="Manage this tenant school" back="/super-admin/schools" />

      {loading ? (
        <div className="flex justify-center py-20">
          <Spinner className="size-8" />
        </div>
      ) : error ? (
        <Card className="p-6 text-sm text-slate-500">Could not load this school.</Card>
      ) : (
        <>
          <div className="overflow-hidden rounded-2xl bg-slate-900">
            <div className="flex flex-col gap-6 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-4">
                <span className="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-xl font-bold">
                  {String(school?.name ?? 'S').slice(0, 1).toUpperCase()}
                </span>
                <div>
                  <p className="font-mono text-xs text-slate-400">/{school?.slug}</p>
                  <h1 className="text-2xl font-bold">{school?.name}</h1>
                  <div className="mt-2 flex flex-wrap gap-3 text-xs text-slate-300">
                    {school?.email ? (
                      <span className="flex items-center gap-1"><Mail className="size-3.5" />{school.email}</span>
                    ) : null}
                    {school?.phone ? (
                      <span className="flex items-center gap-1"><Phone className="size-3.5" />{school.phone}</span>
                    ) : null}
                    {school?.address ? (
                      <span className="flex items-center gap-1"><MapPin className="size-3.5" />{school.address}</span>
                    ) : null}
                  </div>
                </div>
              </div>
              <div className="flex flex-col items-start gap-3">
                <StatusBadge status={school?.status} />
                <div className="flex flex-wrap gap-2">
                  <Button size="sm" variant="secondary" onClick={changeStatus('active')}>Activate</Button>
                  <Button size="sm" variant="secondary" onClick={changeStatus('trial')}>Trial</Button>
                  <Button size="sm" variant="dangerSoft" onClick={changeStatus('suspended')}>Suspend</Button>
                  <Button size="sm" variant="secondary" onClick={changeStatus('expired')}>Expire</Button>
                </div>
              </div>
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-3">
            <Card>
              <CardHeader title="Plan" />
              <CardBody>
                <p className="text-2xl font-bold tracking-tight text-slate-900">{school?.subscription_plan?.name ?? '—'}</p>
                <p className="mt-1 text-sm text-slate-500">Subscription: {school?.subscription_status}</p>
              </CardBody>
            </Card>
            <Card>
              <CardHeader title="Users" />
              <CardBody>
                <p className="text-2xl font-bold tracking-tight text-slate-900">{userCount}</p>
                <p className="mt-1 text-sm text-slate-500">Accounts in this school</p>
              </CardBody>
            </Card>
            <Card>
              <CardHeader title="Timezone" />
              <CardBody>
                <p className="text-2xl font-bold tracking-tight text-slate-900">{school?.timezone ?? '—'}</p>
                <p className="mt-1 text-sm text-slate-500">School locale</p>
              </CardBody>
            </Card>
          </div>

          <Card>
            <CardHeader title={`Users (${userCount})`} description="Everyone with an account in this school" />
            <CardBody>
              <DataTable
                columns={columns}
                rows={users}
                loading={false}
                emptyTitle="No users"
                emptyDescription="This school has no accounts yet."
              />
            </CardBody>
          </Card>
        </>
      )}
    </SuperAdminLayout>
  )
}
