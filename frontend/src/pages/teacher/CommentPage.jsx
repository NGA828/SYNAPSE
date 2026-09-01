import { useParams } from 'react-router-dom'
import { ReportCardCommentCard } from '../../components/comments/ReportCardCommentCard.jsx'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'

/**
 * Approve one pupil's report-card comment.
 *
 * Reached from the class roster. The card explains what was generated and why,
 * so approving it is a decision rather than a formality.
 */
export default function CommentPage() {
  const { studentId } = useParams()

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Report-card comment"
          description="Generated from the marks on the card. Nothing is printed until you lock it."
          back="/teacher"
        />

        <ReportCardCommentCard studentId={studentId} />
      </div>
    </PageContainer>
  )
}
