# SYNAPSE — Where AI Actually Fits

Analysis date: 2026-08-29. Every claim below is backed by a file/line read from
`backend/app`, `backend/routes/api.php`, `backend/config/synapse.php` and `frontend/src`.
This is a companion to `docs/GAP_ANALYSIS.md`, not a replacement for it.

---

## 0. Current state: there is no AI in this project

- `composer.json` requires only `laravel/framework`, `sanctum`, `tinker`, `barryvdh/laravel-dompdf`.
  No OpenAI/Anthropic/LLM/vector package.
- `frontend/package.json` has 8 runtime deps (react, axios, tailwind, lucide, clsx…). No AI SDK, no chart lib.
- A keyword sweep for `openai|anthropic|gemini|llm|gpt|langchain|embedding|vector` across the backend
  and `frontend/src` returns **zero** real hits.
- The only "intelligence" today is a 4-branch `match(true)` in
  `app/Services/Pdf/ReportCardPresenter.php::comment()` (lines 127–140) that picks one of four
  canned sentences by average.

So this is a greenfield AI decision, not a refactor of an existing AI layer.

---

## 1. What the platform is (verified)

| Fact | Evidence |
|---|---|
| Multi-tenant school SaaS, one shared MySQL, row-level isolation | `app/Models/Scopes/TenantScope.php`, `BelongsToSchool` trait on every tenant model |
| **Exactly 4 roles**, no parent role | `User::ROLES` = `super_admin, admin, teacher, student` |
| 78 named API routes across 4 role prefixes | `grep -c "name('api\." routes/api.php` → 78 |
| Cameroon-first | `config('synapse.currency') = XAF`, `sms.country_code = 237`, `country = Cameroon` |
| Plan feature flags already gate UI + API | `config/synapse.php` `features[]`, `EnsureFeature` middleware, `useFeature` hook |
| Real PDF rendering + QR verification is built | `barryvdh/laravel-dompdf`, `DocumentVerificationController`, `/verify/{code}` |
| Email + SMS notification channels are built | `app/Notifications/*`, `SmsManager` with `log`/`twilio`/`http` drivers |
| Scheduler exists | `routes/console.php`: `SweepSubscriptions` 02:00 daily, `PruneNotifications` weekly |
| ~51 frontend pages across all roles | `frontend/src/pages/**` |

**Note on `docs/GAP_ANALYSIS.md`:** several of its "Section A — fake/non-functional" items have since
been fixed and the doc is stale. Verified as now **real**: PDF documents (A1), pagination
(`HandlesPagination` concern + `PaginationTest`), API Resources (11 classes in `app/Http/Resources`),
password reset/change/profile (A3, `PasswordController`, `ProfileController`), notification channels
(A4), audit-log UI (A7, `AuditLogController` for both admin and super-admin), scheduler (B3).
Still genuinely absent: real payments (A2), parent role (C1), school fees (C2), timetable conflict
detection (C4), i18n (D1), analytics (D5).

---

## 2. The five strongest AI opportunities

Ranked by (existing manual burden) × (a clear insertion point already in the code).

### 2.1 Report-card comments & appreciations — **best first win**

**The gap, concretely.** `ReportCardPresenter::comment()` returns one of four hard-coded strings
based on the numeric average. Every student with a 14.5 gets the identical sentence, in every
subject, in every school, forever. `GAP_ANALYSIS.md` C3 independently flags missing
"teacher appreciations/comments".

**Why AI fits perfectly.** This is pure language generation from structured data — the hardest part
(computing averages, ranks, per-component scores) is already done and correct in `GradeService`.
The model is only asked to phrase what the numbers already say.

**Insertion point.** `ReportCardPresenter::comment($average, $rank)` — swap the `match(true)` for a
call into a new `App\Services\Ai\CommentWriter`, keeping the current strings as the fallback when AI
is disabled or the call fails.

