# SYNAPSE — Textual use case descriptions

Format: the course template (*UML level 2*, Mr MESSIO) — title, summary, actors, dates,
pre-conditions, trigger, nominal / alternative / exception scenarios, post-conditions,
non-functional requirements. Each description is consistent with the matching activity
diagram (`png/1x-activity-*.png`) and sequence diagram (`png/2x-sequence-*.png`), and
every step refers to code that exists today in `backend/` and `frontend/`.

| # | Use case | Activity | Sequence |
|---|----------|----------|----------|
| UC-01 | Authenticate | `10-activity-authenticate` | `20-sequence-authenticate` |
| UC-02 | Enter grades & generate report card | `11-activity-enter-grades` | `21-sequence-enter-grades` |
| UC-03 | Request an administrative document | `12-activity-request-document` | `22-sequence-request-document` |
| UC-04 | Onboard a school & pay a subscription | `13-activity-onboard-subscribe` | `23-sequence-onboard-subscribe` |
| UC-05 | Take attendance | `14-activity-take-attendance` | `24-sequence-take-attendance` |
| UC-06 | Publish, submit & grade homework | `15-activity-homework` | `25-sequence-homework` |

Common non-functional requirements (apply to every use case below):

* **Security** — every non-public endpoint requires a Sanctum bearer token (`auth:sanctum`),
  a matching role (`role:*` middleware) and is tenant-scoped (`IdentifyTenant` + `TenantScope`),
  so a school can never read another school's rows. Passwords are hashed (bcrypt); the
  login route is throttled (5 attempts / minute).
* **Availability of the school** — student and teacher routes are also behind `EnforceSubscription`
  (403 `subscription_required` once the plan has lapsed).
* **Usability** — the SPA answers in the user's locale (EN/FR strings), works on phone browsers
  and shows field-level validation errors returned by the API (HTTP 422).
* **Traceability** — sensitive actions write an `audit_logs` row (onboarding, billing, status changes).

---

## UC-01 — Authenticate

| Field | Value |
|-------|-------|
| **Title** | Authenticate (sign in with e-mail and password) |
| **Summary** | A registered user proves their identity to obtain an API token and is routed to the portal that matches their role. Every other authenticated use case *includes* this one. |
| **Primary actor** | Student, Teacher, School Administrator, Super Administrator (generalised as *Authenticated User*) |
| **Secondary actors** | — (mail server only for the "forgot password" alternative) |
| **Creation date / version** | 2026-09-20 — v1.0 |
| **Manager (author)** | SYNAPSE project team |
| **Pre-conditions** | The user account exists (`users` row) and belongs to a school that is not suspended; the SPA is loaded in a browser. |
| **Trigger** | The user opens `/login` (or the school page `/school/{slug}`) and submits the form. |

**Nominal scenario**

1. The user opens the login page and enters e-mail and password.
2. The SPA (`LoginForm` → `AuthContext.login()`) sends `POST /api/login {email, password}`.
3. The API applies the `throttle:login` limiter and validates the payload (`LoginRequest`: valid e-mail, password ≥ 8 characters).
4. `AuthService::login()` looks the user up by e-mail without the tenant scope and verifies the password with `Hash::check()`.
5. The service checks that the user's school is not suspended/expired.
6. The service stores `last_login_at`, creates a Sanctum personal access token and returns `{token, user, must_change_password}`.
7. The SPA stores the token in `localStorage` (`synapse_token`), fills the Auth context and redirects by role: `/super-admin`, `/admin`, `/teacher` or `/student`.
8. The user lands on their dashboard. Every later request carries `Authorization: Bearer <token>` and passes the middleware chain `auth:sanctum → tenant (IdentifyTenant sets TenantContext = user.school) → password.rotated → role → subscription`.

**Alternative scenarios**

* **A1 — First login / temporary password.** At step 6 `must_change_password` is `true`: the SPA redirects to `/change-password`; until the password is rotated every protected API call answers `403 password_change_required`.
* **A2 — Forgotten password.** Before step 1 the user clicks "Forgot password": `POST /api/forgot-password` e-mails a reset link (mail server), `POST /api/reset-password` sets the new password; the flow resumes at step 1.
* **A3 — School login page.** The user starts from `/school/{slug}`: the SPA loads the public branding (`GET /api/school/{slug}`) and shows the same form; the tenant itself is always derived from the authenticated user by `IdentifyTenant`, never from the client.

**Exception scenarios**

