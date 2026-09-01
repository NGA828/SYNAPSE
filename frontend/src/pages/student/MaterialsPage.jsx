import { useState } from 'react'
import { BookOpen, FileText, Layers } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getStudentLesson, listStudentMaterials } from '../../services/lessonService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { StatCard } from '../../components/dashboard/StatCard.jsx'
import { EmptyState } from '../../components/dashboard/EmptyState.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { Modal } from '../../components/ui/Modal.jsx'
import { AttachmentList } from '../../components/homework/AttachmentList.jsx'

/**
 * Course materials for the student — published lessons from their class,
 * grouped by subject and topic, with file downloads.
 */
export default function StudentMaterialsPage() {
  const { data, loading } = useAsyncList(listStudentMaterials)
  const [openId, setOpenId] = useState(null)
  const [lesson, setLesson] = useState(null)

  const grouped = data?.data ?? {}
  const summary = data?.summary ?? null

  const openLesson = async (id) => {
    setOpenId(id)
    setLesson(await getStudentLesson(id))
  }

  const closeLesson = () => {
    setOpenId(null)
    setLesson(null)
  }

  const subjects = Object.entries(grouped)

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Course materials" description="Lesson notes and resources published by your teachers." />

        {summary ? (
          <div className="grid gap-4 sm:grid-cols-3">
            <StatCard label="Lessons available" value={summary.lessons} icon={BookOpen} tone="brand" />
            <StatCard label="Subjects" value={summary.subjects} icon={Layers} tone="violet" />
            <StatCard label="Files to download" value={summary.files} icon={FileText} tone="teal" />
          </div>
        ) : null}

        {loading ? (
          <div className="flex justify-center py-16">
            <Spinner className="size-8" />
          </div>
        ) : subjects.length === 0 ? (
          <Card>
            <CardBody>
              <EmptyState
                icon={BookOpen}
                title="No materials published yet"
                description="When a teacher publishes a lesson to your class, it will appear here."
              />
            </CardBody>
          </Card>
        ) : (
          subjects.map(([subjectName, topics]) => (
            <div key={subjectName} className="space-y-3">
              <h2 className="text-lg font-semibold text-slate-800">{subjectName}</h2>

              {Object.entries(topics).map(([topic, lessons]) => (
                <div key={topic} className="space-y-2">
                  <p className="pl-1 text-xs font-semibold uppercase tracking-wide text-slate-400">{topic}</p>

                  <div className="grid gap-3 lg:grid-cols-2">
                    {lessons.map((item) => (
                      <Card key={item.id}>
                        <CardHeader
                          title={item.title}
                          description={item.summary ?? undefined}
                          action={
                            <Button size="sm" variant="secondary" onClick={() => openLesson(item.id)}>
                              Open
                            </Button>
                          }
                        />
                        <CardBody>
                          <div className="flex flex-wrap items-center gap-2">
                            {item.minutes ? <Badge variant="info">{item.minutes} min read</Badge> : null}
                            <Badge variant="neutral">
                              {item.attachments?.length ?? 0} file{item.attachments?.length === 1 ? '' : 's'}
                            </Badge>
                            {item.sequence ? <Badge variant="neutral">Lesson {item.sequence}</Badge> : null}
                          </div>
                        </CardBody>
                      </Card>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          ))
        )}
      </div>

      <Modal
        open={Boolean(openId)}
        onClose={closeLesson}
        title={lesson?.title ?? 'Lesson'}
        description={lesson ? `${lesson.subject?.name}${lesson.topic ? ` · ${lesson.topic}` : ''}` : undefined}
      >
        {lesson ? (
          <div className="space-y-4">
            {lesson.summary ? <p className="text-sm font-medium text-slate-700">{lesson.summary}</p> : null}

            {lesson.body ? (
              <div className="max-h-72 overflow-y-auto whitespace-pre-wrap rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-700">
                {lesson.body}
              </div>
            ) : null}

            <AttachmentList attachments={lesson.attachments} label="Lesson files" />

            <div className="flex justify-end">
              <Button variant="secondary" onClick={closeLesson}>
                Close
              </Button>
            </div>
          </div>
        ) : (
          <div className="flex justify-center py-10">
            <Spinner className="size-7" />
          </div>
        )}
      </Modal>
    </PageContainer>
  )
}
