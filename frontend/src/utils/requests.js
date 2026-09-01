/**
 * Request lifecycle helpers — pure functions mirroring the backend lifecycle.
 */

export const REQUEST_STEPS = ['Submitted', 'Under Review', 'Approved', 'Ready']

export const REQUEST_STATUS_META = {
  submitted: { label: 'Submitted', variant: 'warning', step: 0 },
  under_review: { label: 'Under Review', variant: 'info', step: 1 },
  approved: { label: 'Approved', variant: 'info', step: 2 },
  ready: { label: 'Ready', variant: 'success', step: 3 },
  rejected: { label: 'Rejected', variant: 'danger', step: -1 },
}

/*
 * The list of requestable documents is served by GET /student/requests/types
 * rather than duplicated here. A second copy is how the client and the server
 * drift, and the drift is what let a student ask for a document the school
 * could not issue.
 */