* **E1 — Rate limit exceeded** (step 3): `429 Too Many Requests`; the SPA asks the user to retry later.
* **E2 — Invalid payload** (step 3): `422` with field errors; the user corrects the form.
* **E3 — Unknown e-mail or wrong password** (step 4): `422 "These credentials are incorrect"`; the user may retry.
* **E4 — School suspended or expired** (step 5): `403 "School account suspended"`; the flow ends.

**Post-conditions**

* *Success*: a `personal_access_tokens` row exists, `users.last_login_at` is updated, the SPA is in an authenticated state.
* *Failure*: no token is issued and nothing is persisted (except the throttle counter).

**Specific non-functional requirements** — response < 500 ms; token stored only in the browser; passwords never logged; the same generic error message for unknown e-mail and wrong password (no user enumeration).

---

## UC-02 — Enter grades & generate report card

| Field | Value |
|-------|-------|
| **Title** | Enter grades in the gradebook and generate the report card (PDF) |
| **Summary** | A teacher records test/exam/component scores for the students of a class in a subject they teach; the administration then produces the report card (single student, or the whole class asynchronously). |
| **Primary actors** | Teacher (grade entry), School Administrator (report card generation) |
| **Secondary actors** | Queue worker (bulk generation), mail server / SMS gateway (report-card-ready notifications) |
| **Creation date / version** | 2026-09-20 — v1.0 |
| **Manager (author)** | SYNAPSE project team |
| **Pre-conditions** | UC-01 done; a **current academic year** exists; the teacher has a `TeachingAssignment` for (subject, class, year); students are enrolled in the class; the plan includes the `report_cards` feature for the PDF part. |
| **Trigger** | The teacher opens *Gradebook* for one of their class/subject assignments. |

**Nominal scenario**

1. The SPA calls `GET /api/teacher/classes/{class}/subjects/{subject}/grades`.
2. The `teaching.assignment` middleware (`CheckTeachingAssignment`) verifies the current year and the assignment.
3. `GradeService::gradebook()` resolves the effective grade components (subject-specific, otherwise the school defaults) and returns the enrolled students with their existing grades/scores.
4. The SPA renders an editable grid; the teacher types the scores for each student.
5. The teacher clicks *Save*; the SPA sends `POST …/grades {rows[{student_id, test1, test2, exam, scores[{component_id, score}]}]}`.
6. The API validates the rows (numeric, 0–20, ids exist).
7. `GradeService::save()` re-checks the assignment, loads the enrolled student ids and, for each enrolled row, upserts `grades` (keyed by student, subject, class, year, semester) and `grade_scores` per component; non-enrolled rows are skipped.
8. The refreshed gradebook is returned; the SPA shows "Grades saved" with averages.
9. Later, a school administrator opens a student and requests the report card: `GET /api/admin/students/{student}/report-card`.
10. `GradeService::reportCard()` computes subject averages, weighted totals and the class rank; `DocumentService::generateReportCard()` renders the Blade template with dompdf, stores the file and creates a `documents` row (type `report_card`, unique `verification_code`).
11. The PDF is streamed to the administrator, who prints or shares it.

**Alternative scenarios**

* **A1 — Whole class.** At step 9 the administrator chooses *Generate report cards for the class*: `POST /api/admin/classes/{class}/report-cards` answers `202 Accepted` and dispatches `GenerateClassReportCardsJob` on the `documents` queue; the worker generates every report card in chunks of 25 and sends `ReportCardReadyNotification` (bell / e-mail / SMS).
* **A2 — Student download.** The student obtains their own copy from `GET /api/student/report-card/pdf` (plan feature `report_cards`).
* **A3 — Report-card comments.** Before step 10 teachers may write (or AI-draft) `report_card_comments`, which are printed on the document.

**Exception scenarios**

* **E1 — No current academic year** (step 2): `409`; the gradebook is not opened.
* **E2 — Teacher not assigned** (step 2 or 7): `403`.
* **E3 — Invalid scores** (step 6): `422` with row errors; the teacher fixes the cells.
* **E4 — PDF renderer failure** (step 10): `500`, no `documents` row is written; the administrator retries.

**Post-conditions**

* *Success*: `grades` / `grade_scores` rows reflect the grid; one `documents` row per generated report card, verifiable at `/verify/{code}`.
* *Failure*: no partial grid is committed for invalid rows; no document row without a stored file.

**Specific non-functional requirements** — saving 60 rows < 2 s; bulk generation must not block the HTTP request (queue); ranks computed on the full class, not the page; PDF carries school branding and a verification code.

---

## UC-03 — Request an administrative document

