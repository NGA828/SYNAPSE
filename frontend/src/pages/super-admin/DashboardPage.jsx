import { Building2, CreditCard, GraduationCap, Users } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getSuperAdminDashboard } from '../../services/superAdminService.js'
import { listSchools } from '../../services/schoolService.js'
import { SuperAdminLayout } from '../../components/layout/SuperAdminLayout.jsx'
import { StatCard } from '../../components/dashboard/StatCard.jsx'
import { BarChart } from '../../components/super-admin/BarChart.jsx'
import { BarList } from '../../components/ui/BarList.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'

export default function DashboardPage() {
  const { data, loading, error } = useAsyncList(getSuperAdminDashboard)
  const { data: schools } = useAsyncList(listSchools)

  const stats = data?.stats

  const planDistribution = (schools ?? []).reduce((acc, school) => {
    const plan = school.subscription_plan?.name ?? 'None'
    acc[plan] = (acc[plan] ?? 0) + 1
    return acc
  }, {})
  const chartData = Object.entries(planDistribution).map(([label, value]) => ({ label, value }))

  const statusBars = [
    { label: 'Active', value: stats?.schools?.active ?? 0, tone: 'emerald', dot: 'bg-emerald-500' },
    { label: 'Trial', value: stats?.schools?.trial ?? 0, tone: 'brand', dot: 'bg-brand-500' },
    { label: 'Suspended', value: stats?.schools?.suspended ?? 0, tone: 'rose', dot: 'bg-rose-500' },
    { label: 'Expired', value: stats?.schools?.expired ?? 0, tone: 'amber', dot: 'bg-amber-500' },
  ]

  return (
    <SuperAdminLayout>
      <PageHeader title="Platform overview" description="All schools on the SYNAPSE SaaS platform." />

      {loading ? (
        <div className="flex justify-center py-20">
          <Spinner className="size-8" />
        </div>
      ) : error ? (
        <Card className="p-6 text-sm text-slate-500">Could not load the platform dashboard.</Card>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard icon={Building2} label="Total schools" value={stats?.schools?.total ?? 0} tone="brand" />
            <StatCard icon={Users} label="Total users" value={stats?.users?.total ?? 0} tone="violet" />
            <StatCard icon={GraduationCap} label="Students" value={stats?.users?.students ?? 0} tone="teal" />
            <StatCard icon={CreditCard} label="Active subscriptions" value={stats?.subscriptions?.active ?? 0} tone="emerald" />
          </div>

          <div className="grid gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
              <CardHeader title="Schools by plan" description="Distribution across subscription plans" />
              <CardBody>
                <BarChart data={chartData} />
              </CardBody>
            </Card>

            <Card>
              <CardHeader title="School statuses" />
              <CardBody>
                <BarList items={statusBars} />
                <div className="mt-5 border-t border-slate-100 pt-4">
                  <p className="text-xs text-slate-400">Estimated monthly recurring revenue</p>
                  <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                    {stats?.revenue?.mrr ?? 0}
                    <span className="ml-1 text-sm font-normal text-slate-400">{stats?.revenue?.currency ?? 'XAF'}</span>
                  </p>
                </div>
              </CardBody>
            </Card>
          </div>
        </>
      )}
    </SuperAdminLayout>
  )
}
