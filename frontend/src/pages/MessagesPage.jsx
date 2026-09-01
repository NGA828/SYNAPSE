import { useState } from 'react'
import { MessageSquare, Plus } from 'lucide-react'
import {
  getConversation,
  listConversations,
  sendMessage,
} from '../services/messageService.js'
import { useAsyncList } from '../hooks/useAsyncList.js'
import { PageContainer } from '../components/layout/PageContainer.jsx'
import { PageHeader } from '../components/ui/PageHeader.jsx'
import { Card, CardBody, CardHeader } from '../components/ui/Card.jsx'
import { Button } from '../components/ui/Button.jsx'
import { Spinner } from '../components/ui/Spinner.jsx'
import { EmptyState } from '../components/dashboard/EmptyState.jsx'
import { ConversationList } from '../components/messaging/ConversationList.jsx'
import { MessageThread } from '../components/messaging/MessageThread.jsx'
import { NewMessageModal } from '../components/messaging/NewMessageModal.jsx'

/**
 * Direct messages, shared by every role.
 *
 * Students can write to teachers and administrators; the backend enforces that
 * and the recipient picker only ever lists people it permits.
 */
export default function MessagesPage() {
  const [activeId, setActiveId] = useState(null)
  const [pickerOpen, setPickerOpen] = useState(false)
  const [sending, setSending] = useState(false)

  const conversations = useAsyncList(listConversations)
  const thread = useAsyncList(
    () => (activeId ? getConversation(activeId) : Promise.resolve(null)),
    [activeId],
  )

  const messages = thread.data?.data ?? []
  const active = conversations.data?.data?.find(
    (row) => Number(row.id) === Number(activeId),
  )

  const handleStarted = (conversation) => {
    setPickerOpen(false)
    setActiveId(conversation.id)
    conversations.reload()
  }

  const handleSend = async (body) => {
    setSending(true)
    try {
      await sendMessage(activeId, body)
      await thread.reload()
      await conversations.reload()
    } finally {
      setSending(false)
    }
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Messages"
          description="Direct messages between you and the people at your school."
        >
          <Button onClick={() => setPickerOpen(true)}>
            <Plus className="size-4" aria-hidden="true" />
            New message
          </Button>
        </PageHeader>

        <div className="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
          <Card className="overflow-hidden">
            <CardHeader title="Conversations" />
            <ConversationList
              conversations={conversations.data?.data ?? []}
              activeId={activeId}
              onSelect={setActiveId}
              loading={conversations.loading}
            />
          </Card>

          <Card className="overflow-hidden">
            <CardHeader
              title={active?.participant?.name ?? 'Messages'}
              description={
                active
                  ? 'Opening a conversation marks the other person’s messages as read.'
                  : 'Select a conversation, or start a new one.'
              }
              noBorder={false}
            />
            <CardBody className="p-0">
              {!activeId ? (
                <div className="p-4">
                  <EmptyState
                    icon={MessageSquare}
                    title="Nothing open"
                    description="Pick a conversation on the left, or start a new message."
                  />
                </div>
              ) : thread.loading ? (
                <div className="flex justify-center py-16">
                  <Spinner />
                </div>
              ) : (
                <div className="h-[520px]">
                  <MessageThread messages={messages} busy={sending} onSend={handleSend} />
                </div>
              )}
            </CardBody>
          </Card>
        </div>
      </div>

      {pickerOpen ? (
        <NewMessageModal
          key={String(pickerOpen)}
          open={pickerOpen}
          onClose={() => setPickerOpen(false)}
          onStarted={handleStarted}
        />
      ) : null}
    </PageContainer>
  )
}