Note: `ReportCardPresenterTest` has 4 tests covering component columns, dash rendering, legacy
grades and the `remark()` Pass/Fail threshold — **none of them exercise `comment()`**. So the
fallback path needs a new test written alongside this change, not one that already exists.

**Who benefits.** Teachers (bulk generate per class, then edit), students/parents (personalised
text), admins (a real differentiator to sell).

**Guardrail.** Comments must be editable before the PDF is locked, and `bulk class report cards`
(`POST /admin/classes/{schoolClass}/report-cards`) should generate drafts, not final text.

---

### 2.2 Document-request triage — **fixes a real correctness bug**

**The bug.** `StoreRequestRequest` validates `type` as `['required','string','max:255']` — **free
text, no enum**. `DocumentService::certificatePayload()` then does
`Str::of($request->type)->lower()` and substring-matches `'enrol'`, `'transcript'`, `'transfer'`,
`'conduct'`, `'good standing'`, `'leaving'`, `'graduat'`. Anything else falls through to a generic
*"is known to {school}"* certificate.

So a student typing "Certificate of Scolarity" (the normal Francophone phrasing, and Cameroon is
bilingual) silently receives a worthless generic PDF instead of an enrolment certificate.

**Two-step fix, only the second step needs AI.**
1. First: a proper `type` enum + i18n labels. This is a plain bug fix, do it regardless.
2. Then: an AI classifier maps free-text `type`/`reason` onto that enum, so a student can write
   *"I need a paper saying I'm still a student here for my father's job"* and get
   `enrolment_certificate`.

**Insertion point.** `RequestService` on submit (classify + suggest), and
`AdminRequestController::status` (show the admin a confidence + suggested outcome).

**Who benefits.** Students (correct document first time), admins (less back-and-forth).

---

### 2.3 Announcement drafting — **lowest build cost**

**The gap.** `StoreAnnouncementRequest` takes `title` (255), `body` (5000), `audience`
(`all|students|teachers`). The admin writes everything from scratch, in English, in a bilingual
country. `AnnouncementService::create()` then fans out to every matching user **in the current
tenant** (the `User::query()` call is caught by the global `TenantScope`) over bell + mail.

**Why AI fits.** Drafting, tone-adjusting (formal admin → friendly for students), translating
EN↔FR, and shortening to SMS length — note `AnnouncementPublishedNotification::body()` already
applies `Str::limit(..., 240)`.

**One correction worth knowing:** announcements currently go out over **bell + mail only**. SMS is
not wired for them. `SynapseNotification::channels()` defaults to `['bell','mail']`, and only four
notifications opt into `sms`: `DocumentReady`, `ReportCardReady`, `RequestStatusChanged` (only when
status becomes `ready`) and `SubscriptionReminder` (only at the `expired` stage), plus
`TemporaryCredentials` (`mail`+`sms`). So an AI-drafted *SMS campaign* to parents/students would need
`channels()` extended first — that plumbing does not exist yet.

**Insertion point.** A new `POST /admin/ai/announcement-draft` returning `{title, body}`; the
existing `AnnouncementManager.jsx` gains a "Draft with AI" button. **The admin still hits Publish** —
`AnnouncementService::create()` is unchanged, so the fan-out and audit path are untouched.

