# SYNAPSE — Multi-Tenant School Management SaaS

> **Phase 1 — Foundation & Strict Modular Architecture** · **Phase 2 — Database Schema & Teaching Assignment Model** · **Phase 3 — Grades, Grade Entry, Report Cards & Timetables** · **Phase 4 — Requests, Documents, Announcements & Notifications** · **SaaS Upgrade — Multi-Tenancy, Super Admin, Onboarding & Subscriptions**

SYNAPSE is a production-grade, **multi-tenant** SaaS platform. Independent schools share
one application and one MySQL database while their data stays **completely isolated** —
students, teachers, classes, subjects, grades, requests, documents, announcements and
notifications are all scoped to a school (tenant). Authorization is enforced **in Laravel**,
never in the React UI.

---

## Tenancy model

```
                SYNAPSE PLATFORM
                      │
                  SUPER ADMIN
                      │
        ┌─────────────┴─────────────┐
     SCHOOL A                    SCHOOL B
        │                            │
     ADMIN A                     ADMIN B
        │                            │
   ┌────┴─────┐                ┌────┴─────┐
Teachers   Students         Teachers   Students
```

- **Shared app, shared DB, row-level isolation** via a `school_id` foreign key on every
  tenant-owned table plus a global `TenantScope` that auto-scopes every Eloquent query.
- **`TenantContext`** holds the resolved school; `IdentifyTenant` middleware derives it
  from the **authenticated user** — never from a frontend-supplied ID.
- **Global vs tenant vs user-owned**: `subscription_plans` and `schools` are platform
  global; academic tables are tenant-owned; `grades`/`documents`/`notifications` are also
  user-owned (a student only ever sees their own rows).
- **Roles**: `super_admin` (platform) → `admin` (school) → `teacher` → `student`.
  Teacher access remains strictly governed by `TeachingAssignment` records, now
  additionally scoped to the school.

---

## Tech Stack

| Layer     | Technology                                             |
| --------- | ------------------------------------------------------ |
| Frontend  | React 19 (Vite) · Tailwind CSS v4 · Lucide Icons       |
| Backend   | Laravel 13 (PHP 8.3+) · Laravel Sanctum · MySQL        |
| Auth      | Sanctum personal access tokens (Bearer)                |
| Payments  | Provider abstraction (Mock / MTN MoMo / Orange Money / Card) |

---

## Repository Structure

```
SYNAPSE/
├── frontend/src/
│   ├── assets/               # Static assets
│   ├── components/
│   │   ├── ui/               # Atomic primitives (Button, Input, Card, Badge, Spinner)
│   │   ├── layout/           # Sidebar, TopBar, PageContainer, SuperAdminLayout
│   │   ├── forms/            # LoginForm, ErrorDisplay
│   │   ├── dashboard/        # StatCard, tables, timetable, lists
│   │   ├── tenant/           # SubscriptionBanner (trial/expired/limit states)
│   │   ├── super-admin/      # BarChart, StatusBadge
│   │   ├── landing/          # Marketing page sections
│   │   └── brand/            # Logo / LogoMark
│   ├── pages/                # Thin route handlers only
│   │   ├── auth/             # LoginPage, RegisterPage
│   │   ├── school/           # Branded per-school login
│   │   ├── onboarding/       # Multi-step school registration
│   │   ├── student|teacher|admin/   # admin/ includes billing + settings
│   │   ├── super-admin/      # Platform dashboard, schools, plans, subscriptions, payments
│   │   └── errors/           # ForbiddenPage, NotFoundPage
│   ├── hooks/                # useAuth, useTenant, useSubscription, useFeature, useAsyncList, …
│   ├── services/             # apiClient, auth, tenant, school, subscription, billing, onboarding, mockAdapter
│   ├── context/              # AuthContext, TenantContext, SubscriptionContext
│   ├── routes/               # AppRoutes, roleGuards
│   └── utils/                # cn, validators, formatters, grades, timetable, requests
│
└── backend/app/
    ├── Http/Controllers/Api/ # Auth, Tenant, Onboarding, Student, Teacher, Admin,
    │                         # SuperAdmin (Dashboard, School, Plan, Subscription, Payment)
    ├── Http/Middleware/      # EnsureRoleIs, IdentifyTenant, EnforceSubscription,
    │                         # EnsureFeature, CheckTeachingAssignment
    ├── Http/Requests/        # Validation for every write endpoint
    ├── Models/               # School, SubscriptionPlan, Subscription, Payment,
    │                         # SchoolSetting, AuditLog + all academic models
    ├── Models/Concerns/      # BelongsToSchool trait
    ├── Models/Scopes/        # TenantScope (global scope)
    ├── Services/             # TenantContext, Subscription, Billing, Payment, School,
    │                         # Onboarding, Audit + academic services
    ├── Services/Payments/    # PaymentGateway interface + Mock/MTN/Orange/Card
    └── Policies/             # School, Student, Teacher, Grade, Request, Document,
                              # Announcement, TeachingAssignment
```

---

## Design System

Inspired by modern education-SaaS dashboards on Dribbble (light theme, indigo→violet
brand, soft cards, sidebar navigation, stat cards, tables, timetable grid):

- **Brand** — indigo (`brand-*`) with a violet/teal gradient accent
- **Type** — Inter Variable, bundled locally via `@fontsource-variable/inter`
- **Surfaces** — white cards, `rounded-2xl`, 1px `slate-200` borders, soft shadows
- **Status** — emerald (success), amber (warning), rose (danger), brand (info)

