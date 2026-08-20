import { useAsyncList } from '../hooks/useAsyncList.js'
import { getAnnouncements } from '../services/announcementService.js'
import { PageContainer } from '../components/layout/PageContainer.jsx'
import { PageHeader } from '../components/ui/PageHeader.jsx'
import { AnnouncementList } from '../components/dashboard/AnnouncementList.jsx'
import { Card, CardBody, CardHeader } from '../components/ui/Card.jsx'
import { Spinner } from '../components/ui/Spinner.jsx'

export default function AnnouncementsPage() {
  const { data: announcements, loading, error } = useAsyncList(getAnnouncements)

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Announcements" description="Updates published by your school." />

        <Card>
          <CardHeader title="School announcements" />
          <CardBody>
            {loading ? (
              <div className="flex justify-center py-10">
                <Spinner />
              </div>
            ) : error ? (
              <p className="text-sm text-slate-500">Could not load announcements.</p>
            ) : (
              <AnnouncementList announcements={announcements} />
            )}
          </CardBody>
        </Card>
      </div>
    </PageContainer>
  )
}
