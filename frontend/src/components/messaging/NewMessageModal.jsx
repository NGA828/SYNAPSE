import { useState } from 'react'
import { Users } from 'lucide-react'
import { listRecipients, startConversation } from '../../services/messageService.js'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { Modal } from '../ui/Modal.jsx'
import { Button } from '../ui/Button.jsx'
import { SearchInput } from '../ui/SearchInput.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { Avatar } from '../ui/Avatar.jsx'
import { EmptyState } from '../dashboard/EmptyState.jsx'

/**
 * Recipient picker.
 *
 * The list is whatever the backend allows this user to message, so the
 * safeguarding rule is enforced by the server and merely reflected here — a
 * student never sees other students, rather than seeing them and being refused.
 */
export function NewMessageModal({ open, onClose, onStarted }) {
  const [search, setSearch] = useState('')
  const [busyId, setBusyId] = useState(null)
  const [error, setError] = useState(null)

  const { data, loading } = useAsyncList(
    () => listRecipients({ search }),
    [search],
  )

  const pick = async (person) => {
    setBusyId(person.id)
    setError(null)
    try {
      const conversation = await startConversation(person.id)
      onStarted(conversation)
    } catch (err) {
      setError(err?.response?.data?.message ?? 'Could not open that conversation.')
    } finally {
      setBusyId(null)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="New message"
      description="Choose who you want to write to."
    >
      <div className="space-y-3">
        <SearchInput value={search} onChange={setSearch} placeholder="Search by name…" />

        {error ? <p className="text-sm text-rose-600">{error}</p> : null}

        {loading ? (
          <div className="flex justify-center py-8">
            <Spinner />
          </div>
        ) : !data?.length ? (
          <EmptyState
            icon={Users}
            title="No one to message"
            description="There is nobody matching that name at your school."
          />
        ) : (
          <ul className="max-h-72 divide-y divide-slate-100 overflow-y-auto">
            {data.map((person) => (
              <li key={person.id}>
                <button
                  type="button"
                  onClick={() => pick(person)}
                  disabled={busyId === person.id}
                  className="flex w-full items-center gap-3 px-2 py-2.5 text-left transition hover:bg-slate-50 disabled:opacity-60"
                >
                  <Avatar name={person.name} size="md" />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-slate-900">
                      {person.name}
                    </span>
                    <span className="block text-xs capitalize text-slate-500">{person.role}</span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}

        <div className="flex justify-end">
          <Button variant="secondary" size="sm" onClick={onClose}>
            Cancel
          </Button>
        </div>
      </div>
    </Modal>
  )
}