The public landing page (`/`) demonstrates the full visual language: navbar, hero with a
live dashboard mockup, features, "how it works", role cards, CTA and footer.

---

## Quick Start

### Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env          # set DB_* to your MySQL credentials
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve              # http://127.0.0.1:8000
```

> `.env.example` defaults to MySQL. For a zero-config local run, switch to SQLite:
> `DB_CONNECTION=sqlite` and create `database/database.sqlite`.

### Frontend (Vite)

```bash
cd frontend
npm install
cp .env.example .env           # set VITE_API_URL and VITE_USE_MOCK=false
npm run dev                    # http://localhost:5173
```

---

## Demo Accounts

Seeded by `DatabaseSeeder` (passwords hashed — never stored in plain text). All use
`password123`:

| Role                | Email                            | School              |
| ------------------- | -------------------------------- | ------------------- |
| Super Admin         | `superadmin@synapse.test`        | Platform            |
| Administrator       | `admin@synapse.test`             | AICS Cameroon       |
| Teacher             | `teacher@synapse.test`           | AICS Cameroon       |
| Student             | `student@synapse.test`           | AICS Cameroon       |
| Administrator       | `admin.saintalbert@synapse.test` | Saint Albert (trial)|
| Teacher             | `teacher.saintalbert@synapse.test` | Saint Albert (trial)|
| Student             | `student.saintalbert@synapse.test` | Saint Albert (trial)|
| Administrator       | `admin.demo@synapse.test`        | Demo Intl (expired) |

---

## Mock Mode

When the Laravel backend is not running, set `VITE_USE_MOCK=true` in `frontend/.env`.
`services/mockAdapter.js` installs an in-browser axios adapter that reproduces the exact
JSON contract of the Laravel controllers — including **tenant isolation, subscription
enforcement and plan limits** — so the whole multi-tenant platform can be demoed
end-to-end. Set it to `false` to talk to the real API — no other code changes are required.

---

## SaaS capabilities

- **Super Admin** — platform dashboard (schools, users, subscriptions, MRR), school CRUD
  with activate/suspend/trial/expire, plan management, subscription & payment views.
- **Onboarding** — multi-step public registration: school info → admin account → plan →
  free trial (configurable `SYNAPSE_TRIAL_DAYS`, default 14).
- **Subscriptions & billing** — `subscription_plans` (configurable price, limits, features),
  per-school `subscriptions` history, `payments`, and a school-admin billing dashboard with
  upgrade/renew (mock payment in dev) and usage-vs-limit progress bars.
- **Plan limits** — enforced in Laravel (`SubscriptionService::assertCanCreate`); a 422 is
  returned with an upgrade message when a school hits a student/teacher/class limit.
- **Subscription enforcement** — `EnforceSubscription` middleware blocks academic
  operations for expired/suspended schools while leaving billing reachable so they can
  renew. The frontend shows a clear banner for trial/expired/limit states.
- **Feature flags** — plan features (e.g. `report_cards`, `document_management`) gate both
  the UI (`useFeature`) and the API (`feature:` middleware).
- **White-label branding** — per-school name/logo/primary color via `/tenant`; branded
  sign-in at `/school/{slug}`; school settings (grading scale, semesters, branding).
- **Payments (Cameroon-first)** — `PaymentGateway` abstraction with `Mock` (dev-only,
  always `sandbox`), `MTN Mobile Money`, `Orange Money` and `Card` adapters that refuse to
  charge until real credentials are configured (no fake production payments).
- **Audit logs** — tenant-scoped `audit_logs` recorded on school/onboarding/subscription
  events.

---

## Authentication Flow

1. `LoginForm` → `useAuth().login()` → `authService.login()` → `POST /login`
2. Laravel validates via `LoginRequest`, `AuthService` issues a Sanctum token
3. Token is stored in `localStorage` and injected by `apiClient` on every request
4. `useRoleRedirect` navigates to the role's dashboard (`/student`, `/teacher`, `/admin`)
5. `ProtectedRoute` blocks unauthenticated access; `RoleGuard` blocks wrong-role access
6. Logout revokes the token server-side and clears local state

**Backend enforcement:** `/student/dashboard` is guarded by `auth:sanctum` **and**
`role:student`. Teacher access is governed by `CheckTeachingAssignment`, which verifies a
`TeachingAssignment` record (teacher → subject → class → academic year) before any
class-student resource is served — the frontend never grants access on its own.

## Data Model (Phases 2–3)

```
academic_years        (name, start_date, end_date, is_current)
classes               (name)                    → SchoolClass model
subjects              (name, code)
students / teachers   (profiles on top of users)
enrollments           (student, class, academic_year)      ← preserves history
teaching_assignments  (teacher, subject, class, academic_year) ← source of truth
grades                (student, subject, class, year, teacher, test1, test2, exam)
timetable_entries     (class, year, subject, day 1–5, start, end)
```

- **One enrollment per (student, class, year)** — a student's past classes are never
  deleted, so academic history is preserved across year transitions.
- **One assignment per (teacher, subject, class, year)** — `CheckTeachingAssignment`
  middleware + service-layer checks both enforce it (defense in depth). A teacher can
  only view or save grades for classes they are assigned to.
- **One grade per (student, subject, class, year)** — `teacher_id` records who entered
  it; the average is the mean of all entered components (0–20 scale).
- Administrators manage everything via the `/admin/*` API; teachers only ever receive
  data scoped to their own assignments via `/teacher/*`; students only see their own
  grades, report card and timetable via `/student/*`.

## Requests, Documents, Announcements & Notifications (Phase 4)

```
requests        (student, reference REQ-*, type, reason, status, admin_note)
documents       (request, student, title, file metadata + storage path)
announcements   (author, title, body, audience: all|students|teachers)
notifications   (recipient user, type, title, message, data, read_at)
```

- **Request lifecycle** — `submitted → under_review → approved → ready` (or rejected).
  Administrators drive each transition; students track progress with a visual stepper.
- **Documents** — administrators generate the official document for an approved request,
  which marks it `ready` and lets the student download it.
- **Announcements** — audience-targeted (`all` / `students` / `teachers`); the listing
  endpoint is role-aware so each audience only ever sees what's meant for them.
- **Notifications** — every event (request submitted, status change, document ready,
  announcement published) notifies the right users; the bell shows an unread count and
  supports per-item and mark-all-read.

## Homework & Submissions (Phase 5.1)

```
homework_assignments  (teacher, subject, class, year, semester, title,
                       instructions, max_score, due_at, is_published)
homework_submissions  (homework, student, content, attempts, submitted_at,
                       is_late, score, feedback, graded_by, graded_at)
```

- **Lifecycle** — a teacher creates a **draft** (invisible to students), publishes it to the
  class, students submit, the teacher marks and returns it with feedback.
- **One submission per student per homework.** Re-submitting before the deadline updates the row
  and increments `attempts`; the previous text is replaced. Once marked, the submission is locked.
- **Late work is refused, not flagged** — `submit()` returns 422 after `due_at`, so a student cannot
  quietly submit late. Teachers extend the deadline by editing the homework instead.
- **Authorization** — a teacher may only set homework inside a class/subject they hold a
  `TeachingAssignment` for (the same guard the gradebook uses), and may only touch their own
  homework afterwards. A student only ever sees published homework for the class they are
  enrolled in that year.
- **Notifications** — publishing notifies every enrolled student; grading notifies the author.
  Both ride the existing `SynapseNotification` bell + mail channels.
- **Naming** — the table is `homework_assignments`, *not* `assignments`: `teaching_assignments`
  already owns "assignment" in this codebase, and `/teacher/assignments` is taken.

| Role    | Can do                                                                                       |
| ------- | -------------------------------------------------------------------------------------------- |
| Teacher | Create/edit/delete drafts, publish, withdraw, view the class roster, mark and return work      |
| Student | See published homework, submit, replace before the deadline, see the mark and feedback         |

### Attachments (Phase 5.2)

```
attachments  (school_id, attachable_type, attachable_id, uploaded_by_role,
              uploaded_by, file_name, mime_type, size, disk, path, visibility)
```

- **Teachers attach the homework document** (PDF/Word) to the brief; every student enrolled in
  that class can download it.
- **Students submit documents too** — a submission may be text, a file, or both. A submission
  with neither is rejected (422).
- **Polymorphic by design** (`attachable`), so course materials and lesson resources reuse the
  same table, service and download route instead of growing a parallel one.
- **Private disk only.** Files are never on `public`; the stored path carries a random segment
  so it cannot be guessed, and `/attachments/{id}/download` re-authorizes on **every** request —
  a leaked id grants nothing.
- **Visibility model** — `class` (open to the enrolled class) vs `private` (uploader + the
  teacher who set the work). A student cannot read another student's submission, even in the
  same class.
- **Limits** — 10 MB per file, 5 files per record, and a `pdf, doc, docx, odt, rtf, txt, png,
  jpg, jpeg` allow-list. Executables are rejected at validation, before anything is stored.

### Course Materials & Lessons (Phase 5.3)

```
lessons  (school_id, teacher_id, subject_id, class_id, academic_year_id, semester_id,
          title, topic, summary, body, minutes, sequence, is_published, published_at)
```

- **Teachers publish lesson material** — a written body plus any attached slides, notes or
  worksheets — for a class and subject they actually teach. Same `assertAssigned` gate as
  homework, so a teacher cannot publish into a class they do not hold.
- **Students browse by subject, then by topic.** `GET /student/materials` returns a nested
  `subject → topic → lessons` tree so the page reads like a syllabus, plus a
  `{lessons, subjects, files}` summary. List payloads omit the full body; the detail
  endpoint includes it.
- **Files reuse the Phase 5.2 layer unchanged.** `Lesson` implements the `HasAttachments`
  contract, and `AttachmentService::authorize()` delegates to it rather than switching on
  concrete classes — so lesson downloads went through the same authorisation, storage and
  `GET /attachments/{id}/download` route with no new download path.
- **Draft → publish.** Lessons are created unpublished; publishing notifies every student
  enrolled in that class for that academic year. Drafts are refused to students with 403.
- **Reading time** is estimated from the body at ~200 wpm when the teacher leaves it blank.
- **Class, subject and year are frozen after creation**, so a published lesson cannot be
  quietly moved to a different group. Deleting a lesson removes its stored files, not just
  the rows.

### Quizzes & Auto-marking (Phase 5.4)

```
quizzes         (school_id, teacher_id, subject_id, class_id, academic_year_id, semester_id,
                 title, instructions, max_score, closes_at, time_limit_minutes,
                 attempts_allowed, is_published, published_at, is_locked)
quiz_questions  (quiz_id, school_id, prompt, options[json], correct_option, points, sequence)
quiz_attempts   (quiz_id, student_id, school_id, answers[json], correct_count,
                 total_questions, score, attempt, submitted_at, feedback, is_reviewed)
```

- **Marking is immediate and exact.** An option index either equals the stored key or it does
  not — no teacher time is spent on it. The raw points are scaled onto the quiz's own
  `max_score`, so a 5-point question really is worth five times a 1-point one.
- **The answer key never reaches a student before they have sat the paper.** The paper
  endpoint selects only `prompt`, `options`, `points` and `sequence`; `correct_option` is a
  separate column that is simply never selected. Only `GET /student/quiz-attempts/{id}/review`
  returns it, and only for an attempt that student has already submitted.
- **A closed quiz cannot be sat.** Enforced server-side on both the paper and the submit
  routes, so an idle browser cannot outlast the deadline.
- **Attempts are capped** (`attempts_allowed`), and each one is a separate row, so an earlier
  attempt is never overwritten.
- **The paper locks on first attempt.** Editing a question after students have answered it
  would make the marks incomparable, so `update` refuses a question change once `is_locked`.
- **Publishing validates the paper** — no questions, or a key pointing outside its own option
  list, is refused with 422 rather than handing every student an unmarkable quiz.
- **Per-question analytics** for the teacher: which item the class missed, class average,
  highest, lowest and pass rate against `synapse.grading.pass_mark`.
- Quizzes reuse the Phase 5.2 attachment layer for an optional printed paper, and the same
  `assertAssigned` teaching guard as homework and materials.

### Messaging, School Events & the Personal Calendar (Phase 5.5)

```
conversations  (school_id, participant_a_id, participant_b_id, last_message_at)
               UNIQUE (school_id, participant_a_id, participant_b_id)
               CHECK  (participant_a_id < participant_b_id)
messages       (conversation_id, school_id, sender_id, body, read_at)
events         (school_id, user_id, title, description, type, starts_at, ends_at,
                all_day, location, audience, is_published, published_at)
```

Three features that share one theme: getting information between people, and then
showing one person everything that concerns them in a single place. The calendar
adds **no new table** — it is a projection of data the earlier phases already own.

- **A pair of people is one thread, always.** The pair is stored with the lower user
  id in `participant_a_id`, enforced by a `CHECK` constraint *and* a unique index, so
  "A writes to B" and "B writes to A" can never become two half-empty threads. Asking
  for a conversation that already exists returns it rather than creating a second.
- **Students may message teachers and administrators, but not other students.** This is
  a deliberate safeguarding default, not a limitation of the schema: the rule lives in
  `MessageService::assertMayMessage` and is asserted by tests. The recipient picker only
  ever lists people the server permits, so a student never sees an option that will be
  refused. Staff may write to students.
- **One `read_at` per message, not a read receipt per person.** A conversation has exactly
  two participants and a sender has by definition read their own message, so a second row
  would only be a way to get the counter wrong. Opening a thread is the read receipt.
- **Events are audience-targeted and drafted before they are announced** (`all`, `students`,
  `teachers` — the same vocabulary as announcements). Publishing is a separate, deliberate
  action because that is the moment the audience is notified; saving early tells nobody.
- **Outside your audience, an event does not exist.** Both a draft and an event aimed at
  another role return 404, never 403, so the response cannot confirm the record is there.
- **The calendar answers "what do I have on Thursday?" in one place.** It merges the
  timetable, exams, homework due dates, quiz deadlines and events into uniformly-shaped
  items (`kind, id, title, subtitle, starts_at, ends_at, all_day, url`). A recurring
  timetable row is expanded onto every matching weekday in the window, because a weekly
  row is not a calendar item until it is dated.
- **What you see follows from what you own.** A student sees their one enrolled class; a
  teacher sees *every* class/subject pair they are assigned, not just the first; an
  administrator holds no timetable and so sees school events only.
- **Nothing on the calendar is writable.** Each item carries a `url` back to the screen
  that owns it, so there is still exactly one place to change anything.

Routes: `/messages*`, `/events*` and `/calendar*` sit in the shared auth + tenant group
because who may message whom, and which events a role may see, are decisions about the two
people and the audience rather than about the caller's role. Event CRUD is under `/admin`.

### Analytics & the Pastoral Register (Phase 5.6)

```
no new tables — every figure is computed from grades, attendance,
homework_submissions and quiz_attempts on read
config/synapse.php  at_risk: { average, critical_margin, failing_subjects,
                               decline_points, missing_homework, submission_rate,
                               attendance_rate, quiz_average }
```

Phase 5 collected the marks, the registers and the submissions. This phase reads them
back: one screen that says how the school is doing, and a list that says *which pupil*
needs attention this week and why.

- **A risk flag is never stored.** `AtRiskService` recomputes every signal on each read.
  A stored flag goes stale the moment a teacher enters a mark, and nothing would remember
  to regenerate it — so `students` has no `is_at_risk` column, and a test asserts that it
  stays that way.
- **Every flag carries its reason in words.** Not "risk score 0.7" but *"Attendance is
  66.7% against a 80% expectation."* A form teacher can act on that; a score is only a
  prompt to go and look somewhere else.
- **Severity follows from the reason, not from a count.** Missing past-due homework and a
  term average well below the pass mark are `critical`; a narrow shortfall, a single
  declining trend or low submission rates are `warning`. A row is critical if *any* of its
  signals is, and the ordering is critical first, then lowest average, then name so the
  list does not shuffle between page loads.
- **An excused absence is not a warning sign.** Attendance is
  `(present + late) / (present + late + absent)`; `excused` sits outside both sides, because
  a medical absence is not something to intervene on. Being late still counts as attending.
- **Homework is only "missing" once the deadline has passed.** An assignment due next week
  is not evidence of a problem, so the count looks at published assignments whose `due_at`
  is in the past with nothing submitted.
- **Quizzes are compared as a percentage of their own paper.** A 20-mark quiz and a 10-mark
  quiz weigh the same, so `low_quiz_average` means something across mixed papers.
- **One teacher sees their own slice, by construction.** `AcademicScopeService` resolves
  the class/subject pairs a caller holds and every aggregation is filtered through it, so
  a maths teacher is never shown the English homework somebody else set. Attendance is the
  one exception — it is recorded per class per day and is not subject-specific. This is
  also why a teacher's register can legitimately differ from the administrator's: the
  denominators are different.
- **The same three endpoints serve both roles.** `AnalyticsController` is registered under
  `/admin` and `/teacher` with the same methods, because the services scope by caller. Two
  controllers would only give the definitions two places to drift apart.
- **"No data" is never reported as zero.** A class with nobody enrolled is omitted rather
  than shown at 0.0, and an average that cannot be computed is `null`. On a chart, 0.0
  reads as "this class is failing", which is a different claim.
- **A student is told what their teacher is told.** `/student/insights` runs the identical
  assessment, so a pupil can never be surprised by a conversation their form teacher
  started from the same numbers. The framing differs; the signals do not.
- **Thresholds are configuration, not constants.** Everything lives in
  `config/synapse.php` under `at_risk`, env-overridable per school, so a school can decide
  what "at risk" means without a code change.

Routes: `GET /admin/analytics`, `/admin/analytics/at-risk`, `/admin/analytics/students/{student}`,
the same three under `/teacher`, and `GET /student/insights`. A pupil from another school
returns 404 rather than 403, so the response cannot confirm the record exists.

---

## Document Request Types & Triage (Phase 6.1)

```
no new tables — `requests.type` stays a string, but it is now a closed list
DocumentRequest   TYPE_* constants + TYPES + TYPE_SLUGS + AUTO_GENERATABLE_TYPES
DocumentTypeService   classify() / triage() / catalogue()
```

This phase started from a defect, not a feature.

A student could file a request for anything: `type` was validated as
`'required', 'string', 'max:255'`. The document generator then matched that free text with
`Str::contains()` heuristics, and anything it did not recognise fell through to a **silent
default** that emitted a generic *"is known to this school"* certificate. The request was
marked ready, and the student was notified that their document was available to download.

So a pupil who asked for a **recommendation letter** received a document saying the school
knows them — and nothing anywhere in the system recorded that this had happened. Two of the
four options the request form actually offered (`Recommendation Letter`, `Other`) took that
path.

- **Refusing is the correct failure.** `certificatePayload()` now matches on the canonical
  type with no silent default; an unrecognised type throws rather than producing a plausible
  document. The request stays open, no `Document` row is created, and no "ready"
  notification is sent — all three are asserted, because a refusal that quietly half-completes
  is worse than the original bug.
- **The type is a closed list.** `Rule::in(DocumentRequest::TYPES)`, and the form is built
  from `GET /student/requests/types` rather than repeating the list in the client. Two copies
  of a list is how they drift, and the drift is what caused this.
- **Triage decides whether a template may answer at all.** `AUTO_GENERATABLE_TYPES` is the
  honest boundary: a recommendation letter needs an author who teaches the student, and
  "Other" is by definition unspecified. The admin queue shows *Needs a person* and the
  generate button is disabled with the reason, so nobody presses a button that was always
  going to fail.
- **Classification is computed, never stored.** Legacy free text is resolved on read, so
  improving the keyword rules fixes old requests immediately. A stored classification would go
  stale with nothing to re-run it. `triage.exact` distinguishes a canonically-filed request
  from one rescued by a keyword.
- **Specific beats generic.** "Transfer Certificate" and "School Leaving Certificate" both
  contain the word "certificate", so the discriminating word is tested first — the ordering
  is what makes the rules correct, and it is asserted.
- **Unmappable text is reported, not guessed.** "Permission to keep a goat on campus"
  classifies to `null` and is flagged for a person, rather than being quietly issued as
  something else.

Routes: `GET /student/requests/types`, `GET /admin/requests/triage`, and `?needs_human=1`
on the admin queue.

---


## Report-Card Comments (Phase 6.2)

`ReportCardPresenter::comment()` used to return one of four sentences chosen by an
average band. Every pupil at 14.5 received the identical appreciation in every
subject, in every school, forever — and no test covered it.

### Draft → review → lock → print

The ordering is the point. Generated text is a suggestion; nothing reaches a PDF
until a person has approved it.

| Step | Endpoint | Persists? |
|------|----------|-----------|
| Generate a suggestion | `POST /teacher/students/{student}/report-card-comment/draft` | No |
| Read what is in force | `GET /teacher/students/{student}/report-card-comment` | No |
| Approve, optionally lock | `PUT /teacher/students/{student}/report-card-comment` | Yes |

`report_card_comments` keeps `source` (`teacher`, `generated`, `ai`) so provenance
survives, and `is_locked`, which is what the presenter checks. Saving always
records `source = teacher` — a teacher who edits an AI draft has vouched for the
result, and the row should say so.

### The writer cannot compute, and cannot identify

Two constraints do the safety work:

- **`CommentEvidence` is a `final readonly` value object carrying numbers and
  subject names only.** There is no name, matricule or school in scope to leak,
  which is a stronger guarantee than redacting later — and it is what gets sent
  to a provider.
- **Every figure in a comment is one `GradeService` already computed.** A writer
  describes marks; it never derives one. `DeterministicCommentWriterTest` asserts
  that no number appears in the output that is not in the evidence.

### Provider selection

```php
config/ai.php  →  enabled (false), driver (deterministic|http), key, model,
                  max_words, fallback_on_error (true)
```

`CommentWriter` is bound to `HttpCommentWriter` only when the driver is `http`
**and** `enabled` **and** a key **and** a model are all present; otherwise the
deterministic writer is used. Any provider failure falls back rather than failing
a report card. Comments are constrained to `synapse.comments.max_words` and
`max_length` (400 characters) whatever the provider returns.

The `ai_assistant` plan flag gates the **provider**, never the feature. Every
school gets evidence-based comments; the flag only upgrades the phrasing.

### Language

`users.locale` (`en`/`fr`) is honoured by the deterministic writer today, with no
i18n library involved. Comments for a French-locale account are generated in
French.

### Not verified here

The live provider path (`driver: http`) has no reachable endpoint from this
sandbox. It is exercised only through `Http::fake()` in `ReportCardCommentTest`,
which covers fallback on a 500, truncation to the word ceiling, and the absence of
pupil identity in the request body. It has not been run against a real API.

## Architecture Rules Enforced

- Components ≤ 150 lines; UI primitives ≤ 80 lines; pages are thin wrappers
- Components **never** import `axios` or `authService` — they use hooks
- Services contain no JSX; hooks contain business logic; utils are pure functions
- Controllers stay thin and delegate to `App\Services` when logic grows
- No inline styles — Tailwind utility classes only; no hardcoded API URLs in components
- Route files contain configuration only (no page logic)

---

## Announcement Drafting (Phase 7.1)

An administrator used to write every announcement from scratch, in English, in a
bilingual country. Now they supply the facts and get a structured announcement in
either language.

```
POST /admin/announcements/draft
  { subject, key_points[], action_required?, date_text?, venue?,
    audience?, tone?, locale? }
→ { title, body, short_body, source, locale, ai_available }
```

### Drafting cannot publish

This is the whole design. `AnnouncementService::create()` — and therefore the
audience fan-out, the notification channels and the audit trail — is untouched by
the drafting path. A draft:

- creates no `announcements` row,
- notifies nobody (`Notification::assertNothingSent()` in the test suite),
- returns text into the existing publish form, where the administrator reads and
  edits it before pressing Publish.

Each draft is audited as `announcement.drafted` with `ai_generated`, so
provenance is recorded before the text reaches anybody.

### What the deterministic drafter does and does not do

It composes a correctly-structured announcement from the brief: audience-aware
salutation, register (formal or friendly), key points as a list, logistics worded
for whichever of date and venue were actually supplied, an action line, and a
closing — all in EN or FR.

**What it cannot do is translate prose.** If an administrator types the subject in
English and asks for French, the framing will be French and the subject will still
be English. That is why the response carries `source`, so the author can see which
path produced the text.

### Length limits are not one number

Two different truncations, because they answer two different questions:

| Field | Bound | Why |
|---|---|---|
| `body` | ≤ 5000 inclusive | `StoreAnnouncementRequest`'s `max:5000`. A draft longer than this could not be published, which would be worse than no draft. |
| `short_body` | ≤ 241 | Mirrors `AnnouncementPublishedNotification::body()`, which uses `Str::limit(..., 240)` — and `Str::limit` appends its ellipsis *after* truncating, so 241 is the real figure. |

`constrain()` truncates by hand rather than with `Str::limit` for exactly this
reason.

### SMS is not wired for announcements

`short_body` is a length preview for the author. Announcements are delivered over
bell and mail only — `SynapseNotification::channels()` defaults to
`['bell','mail']` and announcements do not opt into `sms`. Nothing here sends a
text message.

### Not verified here

The provider path has no reachable endpoint from this sandbox. It is covered only
by `Http::fake()` in `AnnouncementDraftTest`, which exercises fallback on a 500,
fallback on a malformed completion, truncation to the word ceiling, and the
absence of school or author identity in the request body.

## CSV Import Mapping (Phase 7.2)

`ImportService::parseCsv()` lowercased the header row and matched columns by exact
string. A Cameroonian school exporting `"Nom"`, `"Courriel"`, `"Classe"` produced a
file of nulls and a wall of *"name is required"* errors — for a spreadsheet that
was perfectly readable. The administrator also had to look up `class_id` in another
screen before importing a single pupil.

```
POST /admin/import/preview   → mapping, per-row resolution, warnings. Writes nothing.
POST /admin/import           → the same rows plus the confirmed mapping.
```

### Nothing is guessed silently

An unmapped column is a visible question. A wrongly-mapped one silently writes the
wrong data — so the matcher is deliberately willing to say "I don't know".

Matching runs in two passes. Exact alias matches claim their field first; then a
scored token-overlap pass considers the rest, and it only claims a field when:

- the winner is **unique** — `"Student Phone"` scores one for `name` (from "student
  name") and one for `phone`, so it ties and stays unmapped rather than being
  assigned to whichever field came first; and
- it covers **at least half** the header — `"Nom du directeur général"` shares only
  `"nom"` with `name`, so it is reported unmapped.

Each match reports how it was reached (`exact`, `fuzzy`, `suggested`), and the UI
labels the non-exact ones "check this".

### Class labels resolve, and refuse to when they can't

`ClassResolver` matches a label against the school's own classes on a normalised
key. `"level 3a"` and `"Level  3A"` both find `Level 3A`. A label matching **more
than one** class returns `ambiguous: true` and resolves to nothing — putting a
pupil in the wrong class is worse than asking.

### Normalisation never loses information

| Value | Treatment |
|---|---|
| Phone | Reformatted to `+237XXXXXXXX` from `690123456`, `+237 690 123 456`, `00237690123456`, `(+237) 6.90.12.34.56` |
| Phone, unrecognisable | **Passed through unchanged.** Six digits is not a Cameroonian number, and prefixing it would mean texting whoever happens to own it |
| Email | Lowercased and trimmed |
| Name | Trimmed only. Accents are never stripped — folding `"Ngo Bassa Élodie"` to `"Ngo Bassa Elodie"` would corrupt the record |

Accents *are* folded, but only in the throwaway comparison key used to match
headers and class names — never in a stored value.

### The client no longer decides what a column means

`ImportPage.jsx` used to lowercase headers and match class names with
`name.toLowerCase()`. Both were client-side copies of server rules, and the
lowercasing was the direct cause of the defect. The page now sends the header text
verbatim and renders whatever the server mapped.

### Provider role

The rule table handles ordinary English and French exports; the optional provider
exists for the tail (`"Nom & Prénoms de l'élève inscrits"`). Two constraints keep it
safe: **only the header row is sent** — never pupil data — and a suggestion is
labelled `suggested` for the administrator to confirm. A rule-table exact match is
never overruled by the model.

### Not verified here

The provider path is covered only by `Http::fake()`: fallback on a 500, the absence
of pupil data in the request body, and the inability to overrule an exact match. No
reachable provider exists from this sandbox.

## Timetable Overlap Detection (Phase 8.1)

`TimetableService::create()` and `update()` validated only school ownership. The
`timetable_slot_unique` index covers `(class_id, academic_year_id, day, start)` —
an **identical** start — so a class could hold Mathematics 08:00–10:00 and English
09:00–11:00 on the same Monday and the database would accept both. Nothing
downstream checked either, so the clash was simply stored and printed.

Overlap is now rejected with a message that says what it clashes with:

```
422  start: "That clashes with English, which already occupies 08:00–10:00 on this day."
```

Two intervals overlap when each starts before the other ends, which covers all
six cases: contained, containing, tail overlap, head overlap, one minute of
overlap, and identical.

**Back to back is not an overlap.** A class may finish at 10:00 and start again at
10:00, so the test is strict on both sides.

### Comparison is on minutes, not strings

The column is a `time`, so it reads back as `H:i:s` while the request supplies
`H:i`. A lexical comparison across those two forms is wrong at the boundary —
`'08:00' < '08:00:00'` is true — so both sides are converted to minutes since
midnight first.

### Update excludes itself

`assertNoOverlap()` takes the entry's own id and excludes it, so saving an entry
unchanged is not a clash with itself. Without that, every no-op save would 422.

## Mock Adapter Role Guards (Phase 8.2)

`requireActiveTenant(config)` checks that the caller's school has an active
subscription. It does **not** check the role. Every `admin*` handler in
`mockAdapter.js` used it, so with `VITE_USE_MOCK=true` a teacher or a student could
read the audit log, create accounts, edit the timetable and generate PDFs — while
Laravel's `role:admin` group refused every one of those requests.

All 60 `admin*` handlers now carry an explicit role check. The change is uniform
because the Laravel side is uniform: `routes/api.php:357` is the only
`prefix('admin')` group, it applies `role:admin`, and no `/admin` route is declared
outside it.

`timetable.test.mjs` samples twelve of those routes as a teacher and five as a
student, then re-reads the same twelve as an admin — so a guard that was added too
widely fails just as loudly as one that was missed.

## Tenant Isolation Fails Closed (Phase 8.3)

`TenantScope::apply()` used to read:

```php
if (TenantContext::id() !== null) { $builder->where('school_id', $id); }
```

That conflates two very different states, and the difference is the whole defect:

| State | Meaning | Old behaviour |
|---|---|---|
| Resolved to a school | An ordinary request | Scoped correctly |
| Resolved to no school | Platform super admin, deliberately cross-tenant | No constraint — correct |
| **Never resolved** | Nobody ran the `tenant` middleware | **No constraint — every school's rows** |

The third row is the bug. It is safe *today* only because all 150 authenticated
route declarations happen to apply the `tenant` middleware (verified: a sweep of
`routes/api.php` finds 0 exceptions, and the 7 declarations without it are all
unauthenticated). But the next route registered without it would serve
cross-tenant data silently rather than failing.

`TenantContext` now records whether a tenant was *resolved*, so a deliberate null
and an absent one are distinguishable, and the scope fails **closed** on the
latter during an HTTP request:

```php
$builder->whereNull($model->qualifyColumn('school_id'));  // matches nothing
```

### Console is deliberately exempt

The seeder creates every school with no tenant resolved, and `synapse:*` commands
and queued jobs run outside a request lifecycle. Failing closed there would break
them rather than protect anything — those paths use `forSchool()` or
`withoutTenant()` explicitly, and both are covered by tests.

### The second hatch

`BelongsToSchool::scopeWithoutTenant()` dropped the scope unconditionally. Its only
two callers are console commands (`synapse:prune-notifications` and
`synapse:generate-report-cards`), so it now throws if called during a request. A
request already has a tenant; a controller reaching for this is a bug worth
surfacing immediately. `forSchool()` remains the request-safe way to query another
school explicitly.

### What this does not do

It does not make cross-tenant access impossible — the platform super admin still
spans tenants, by design, and `forSchool()` still bypasses the scope on purpose.
What changed is that the *accidental* path now returns nothing instead of
everything.

## Phase Roadmap

- ✅ **Phase 1** — Foundation, strict modular architecture, auth, role redirects
- ✅ **Phase 2** — Academic year, classes, subjects, `TeachingAssignment` model,
  enrollment, student/teacher registration, teacher-access enforcement
- ✅ **Phase 3** — Grades, grade entry (assignment-scoped), report cards with rank,
  student timetable + admin timetable manager
- ✅ **Phase 4** — Request lifecycle with stepper, document generation/download,
  audience-targeted announcements, notifications with unread count
- ✅ **SaaS upgrade** — Multi-tenant schema (`school_id` + tenant scope), super admin,
  onboarding, subscription plans/subscriptions/payments, billing, plan limits, feature
  flags, white-label branding, payment abstraction, tenant-isolation test suite
- 🚧 **Phase 5 — Feature expansion** (no billing involved)
  - ✅ **5.1 Homework & submissions** — draft → publish → submit → replace → grade → return,
    with roster progress, notifications and full teacher/student UI
  - ✅ **5.2 Attachments** — teachers attach a brief document, students submit PDF/Word;
    polymorphic `attachments` table with per-request authorization on a private disk
  - ✅ **5.3 Course materials & lessons** — teachers publish lessons with attached slides and
    notes for a class they teach; students browse by subject and topic and download the files.
    Built on the `HasAttachments` contract, so no new download path was needed.
  - ✅ **5.4 Quizzes with auto-marking** — teachers build multiple-choice papers that mark
    themselves on submission; students get an instant score and a per-question review, and
    teachers get per-item analytics. The answer key is withheld server-side until the paper
    has been sat.
  - ✅ **5.5 Messaging, school events & personal calendar** — 1:1 messaging with an ordered
    pair so one thread per pair, students message staff but not each other, admin-authored
    audience-targeted events with a draft → publish step, and a read-only calendar that
    merges timetable, exams, homework, quizzes and events into one week view.
  - ✅ **5.6 Admin analytics + student at-risk alerts** — school-wide and per-teacher
    analytics computed on read from grades, attendance, homework and quizzes, plus a
    pastoral register that names the pupil and explains the reason in words. Signals
    are never stored, so they cannot go stale, and a student sees the same assessment
    their form teacher sees.
- ✅ **Phase 5 complete** — all six items delivered
- 🚧 **Phase 6 — Correctness and assistant support**
  - ✅ **6.1 Document-request types & triage** — `type` becomes a closed list, the silent
    generic-certificate fallthrough is replaced by an explicit refusal, and the admin queue
    separates what a template can issue from what needs a person. Fixes a live defect: a
    recommendation-letter request used to be issued a "is known to this school" certificate
    and announced to the student as ready.
  - ✅ **6.2 Evidence-based report-card comments** — every card's appreciation is now
    written from that pupil's actual marks behind a `CommentWriter` contract, replacing four
    hard-coded sentences that gave every 14.5 in the school identical text. A deterministic
    writer ships as the default; an HTTP provider is optional. Generated text is a draft and
    reaches a PDF only after a teacher has locked it.
- ✅ **Phase 6 complete**
- 🚧 **Phase 7 — Assistant support, continued** (Step 3 of the analysis build order)
  - ✅ **7.1 Announcement drafting (FR/EN)** — an administrator supplies the facts and gets a
    structured, correctly-language'd announcement. Drafting is a separate endpoint that
    persists nothing and cannot reach the audience fan-out; the admin still reads it and
    presses Publish. Deterministic drafter by default, provider optional.
  - ✅ **7.2 CSV import mapping** — headers are matched by rule table in EN and FR, class
    labels resolve to ids from the school's own list, and a dry-run preview says what will
    happen before a single account is created. Fixes a live defect: a spreadsheet headed
    `"Nom"`, `"Courriel"`, `"Classe"` used to import as nulls with a wall of per-row errors.
- ✅ **Phase 7 complete** — Step 3 of the analysis build order delivered
- 🚧 **Phase 8 — Defect list** (items no earlier phase owned)
  - ✅ **8.1 Timetable overlap detection** — a class can no longer be booked into two rooms
    at once. Fixes a live defect: the `timetable_slot_unique` index only covers an *identical*
    start, so Mathematics 08:00–10:00 and English 09:00–11:00 on the same Monday were both
    accepted and silently stored.
  - ✅ **8.2 Mock adapter role guards** — all 60 `admin*` handlers now check the role.
    `requireActiveTenant` verified the tenant and the subscription but not the role, so every
    admin endpoint in the mock was reachable by a teacher or a student while Laravel's
    `role:admin` group refused them.
  - ✅ **8.3 Tenant isolation fails closed** — `TenantScope` no longer treats "no tenant
    resolved" and "resolved to no tenant" as the same thing. An unresolved context during an
    HTTP request now matches nothing instead of every school's rows, and `withoutTenant()`
    refuses to run inside a request lifecycle.
- ✅ **Phase 8 complete**
