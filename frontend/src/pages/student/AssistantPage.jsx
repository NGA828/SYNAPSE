import { BotMessageSquare } from 'lucide-react'
import { AssistantChat } from '../../components/student/AssistantChat.jsx'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Card } from '../../components/ui/Card.jsx'

/**
 * The full-page AI study assistant. The conversation is never stored — the
 * transcript lives in the chat component and dies with the tab.
 */
export default function AssistantPage() {
  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="AI Study Assistant"
          description="A patient study buddy for your schoolwork. It explains, coaches and quizzes — it never does graded work for you."
        />

        <Card variant="elevated" className="flex h-[calc(100dvh-17rem)] min-h-[26rem] flex-col overflow-hidden">
          <div className="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span className="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
              <BotMessageSquare className="size-5" aria-hidden="true" />
            </span>
            <div>
              <p className="text-sm font-semibold text-slate-900">Study assistant</p>
              <p className="text-xs text-slate-500">English or French · replies in the language you write in</p>
            </div>
          </div>

          <AssistantChat />
        </Card>
      </div>
    </PageContainer>
  )
}
