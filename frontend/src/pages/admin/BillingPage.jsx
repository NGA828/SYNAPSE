import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getBilling, renewPlan, upgradePlan } from '../../services/billingService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { StatusBadge } from '../../components/super-admin/StatusBadge.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { formatDate } from '../../utils/formatters.js'

function UsageBar({ label, value, limit }) {
  const pct = limit ? Math.min(100, Math.round((value / limit) * 100)) : 0
  return (
    <div>
      <div className="flex justify-between text-sm">
        <span className="text-slate-500">{label}</span>
        <span className="font-medium tabular-nums text-slate-700">
          {value} / {limit ?? '∞'}
        </span>
      </div>
      <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
        <div className="h-2 rounded-full bg-brand-500 transition-all" style={{ width: `${pct}%` }} />
      </div>
    </div>
  )
}

export default function BillingPage() {
  const { data, loading, error, reload } = useAsyncList(getBilling)

  const handleUpgrade = (planId) => async () => {
    await upgradePlan(planId)
    reload()
  }

  const handleRenew = async () => {
    await renewPlan()
    reload()
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Billing & subscription"
          description="Your plan, usage and payment history."
        />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load billing.</Card>
        ) : (
          <>
            <div className="grid gap-6 lg:grid-cols-3">
              <Card className="lg:col-span-2">
                <CardHeader
                  title="Current plan"
                  action={<StatusBadge status={data?.status ?? 'none'} />}
                />
                <CardBody>
                  <div className="rounded-2xl bg-gradient-to-br from-brand-600 to-violet-600 p-6 text-white">
                    <p className="text-sm text-brand-100">Plan</p>
                    <p className="text-2xl font-bold">{data?.plan?.name ?? '—'}</p>
                    <p className="mt-1 text-sm text-brand-100">
                      {data?.plan?.price} {data?.plan?.currency}/{data?.plan?.billing_interval}
                    </p>
                    {data?.subscription?.end_date ? (
                      <p className="mt-3 text-sm text-brand-100">
                        {data?.status === 'trial' ? 'Trial ends' : 'Renews'}{' '}
                        {formatDate(data.subscription.end_date)}
                      </p>
                    ) : null}
                  </div>

                  <div className="mt-5 space-y-3.5">
                    <UsageBar label="Students" value={data?.usage?.students ?? 0} limit={data?.usage?.limits?.students} />
                    <UsageBar label="Teachers" value={data?.usage?.teachers ?? 0} limit={data?.usage?.limits?.teachers} />
                    <UsageBar label="Classes" value={data?.usage?.classes ?? 0} limit={data?.usage?.limits?.classes} />
                  </div>

                  {data?.status !== 'active' ? (
                    <Button className="mt-5" onClick={handleRenew}>
                      Renew my plan
                    </Button>
                  ) : null}
                </CardBody>
              </Card>

              <Card>
                <CardHeader title="Payment history" />
                <CardBody>
                  {data?.payments?.length === 0 ? (
                    <p className="text-sm text-slate-500">No payments yet.</p>
                  ) : (
                    <ul className="divide-y divide-slate-100">
                      {data?.payments?.map((payment) => (
                        <li key={payment.id} className="flex items-center justify-between py-2.5">
                          <div>
                            <p className="text-sm font-medium text-slate-800">
                              {payment.amount} {payment.currency}
                            </p>
                            <p className="text-xs text-slate-400">
                              {payment.provider} · {payment.reference}
                            </p>
                          </div>
                          <StatusBadge status={payment.status} />
                        </li>
                      ))}
                    </ul>
                  )}
                </CardBody>
              </Card>
            </div>

            <Card>
              <CardHeader title="Available plans" description="Upgrade at any time — limits take effect immediately" />
              <CardBody>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  {data?.available_plans?.map((plan) => (
                    <div
                      key={plan.id}
                      className={`flex flex-col rounded-2xl border p-5 ${
                        data?.plan?.id === plan.id ? 'border-brand-400 bg-brand-50/40' : 'border-slate-200'
                      }`}
                    >
                      <p className="font-semibold text-slate-900">{plan.name}</p>
                      <p className="mt-1 text-sm text-slate-500">{plan.description}</p>
                      <p className="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                        {plan.price} {plan.currency}
                        <span className="text-xs font-normal text-slate-400">/{plan.billing_interval}</span>
                      </p>
                      <p className="mt-2 text-xs text-slate-500">
                        {plan.max_students ?? 'Unlimited'} students · {plan.max_teachers ?? 'Unlimited'} teachers
                      </p>
                      <Button
                        className="mt-4 w-full"
                        variant={data?.plan?.id === plan.id ? 'secondary' : 'primary'}
                        disabled={data?.plan?.id === plan.id}
                        onClick={handleUpgrade(plan.id)}
                      >
                        {data?.plan?.id === plan.id ? 'Current plan' : 'Upgrade'}
                      </Button>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>
          </>
        )}
      </div>
    </PageContainer>
  )
}