| Field | Value |
|-------|-------|
| **Title** | Request an administrative document (certificate, transcript, …) |
| **Summary** | A student files a request; the administration triages it, issues the PDF (automatically when the type allows it) or rejects it; the student is notified and downloads the document. |
| **Primary actors** | Student (requester), School Administrator (processor) |
| **Secondary actors** | Mail server, SMS gateway (notifications) |
| **Creation date / version** | 2026-09-20 — v1.0 |
| **Manager (author)** | SYNAPSE project team |
| **Pre-conditions** | UC-01 done; the plan includes the `document_management` feature; the student has a `students` profile. |
| **Trigger** | The student clicks *New request* in the *Requests* page. |

**Nominal scenario**

1. The student chooses a document type (`Certificate of Enrollment`, `Transcript Request`, `Certificate of Good Conduct`, `Transfer Certificate`, `School Leaving Certificate`, `Recommendation Letter`, `Other`) and gives a reason.
2. The SPA sends `POST /api/student/requests {type, reason}`.
3. `RequestService::create()` generates a unique reference `REQ-####`, inserts the request with status `submitted` and notifies the school administrators (`RequestSubmittedNotification`).
4. The SPA shows the request with status *Submitted*.
5. An administrator opens *Document requests*; `GET /api/admin/requests` returns the list with `DocumentTypeService::triage()` badges (auto-generatable / needs human review).
6. The administrator clicks *Generate document*: `POST /api/admin/requests/{id}/generate-document`.
7. `DocumentService::generateForRequest()` renders the PDF for the type (dompdf, school branding, verification code), stores the file and creates the `documents` row linked to the request and the student.
8. `RequestService` sets the request to `ready` with `resolved_at = now` and notifies the student (`DocumentReadyNotification`: bell, e-mail, SMS when enabled).
9. The student opens *My documents* and downloads the PDF (`GET /api/student/documents/{id}/download`). Anyone can later check it on the public page `/verify/{code}`.

**Alternative scenarios**

* **A1 — Manual review.** At step 6 the administrator sets `under_review` or `approved` (`PATCH /api/admin/requests/{id}`) before generating; each transition notifies the student (`RequestStatusChangedNotification`).
* **A2 — Rejection.** The administrator sets `rejected` with an `admin_note`; `resolved_at` is set and the student reads the note (end of flow).
* **A3 — Human-only type.** For *Recommendation Letter* / *Other*, the triage badge says the request needs a human: the administrator prepares the document outside the system and updates the status manually.

**Exception scenarios**

* **E1 — Invalid type / reason** (step 2): `422`.
* **E2 — Unsupported type sent to the generator** (step 7): `422` carrying the triage reason — there is no silent fallback to a generic document.
* **E3 — Feature not in plan** (step 2/6): `403 feature_required`.
* **E4 — Notification channel down** (steps 3/8): the bell notification is stored; e-mail/SMS failures are logged and do not roll back the request.

**Post-conditions**

* *Success*: `requests.status ∈ {ready, rejected}` with `resolved_at`; for `ready`, one `documents` row and a stored PDF.
* *Failure*: the request stays in an open status (`submitted`, `under_review`, `approved`) and can be processed again.

**Specific non-functional requirements** — reference numbers unique per school; generated PDFs immutable once issued; verification page public and rate-limited; every status change traceable in the notification history.

---

## UC-04 — Onboard a school & pay a subscription

| Field | Value |
|-------|-------|
| **Title** | Register a school (free trial) and pay for a subscription plan |
| **Summary** | A visitor creates a school tenant with its first administrator account and a 14-day trial; later the administrator upgrades or renews by paying through a mobile-money / card gateway, which activates the subscription and e-mails a receipt. |
| **Primary actors** | Guest (becomes School Administrator) |
| **Secondary actors** | Payment gateway (MTN MoMo, Orange Money, card — mock in development), mail server (receipt) |
| **Creation date / version** | 2026-09-20 — v1.0 |
| **Manager (author)** | SYNAPSE project team |
| **Pre-conditions** | At least one active `subscription_plans` row; the chosen slug and administrator e-mail are not used yet. For the payment part: UC-01 done as administrator. |
| **Trigger** | The visitor clicks *Start free trial* on the landing page. |

**Nominal scenario**

