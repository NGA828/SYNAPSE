import { Download, FileText } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { downloadDocument, getStudentDocuments } from '../../services/documentService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { EmptyState } from '../../components/dashboard/EmptyState.jsx'
import { formatDate } from '../../utils/formatters.js'

export default function DocumentsPage() {
  const { data: documents, loading, error } = useAsyncList(getStudentDocuments)

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Documents" description="Documents issued to you by the administration." />

        <Card>
          <CardHeader title="Issued documents" />
          <CardBody>
            {loading ? (
              <div className="flex justify-center py-10">
                <Spinner />
              </div>
            ) : error ? (
              <p className="text-sm text-slate-500">Could not load your documents.</p>
            ) : documents?.length === 0 ? (
              <EmptyState
                icon={FileText}
                title="No documents yet"
                description="Documents will appear here once your requests are ready."
              />
            ) : (
              <ul className="grid gap-3 sm:grid-cols-2">
                {documents?.map((document) => (
                  <li
                    key={document.id}
                    className="group flex items-center gap-4 rounded-2xl border border-slate-200 p-4 transition hover:border-slate-300 hover:shadow-sm"
                  >
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-500">
                      <FileText className="size-6" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-semibold text-slate-800">{document.title}</p>
                      <p className="mt-0.5 text-xs text-slate-500">
                        {document.request?.reference ?? 'Document'} · {formatDate(document.created_at)}
                      </p>
                      <p className="text-[10px] uppercase text-slate-400">{document.mime_type ?? 'PDF'}</p>
                    </div>
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={() => downloadDocument(document)}
                      className="shrink-0"
                    >
                      <Download className="size-4" aria-hidden="true" />
                      <span className="hidden sm:inline">Download</span>
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </CardBody>
        </Card>
      </div>
    </PageContainer>
  )
}
