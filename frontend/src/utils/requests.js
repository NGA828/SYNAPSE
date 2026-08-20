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

export const REQUEST_TYPES = [
  'Certificate of Enrollment',
  'Transcript Request',
  'Recommendation Letter',
  'Other',
]