1. The SPA loads the plans: `GET /api/onboarding/plans` (active plans ordered by price).
2. The visitor picks a plan and fills the school (name, slug, contact, logo) and administrator (name, e-mail, password) forms.
3. The SPA sends `POST /api/onboarding/register {school, admin, plan_id}`; `RegisterSchoolRequest` validates (unique slug, unique e-mail, password ≥ 8, plan exists).
4. `OnboardingService::register()` checks that the plan is active and, in one DB transaction, creates the `schools` row (status `trial`, timezone `Africa/Douala`), the admin `users` row, the trial `subscriptions` row (`end_date = today + 14 days`) and mirrors plan/status/expiry onto the school; an `audit_logs` row `school.onboarded` is written.
5. The API answers `201` and the SPA redirects to the login page; the administrator signs in (UC-01) and uses the platform during the trial.
6. The administrator opens *Billing*, chooses a plan and a provider, and confirms: `POST /api/admin/billing/upgrade {plan_id, provider, method}`.
7. `BillingService::upgrade()` calls `PaymentService::charge()`, which selects the gateway by provider and charges the school; a `payments` row is stored with the gateway reference and `paid_at`.
8. `SubscriptionService::changePlan()` creates a new `subscriptions` row with status `active` (start = previous end date if still in the future, otherwise today; end = start + 1 month) and updates the school snapshot; the payment is linked to the subscription and `subscription.upgraded` is audited.
9. `BillingService::sendReceipt()` e-mails a PDF receipt (`PaymentReceiptNotification`) to every administrator of the school and the billing dashboard (plan, usage, payments) is returned.

**Alternative scenarios**

* **A1 — Renewal.** At step 6 the administrator clicks *Renew*: same flow with the current plan (`409` if the school has no plan).
* **A2 — Trial lapses first.** If no payment happens within 14 days the daily `synapse:sweep-subscriptions` command marks the subscription `expired` (reminders 7/3/1 days before); the administrator can still reach *Billing* to pay (steps 6–9).
* **A3 — Platform-side changes.** A super administrator may suspend / cancel / re-activate the subscription (`setStatus`).

**Exception scenarios**

* **E1 — Validation error** (step 3): `422` field errors (slug or e-mail already taken, weak password, unknown plan).
* **E2 — Plan not active** (step 4): `422 "Plan not available"`.
* **E3 — Payment refused** (step 7): a `payments` row with status `failed` is kept for audit, `422 "Payment failed"`; the current plan is unchanged.
* **E4 — Gateway timeout** (step 7): treated as failed; no subscription change; the administrator retries.

**Post-conditions**

* *Success (onboarding)*: school + administrator + trial subscription exist atomically. *Success (payment)*: `payments.status = succeeded`, a new active `subscriptions` row, `schools.status = active`, receipt sent.
* *Failure*: the transaction is rolled back (onboarding) / the plan stays as it was (payment).

**Specific non-functional requirements** — onboarding is atomic (single transaction); payment references idempotent; sandbox flag visible on receipts in development; amounts in XAF; PCI data never touches the API (gateway-hosted).

---

## UC-05 — Take attendance

| Field | Value |
|-------|-------|
| **Title** | Take attendance for a class on a given day |
| **Summary** | A teacher marks each student of one of their classes as present, absent, late or excused for a date; records are upserted so the sheet can be corrected later. |
| **Primary actor** | Teacher (a School Administrator can do the same from the admin console) |
| **Secondary actors** | — |
| **Creation date / version** | 2026-09-20 — v1.0 |
| **Manager (author)** | SYNAPSE project team |
| **Pre-conditions** | UC-01 done; a current academic year exists; the teacher has a `TeachingAssignment` in the class for that year; students are enrolled. |
| **Trigger** | The teacher opens *Attendance* for a class and picks a date (default: today). |

**Nominal scenario**

1. The SPA calls `GET /api/teacher/classes/{class}/attendance?date=YYYY-MM-DD`.
2. The `class.access` middleware (`EnsureClassAccess`) checks the role, the teacher profile, the current year and the assignment.
3. `AttendanceService::roster()` returns the enrolled students with the existing records for that date (default status `present`).
4. The SPA renders the roster with status buttons; the teacher sets a status (and optional remark) for each student.
5. The teacher clicks *Save attendance*; the SPA sends `POST …/attendance {date, records[{student_id, status, remark?}]}`.
6. `StoreAttendanceRequest` validates the date (`Y-m-d`), the status enum and the remark length.
7. `AttendanceService::save()` re-asserts the teacher may manage the class, loads the enrolled ids and upserts one `attendances` row per valid record, keyed by (school, class, student, year, date) and stamped with `teacher_id`; records for non-enrolled students are skipped.
8. The refreshed roster is returned; the SPA shows "Attendance saved" and the counts per status.

**Alternative scenarios**