**Who benefits.** Admins (time), and indirectly every recipient (clearer, correctly-language'd comms).

---

### 2.4 CSV import mapping & cleaning

**The gap.** `ImportService::parseCsv()` lowercases the header row and matches columns by **exact**
string. `$row['name']`, `$row['email']`, `$row['matricule']`, `$row['class_id']`,
`$row['academic_year_id']`. A CSV with `"Nom"`, `"E-mail"` or `"Classe"` produces nulls and a wall of
per-row errors — and `class_id` is a numeric FK the admin must know in advance.

**Why AI fits.** Fuzzy header→field mapping, type/value normalisation (dates, phone numbers to
`+237…`, French accents in names), and resolving `"Seconde A"` → the right `class_id` from the
school's own class list. Plus a dry-run preview, which `GAP_ANALYSIS.md` D4 already asks for.

**Insertion point.** Between `parseCsv()` and `importStudents()`. The existing per-row
`try/catch` + `errors[]` reporting is the perfect place to surface AI-suggested corrections as
"we interpreted row 12 as X — confirm?".

**Who benefits.** Admins, specifically during the painful first-week onboarding.

---

### 2.5 The `advanced_analytics` flag — **a promised feature with nothing behind it**

**Verified.** `advanced_analytics` appears in exactly 5 places: `config/synapse.php` (the flag list),
`DatabaseSeeder.php:105`, `frontend/src/pages/super-admin/PlansPage.jsx:21`, and two mock files.
**There is no analytics endpoint, no chart, no query behind it.** Schools on the Enterprise plan are
being sold a feature that does not exist.

**Split this correctly — do not reach for an LLM first.**
- **Deterministic (SQL, not AI):** pass rates, subject averages, attendance trends, teacher
  grade-submission completion. The data is all there; `AdminAcademicService::summary()` currently
  returns only 5 raw counts.
- **AI on top:** a natural-language summary layer — *"Term 1 attendance in Seconde B fell 12%
  versus last term; Mathematics is the weakest subject across three classes"* — and anomaly
  flagging (a grade distribution that shifted implausibly between terms).

**Who benefits.** Admins (the actual buyer), super-admin (health across tenants).

---

## 3. Full opportunity map by user role

### Super admin (platform operator)

| Opportunity | Type | Notes |
|---|---|---|
| **Churn / at-risk-school prediction** | ML | `platformStats()` currently returns counts + `revenue()` only. Usage signals exist: student counts vs plan limits, grade-entry activity, login recency (`last_login_at`). Predict which trial school won't convert. |
| **Dunning message generation** | LLM | `SweepSubscriptions` already runs daily and `EnforceSubscription` locks schools out. Nothing *persuades* them to renew. Generate a personalised renewal email. |
| **Support copilot / school brief** | LLM+RAG | No impersonation exists (`GAP_ANALYSIS.md` E). Summarise a school's state — plan, usage, recent audit events (`AuditService::latest()`) — into a support brief. |
| **Audit-log anomaly detection** | ML | `audit_logs` is written on every sensitive action and is now queryable via two controllers. An ideal anomaly-detection feed. |

### School admin (the paying customer — highest commercial value)

| Opportunity | Type | Notes |
|---|---|---|
| At-risk student early warning | ML | `grades` + `attendances` are both tenant-scoped and populated. Flag declining students before the term ends. |
| Timetable generation | **Constraint solver, not LLM** | `TimetableService::create()` validates only that class/subject belong to the school. **Zero overlap checking.** A teacher can be double-booked right now. |
| Document-request triage | LLM | See 2.2. |
| Announcement drafting + FR/EN | LLM | See 2.3. |
| CSV import mapping | LLM | See 2.4. |
| Natural-language data queries | LLM + **hard tenant scoping** | "Show every student in Seconde A below 10/20 in Maths." See the guardrails in §4 — this is the highest-risk item on the list. |
| Onboarding copilot | LLM | A new school lands on an empty dashboard (`GAP_ANALYSIS.md` D6). An assistant that walks through year → classes → subjects → teachers → students → assignments. |
| Analytics narrative | LLM over SQL | See 2.5. |

### Teacher (biggest reduction in manual work)

| Opportunity | Type | Notes |
|---|---|---|
| **Report-card comments** | LLM | See 2.1. Highest-value, lowest-risk. |
| Grade-entry anomaly detection | **Statistics, not LLM** | `GradebookController::store()` accepts marks with no plausibility check. Flag a class average that jumps 6 points between terms, or a mark entered out of scale. |
| Attendance patterns | ML | `Attendance` has 4 statuses (`present/absent/late/excused`) + free-text `remark`. Detect a student sliding into chronic absence. |
| Exam/question generation | LLM | `Exam` exists as a record (`ExamController`, `ExamService`) but there is no question bank at all. |
| Parent-communication drafting | LLM | **Blocked** — no parent role exists yet. |
| Lesson plans / schemes of work | LLM | No such entity exists (`GAP_ANALYSIS.md` C3). Greenfield. |

### Student

| Opportunity | Type | Notes |
|---|---|---|
| Personalised revision plan | LLM | From their own `studentGrades()` output — weakest subjects first. |
| Report-card explainer | LLM | Translates a /20 average, a `mention` and a rank into plain language. |
| Request drafting | LLM | The `reason` field is free text up to 2000 chars; help the student phrase it. |
| Study chatbot | LLM+RAG | Only if the school uploads materials — **no lesson-resource entity exists yet**. |

### Parent / guardian

**Every parent-facing AI idea is blocked on a prerequisite:** `User::ROLES` has no `parent`, and
`GAP_ANALYSIS.md` C1 correctly calls this the #1 product gap. An AI parent digest or WhatsApp bot is
a *great* idea for this market — but build the role first.

---

## 4. Guardrails specific to this codebase

These are not generic AI-safety boilerplate; each maps to a real mechanism here.

1. **Ship it as a plan feature flag.** Add `ai_assistant` to `config/synapse.php` `features[]`.
   `EnsureFeature` middleware and the `useFeature` hook then gate it end-to-end for free, and it
   becomes a genuine reason to upgrade — which is the actual business case.

2. **RAG must be tenant-isolated by construction.** The whole platform relies on a global
   `TenantScope` — and it has two documented escape hatches. Read the code:
   `TenantScope::apply()` is a **no-op when `TenantContext::id()` is null**, and
   `BelongsToSchool::scopeWithoutTenant()` drops the scope entirely (already used by
   `NotificationService::prune()`). That means a queued embedding job with no tenant context, or any
   code path calling `withoutTenant()`, will happily read **every school's students at once**.

   If you build embeddings over student records, one shared index means a cross-tenant leak — the
   exact failure the architecture exists to prevent. Either one namespace/collection per
   `school_id`, or (safer) assemble context through Eloquent queries that already carry the scope,
   assert `TenantContext::id() !== null` before any AI retrieval, and never let the model query a
   global index.

3. **Student data is minors' data.** Names, matricules, marks and attendance. Before any external
   API call: redact or pseudonymise (send `student_id`, not name), check data-residency terms, and
   make it a per-school opt-in. Cameroon has Law No. 2010/012 on cybersecurity and personal data.

4. **Human-in-the-loop, always.** AI drafts; a human publishes. Concretely: report-card comments
   editable before PDF lock, announcements published only via the existing
   `AnnouncementService::create()`, request triage shown as a *suggestion* next to the admin's
   decision. Then record `ai_generated => true` in `AuditService` so every generated artefact is
   attributable.

5. **Never let an LLM compute a number.** Averages, ranks and `mention` thresholds live in
   `GradeService` and `config('synapse.grading.mentions')` and are correct. AI may *describe* them;
   it must never *derive* them. Same for timetable scheduling (use a constraint solver), payments
   and attendance records.

6. **Mirror the mock adapter.** `frontend/src/services/mockAdapter.js` reproduces the whole API
   contract in-browser so the platform demos without Laravel. Any new AI endpoint needs a mock
   counterpart or `VITE_USE_MOCK=true` demos will break.

7. **Cost per tenant.** `POST /admin/classes/{class}/report-cards` is a batch job over a whole
   class. Generating comments for 60 students × 8 subjects is 480 model calls — that belongs in a
   queued job (`app/Jobs/GenerateClassReportCardsJob.php` already exists as the pattern), not an HTTP
   request, with a per-tenant rate limit.

8. **FR/EN from day one.** `users.locale` exists and is validated `in:en,fr`, but **no translation
   system exists** — no i18n library in `package.json`, no `resources/lang`. AI is actually a
   shortcut here: generate in the school's language directly rather than translating afterwards.

---

## 5. What NOT to use AI for

| Area | Why |
|---|---|
| Grade computation, ranking, `mention` assignment | Already deterministic and correct in `GradeService` / `config('synapse.grading')`. An LLM introduces error into a legal document. |
| Payment amounts, subscription state | `SubscriptionService` / `PaymentService`. Deterministic money logic. |
| Timetable conflict detection | A constraint problem with a provably correct answer. Use validation + a solver. |
| Attendance recording | A teacher's legal attestation. Suggest patterns, never write records. |
| Authorization / tenant resolution | `IdentifyTenant` derives the school from the authenticated user. Never let a model influence it. |

---

## 6. Suggested build order

**Step 1 — one week, one visible win.**
Report-card comments (§2.1). Single insertion point, existing test coverage for the fallback,
immediately visible to all four roles, and it turns a hard-coded 4-sentence `match` into a real
feature. Add `ai_assistant` to `config/synapse.php` and `config/ai.php` in the same PR.

**Step 2 — the bug that AI also fixes.**
Document-request type enum + classifier (§2.2). Ships a correctness fix even with AI switched off.

**Step 3 — cheap and broad.**
Announcement drafting with FR/EN (§2.3), then CSV import mapping (§2.4).

**Step 4 — make the analytics flag honest.**
Deterministic SQL analytics first, AI narrative second (§2.5). Do not build the AI layer on top of
analytics that do not exist yet.

**Step 5 — the moat.**
At-risk student detection and churn prediction (§3). These need accumulated data, so start
collecting the signals early even if the models come later.

---

## Appendix — verification log

| Claim | How verified |
|---|---|
| No AI dependency exists | Read `composer.json`, `frontend/package.json`; keyword sweep across `backend` + `frontend/src` → 0 hits |
| 4 roles, no parent | `app/Models/User.php` `ROLES` constant |
| 78 named API routes | `grep -c "name('api\." routes/api.php` → 78 |
| Report-card comments are 4 canned strings | `app/Services/Pdf/ReportCardPresenter.php` lines 127–140 |
| No test covers `comment()` | `tests/Unit/ReportCardPresenterTest.php` has 4 tests: component union, dash rendering, legacy grades, `remark()` Pass/Fail — none call `comment()` |
| Document request type is unvalidated free text | `app/Http/Requests/Student/StoreRequestRequest.php` + `DocumentService::certificatePayload()` `match(true)` chain |
| No timetable conflict detection | `app/Services/TimetableService.php` — `create()`/`update()` validate only school ownership |
| CSV headers matched exactly | `app/ImportService::parseCsv()` lowercases and uses literal keys |
| `advanced_analytics` has no implementation | 5 references, all config/seeder/mock — no controller, service or component |
| Email + SMS channels built | `app/Notifications/SynapseNotification.php` `via()` fans out to `BellChannel`/`mail`/`SmsChannel`; `SmsManager` supports `log`/`twilio`/`http` drivers |
| SMS is opt-in per notification type | Default `channels()` = `['bell','mail']`. Only `DocumentReady`, `ReportCardReady`, `RequestStatusChanged` (at `ready`), `SubscriptionReminder` (at `expired`), `TemporaryCredentials` include `sms` |
| Real payments absent | `MtnMobileMoneyGateway` / `OrangeMoneyGateway` / `CardGateway` throw `RuntimeException` |
| `locale` stored but no i18n | Migration `2026_08_28_090100`, validated `in:en,fr`; no i18n lib in `package.json` |
| Tenant scope has escape hatches | `TenantScope::apply()` no-ops when `TenantContext::id()` is null; `BelongsToSchool::scopeWithoutTenant()` drops the scope (used by `NotificationService::prune()`) |
| Frontend is healthy | `npm run lint` → 0 problems; `npm run build` → succeeded in 579 ms |
| Backend tests could not be run | No `php` or `composer` binary in this environment (searched `/usr/bin`, `/usr/local/bin`, filesystem) |
