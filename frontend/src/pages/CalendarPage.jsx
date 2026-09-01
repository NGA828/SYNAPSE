import { useState } from 'react'
import { getCalendar } from '../services/calendarService.js'
import { useAsyncList } from '../hooks/useAsyncList.js'
import { currentWeek, isoDate, shiftWeek } from '../utils/weekRange.js'
import { PageContainer } from '../components/layout/PageContainer.jsx'
import { PageHeader } from '../components/ui/PageHeader.jsx'
import { Card, CardBody, CardHeader } from '../components/ui/Card.jsx'
import { CalendarItemList } from '../components/calendar/CalendarItemList.jsx'
import { WeekNav } from '../components/calendar/WeekNav.jsx'

/**
 * One calendar built from everything else in the app.
 *
 * The items come from the timetable, exams, homework due dates, quiz deadlines
 * and school events — all read-only. Each links back to the screen that owns
 * it, so there is exactly one place to change any of them.
 */
export default function CalendarPage() {
  const [week, setWeek] = useState(() => currentWeek())

  const { data, loading, error } = useAsyncList(
    () => getCalendar(week),
    [week.from, week.to],
  )

  const items = data?.data ?? []
  const from = data?.from ?? week.from
  const to = data?.to ?? week.to

  const move = (weeks) => {
    const anchor = new Date(`${week.from}T00:00:00`)
    const next = shiftWeek(anchor, weeks)
    setWeek({
      from: isoDate(next),
      to: isoDate(new Date(next.getFullYear(), next.getMonth(), next.getDate() + 6)),
    })
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Calendar"
          description="Lessons, exams, deadlines and school events in one place."
        />

        <Card>
          <CardHeader
            title="This week"
            description="Read-only: every entry links back to the screen that owns it."
          />
          <CardBody className="space-y-4">
            <WeekNav from={from} to={to} onPrevious={() => move(-1)} onNext={() => move(1)} onToday={() => setWeek(currentWeek())} />

            {error ? (
              <p className="text-sm text-slate-500">Could not load your calendar.</p>
            ) : (
              <CalendarItemList items={items} loading={loading} />
            )}
          </CardBody>
        </Card>
      </div>
    </PageContainer>
  )
}