* **A1 — Correction.** Re-opening the same date shows the saved statuses; saving again updates the same rows (upsert).
* **A2 — Administrator entry.** The administrator uses `/api/admin/classes/{class}/attendance` with the same payload, without the assignment check.
* **A3 — Student view.** Students consult their own history in *My attendance*; analytics use the rows for at-risk signals.

**Exception scenarios**

* **E1 — No current academic year** (step 2): `409`.
* **E2 — Teacher not assigned to the class** (step 2 or 7): `403`.
* **E3 — Invalid payload** (step 6): `422`.

**Post-conditions**

* *Success*: exactly one `attendances` row per (student, date) for the class, reflecting the last save.
* *Failure*: previous rows untouched.

**Specific non-functional requirements** — usable on a phone in class (large buttons, default *present*); save of a 60-student roster < 1 s; idempotent saves.

---

## UC-06 — Publish, submit & grade homework

| Field | Value |
|-------|-------|
| **Title** | Publish a homework assignment, submit it and grade the submissions |
| **Summary** | A teacher creates and publishes an assignment (with optional files) for a class; enrolled students submit text and/or files before the deadline; the teacher grades each submission, which is returned to the student with feedback. |
| **Primary actors** | Teacher, Student |
| **Secondary actors** | Mail server / SMS gateway (publication and return notifications), file storage |
| **Creation date / version** | 2026-09-20 — v1.0 |
| **Manager (author)** | SYNAPSE project team |
| **Pre-conditions** | UC-01 done; the teacher has a `TeachingAssignment` for (subject, class, current year); the student is enrolled in the class; the school subscription is active. |
| **Trigger** | The teacher clicks *New assignment* in the *Homework* page. |

**Nominal scenario**

1. The teacher enters title, instructions, max score (default 20), due date and attaches files; the SPA sends `POST /api/teacher/homework` (multipart).
2. `HomeworkService::create()` checks the assignment, stores the row as a draft (`is_published = false`) and saves the files through `AttachmentService` (visibility `class`).
3. The teacher clicks *Publish*: `POST /api/teacher/homework/{id}/publish`; the service verifies ownership, sets `is_published = true` and `published_at`, and sends `HomeworkPublishedNotification` to every enrolled student.
4. A student opens the notification, reads the instructions and attachments, writes an answer and/or attaches files, then submits: `POST /api/student/homework/{id}/submit`.
5. `HomeworkService::submit()` checks that the assignment is published and not past due, that the submission is not empty and that the student is enrolled; inside a transaction it creates the `homework_submissions` row (`attempts = 1`, `submitted_at = now`) or updates the existing ungraded one (`attempts + 1`); files are then stored with visibility `private`.
6. The SPA shows the *Submitted* badge.
7. The teacher opens *Submissions*: `GET /api/teacher/homework/{id}/submissions` returns the roster with each student's status (`not_submitted`, `submitted`, `late`, `graded`).
8. The teacher opens a submission, enters a score and feedback and clicks *Return*: `POST …/submissions/{sid}/grade {score, feedback}`.
9. `HomeworkService::grade()` verifies ownership and `0 ≤ score ≤ max_score`, stores score, feedback, `graded_by`, `graded_at`, `returned_at` and sends `HomeworkReturnedNotification`.
10. The student sees the score and feedback on the homework page.

**Alternative scenarios**

* **A1 — Resubmission.** Between steps 6 and 8, while the assignment is open and the submission is not graded, the student may submit again (content replaced, `attempts` incremented).
* **A2 — Unpublish.** The teacher may withdraw the assignment (`unpublish`): it disappears for students but existing submissions are kept.
* **A3 — Attachments only.** A submission may contain only files (no text) or only text.

**Exception scenarios**

* **E1 — Not the owner** (steps 3, 8/9): `403`.
* **E2 — Assignment not published** (step 5): `403`.
* **E3 — Deadline passed** (step 5): `422 "deadline passed"` — late work is refused, `is_late` is never set by the current code.
* **E4 — Empty submission** (step 5): `422`.
* **E5 — Already graded** (step 5): `422 "already graded"`, transaction rolled back.
* **E6 — Score out of range** (step 9): `422`.

**Post-conditions**

* *Success*: one `homework_submissions` row per student with `score`/`feedback` once graded; attachments stored on disk and referenced in `attachments`.
* *Failure*: no partial submission (transaction), files are only stored after the row is committed.

**Specific non-functional requirements** — uploads limited by `AttachmentService` (type / size whitelist); private files downloadable only by the owning student and the assignment's teacher; publication notification delivered within a minute (bell immediately, mail/SMS by queue).
