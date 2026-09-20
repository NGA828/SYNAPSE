# SYNAPSE — Gap Analysis & Improvement Roadmap

**Revision 2 — 2026-09-20.** Original audit 2026-08-28. Re-verified against the current tree:
`backend/app` (283 PHP files, 37 models, 43 migrations), `backend/routes/api.php` (164 route
declarations), `backend/tests` (26 real test files) and `frontend/src` (227 files, 63 pages).
Every item below cites a file that exists in this repository today. PHP is not installed in the
review sandbox, so the test suite was read, not run.

Section IDs (A1…, B1…, C1…, D1…, E, F) are unchanged from revision 1 so that
`docs/AI_OPPORTUNITY_ANALYSIS.md` and the README's phase log still resolve.

Legend: ✅ **Done** · ⚠️ **Partial** · ❌ **Open**

---

## Scorecard

| Section | Items | ✅ Done | ⚠️ Partial | ❌ Open |
|---|---|---|---|---|
| A — fake / non-functional | 8 | 5 (A1, A4, A5, A6, A7) | 2 (A3, A8) | 1 (A2) |
| B — engineering | 11 | 3 (B1, B2, B3) | 5 (B4, B6, B7, B8, B10) | 3 (B5, B9, B11) |
| C — school functionality | 8 | 0 | 3 (C3, C4, C7) | 5 (C1, C2, C5, C6, C8) |
| D — front-end | 9 | 2 (D4, D9) | 3 (D2, D5, D8) | 4 (D1, D3, D6, D7) |
| E — SaaS / super-admin | 8 | 0 | 3 (dunning, receipts, metering) | 5 |

**Reading:** the revision-1 "Phase 5 — Make it real" list is delivered except CI. The platform is no
longer fake anywhere except **payments**. What remains is product breadth (parents, fees, promotion,
i18n) and operations (CI, Docker, caching, object storage).

---

## A. Things that were fake / non-functional

| # | Status | Aug-28 finding | Sept-20 state (evidence) | Still to do |
|---|---|---|---|---|
| A1 | ✅ | Documents were plain text renamed `*.pdf`. | `barryvdh/laravel-dompdf ^3.1.2` in `composer.json`. `DocumentService` renders `resources/views/pdf/{certificate,report-card,transcript,receipt}.blade.php` through `Services/Pdf/PdfRenderer`, stores on `synapse.documents.disk`, stamps a `SYN-XXXX-XXXX` code, and `GET /api/verify/{code}` (public, throttled) checks authenticity. `DocumentPdfTest` covers it. | QR image on the page (code is text-only); signature/stamp block; verification page in the SPA. |
| A2 | ❌ | No real payment collection. | **Unchanged.** `Services/Payments/{MtnMobileMoney,OrangeMoney,Card}Gateway` each still `throw new RuntimeException('… not configured')`. No webhook/callback route in `api.php`. Only `MockPaymentGateway` completes a charge. | MTN MoMo Collections + Orange Money Web Payment APIs; `POST /webhooks/{provider}` with signature check; idempotency keys on `payments.reference`; `pending → succeeded/failed` polling job; refunds. This one item now blocks both SaaS revenue and the future fees module (C2). |
| A3 | ⚠️ | Auth was login/logout only. | `Auth/PasswordController` (`/forgot-password`, `/reset-password`, `/password`), `Auth/ProfileController` (`GET/PATCH /profile`, session list from `tokens()`, `/profile/sign-out-others`), `EnsurePasswordIsRotated` middleware (`password.rotated`) on every role group, `throttle:login` = 5/min per email+IP. `PasswordManagementTest` exists. | 2FA for admin/super-admin; e-mail verification (`email_verified_at` cast exists, never set); token TTL (`config/sanctum.php` `expiration => null`). |
| A4 | ✅ | Notifications never left the DB. | 16 concrete classes in `app/Notifications`, all extending `SynapseNotification implements ShouldQueue` (queue `notifications`, 3 tries, back-off 10/60/300 s). Channels: `Channels/BellChannel`, Laravel `mail`, `Channels/SmsChannel` → `Services/Sms/SmsManager` with `Twilio`, `Http` and `Log` gateways (`SMS_DRIVER`, `SMS_COUNTRY_CODE=237` in `.env.example`). Per-user channel preferences honoured in `via()`. `NotificationDeliveryTest` exists. | SMS is opted-in by only 5 notifications (documents, report cards, request status, subscription reminders, temporary credentials); announcements and absence alerts do not use it. No WhatsApp channel. No digest e-mails. |
| A5 | ✅ | `sendToRole()` relied on the global tenant scope. | Renamed `notifyRole(School\|int\|null $school, ?string $role, …)`; explicit `where('school_id', $schoolId)`, returns early when no school, chunks by 200. `TenantScope` now fails closed (README Phase 8.3, `TenantScopeTest`). | — |
| A6 | ✅ | Bulk import defaulted everyone to `password123`. | `ImportService` uses `RegistrationService::temporaryPassword()` / `Str::random(10)`; `RegistrationService` sets `must_change_password = true` and sends `TemporaryCredentialsNotification` (mail + SMS). Middleware blocks every role group until rotated. | — |
| A7 | ✅ | Audit logs were write-only. | `GET /admin/audit-logs` and `GET /super-admin/audit-logs` (paginated, searchable, `AuditLogResource`); `pages/admin/AuditLogPage.jsx` with `SearchInput` + `Pagination`. Super-admin dashboard shows `audit.latest(20)`. | Date/actor/action filters; CSV export. |
| A8 | ⚠️ | Mock adapter can mask a broken backend. | Mock handlers now enforce roles (README Phase 8.2). The login page shows "Demo accounts (mock mode)" when `VITE_USE_MOCK=true`. | No persistent "MOCK MODE" banner in the app shell; no CI check that the real API satisfies the mock's contract. |

---

## B. Scalability / engineering gaps

| # | Status | Aug-28 finding | Sept-20 state (evidence) | Still to do |
|---|---|---|---|---|
| B1 | ✅ | Zero pagination (`grep paginate(` = 0). | `Http/Concerns/HandlesPagination` — server-side `?search=&sort=&per_page=&page=`, whitelisted sort columns, bounded by `synapse.pagination.max_per_page` (100), no `per_page=all`. Used by 11 controllers (students, teachers, requests, schools, subscriptions, payments, audit logs ×2, homework, lessons, quizzes); notifications, messages, events paginate in their services. Front-end `usePaginatedList` hook + `Pagination.jsx`. `PaginationTest` exists. | Attendance and grade list endpoints are bounded by class, not paginated — acceptable. |
| B2 | ✅ | No API Resource layer. | 20 `JsonResource` classes in `app/Http/Resources` (Student, Teacher, School, Subscription, Payment, AuditLog, Document, Homework, Quiz, Message, Event, …). | Not every endpoint goes through a Resource yet (dashboards, gradebook, timetable return arrays). No `/api/v1` prefix. |
| B3 | ✅ | `routes/console.php` had only `inspire`. | Schedules `synapse:sweep-subscriptions` (daily 02:00, `withoutOverlapping`, `onOneServer`), `synapse:prune-notifications --days=90` (weekly), `auth:clear-resets`, `queue:prune-batches`. `Console/Commands/GenerateReportCards` also exists. `SubscriptionSweepTest` exists. | Attendance auto-close, backups. |
| B4 | ⚠️ | Nothing queued; no worker. | Everything heavy is now *queueable*: all notifications, `Jobs/GenerateClassReportCardsJob` (bulk class PDFs). `QUEUE_CONNECTION=database`. | Neither README tells the operator to run `php artisan queue:work` — without it nothing is delivered. No Horizon, no Redis, no failed-job alerting. Imports still run inside the HTTP request. |
| B5 | ❌ | Caching unused. | **Unchanged.** `grep "Cache::" backend/app` = 0. `GradeService::reportCard()` (346 lines) still loads every classmate's averages and ranks in PHP; `AnalyticsService` and `AtRiskService` compute on every read (by design per README 5.6, but with no cache in front). | Cached per-tenant aggregates with tag invalidation on grade write; DB-side `AVG()`/window ranks; materialised rank per (class, semester). |
| B6 | ⚠️ | 3 tests, no CI. | **26 real test files** — feature: pagination, PDF, notification delivery, password management, subscription sweep, tenant isolation, timetable overlap, homework, quizzes, lessons, messages, events, calendar, analytics, at-risk, import mapping, document triage, report-card comments, announcement drafts, teacher timetable, student assistant; unit: tenant scope, header mapper, presenter, deterministic writers. | **Still no `.github/workflows`.** No frontend test runner (`package.json` scripts: dev/build/lint/preview only). No billing/policy tests. |
| B7 | ⚠️ | No Docker, health check, Sentry, structured logs. | Health route `/up` is configured in `bootstrap/app.php`. | No `Dockerfile`, no `docker-compose.yml`, no Sentry/Flare, no `.env` validation, no structured (JSON) logging. |
| B8 | ⚠️ | Only default `throttle:api`; no login lockout. | Named limiters in `AppServiceProvider`: `login` (5/min per email+IP, 20/min per IP), `password` (5 per 15 min), `assistant` (per-user, config-driven + daily ceiling in controller). | No per-tenant or per-role limiter on the general API; no account lock after N failures (throttle only). |
| B9 | ❌ | Local disk; logos as base64 in MySQL. | **Unchanged.** `FILESYSTEM_DISK=local`; `2026_08_21_010000_expand_school_logo_column` widens `schools.logo` to `longText`. Documents and attachments default to the `local` disk (`synapse.documents.disk`, `synapse.attachments.disk`) — private, which is correct, but single-node. Attachments are validated for extension and count (`AttachmentService`) and size (`synapse.attachments.max_size`, 10 MB, enforced in the `Store*Request` classes) — so the validation half of this item is done. | S3-compatible disk (`league/flysystem-aws-s3-v3`), logo stored as a file instead of base64, temporary signed URLs for downloads. |
| B10 | ⚠️ | No API docs. | `docs/postman/SYNAPSE-API.postman_collection.json` + Local/Mock environments, generated by `tools/generate_postman_collection.py`. | No OpenAPI spec; Postman collection has to be regenerated by hand. |
| B11 | ❌ | No soft deletes / export / deletion. | **Unchanged.** No model uses `SoftDeletes`; no per-tenant export or purge endpoint. | `SoftDeletes` on academic entities; `synapse:export-school` and `synapse:purge-school` commands with a retention window. |

---

## C. Missing school functionality

### C1. Parent / guardian portal — ❌ **still the #1 gap**
`User::ROLES` is still `super_admin, admin, teacher, student` (`app/Models/User.php:45`). No guardian
table, no student↔guardian link. Everything the portal would show already exists as data (grades,
attendance, report-card PDFs, homework, messages, events) — the missing piece is the role, the link
table, the read-only routes, and sibling switching. Absence alerts by SMS become possible the day this
exists because `SmsChannel` already works.

### C2. School fees / tuition module — ❌ **still the #2 gap**
No fee, invoice or student-payment model (`ls app/Models` — `Payment` is the *school's* SaaS payment).
Depends on A2 for collection. Design notes unchanged: fee structures per class/year, installments,
partial payments, discounts/scholarships, receipts (the PDF `receipt.blade.php` can be reused), arrears
report, optional "block report card while unpaid" policy.

### C3. Academic depth — ⚠️
| Item | Status | Evidence |
|---|---|---|
| Promotion / repeat / graduate / transfer / withdraw, year rollover, alumni | ❌ | No such status or command anywhere in `app/`. |
| Bulk report-card generation per class | ✅ | `POST /admin/classes/{class}/report-cards` → `GenerateClassReportCardsJob` (queued). |
| PDF export of report card / transcript | ✅ | `/student/report-card/pdf`, `/student/transcript/pdf`, admin equivalents; `window.print()` on the student page. |
| Teacher appreciations | ✅ | `ReportCardComment` model, `TeacherReportCardCommentController` draft → review → lock; only locked text reaches the PDF (README 6.2). |
| Mentions | ✅ | `ReportCardPresenter::mention()` from `synapse.grading.mentions`. |
| Subject coefficients | ❌ | No `coefficient` column on `subjects`; averages are unweighted. |
| Class average / min / max per subject on the card | ❌ | Not computed in `GradeService::reportCard()` or the presenter. |
| Conduct / discipline mark | ❌ | No field. |
| Bilingual FR/EN card, GCE letter grades | ❌ | `report-card.blade.php` has no `__()` calls; single English template. |
| Exam scheduling | ⚠️ | `Exam` now carries `date`, `start`, `end`, `room` and there is an admin exams page + ranking. No invigilator assignment, seat allocation, or results-publication step. |
| Homework & submissions | ✅ | Phase 5.1 — full draft → publish → submit → grade → return cycle with attachments. |
| Lesson resources | ✅ | Phase 5.3 — `Lesson` + attachments, student materials browser. |
| Quizzes | ✅ | Phase 5.4 — auto-marked MCQ with per-item analytics. |
| Syllabus / scheme of work | ❌ | No entity. |

### C4. Timetable — ⚠️
`TimetableService::assertNoOverlap()` now rejects a slot that overlaps another slot **of the same class**
(minute-based comparison, update excludes itself, `TimetableOverlapTest`). Still open:
- **Teacher double-booking is not checked** — the query filters on `class_id` only; the same teacher can
  still be placed in two classes at 08:00 Monday.
- No `rooms` entity, so no room clash check.
- No period templates, workload limits, or builder UI beyond the form.

### C5. Attendance — ❌
Unchanged: one record per student per day per class, statuses `present/absent/late/excused` plus a
free-text `remark` (`Attendance::STATUSES`). No justification workflow, no parent alert (blocked on
C1), no per-period attendance, no monthly/truancy report. `AnalyticsService::attendance()` and
`AtRiskService` do surface attendance rates and absence streaks, which is a start.

### C6. Discipline, health & student records — ❌
Unchanged. No incident register, sanctions, merit points, medical record, emergency contacts, photos or
ID cards. `grep -ril "disciplin|incident|medical|emergency" app/Models database/migrations` = 0.

### C7. Communication — ⚠️ (mostly done)
- ✅ Direct messaging: `Conversation` (ordered pair, one thread per pair) + `Message`, `/messages/*`
  routes, students may message staff but not each other, unread counts (`MessageTest`).
- ✅ School events with audience targeting and draft → publish; personal calendar merging timetable,
  exams, homework, quizzes and events (`CalendarService`).
- ✅ Announcement drafting in FR/EN (`AnnouncementDraftService`, deterministic drafter by default).
- ❌ Announcement read receipts; SMS campaigns (README 7.1 states "SMS is not wired for announcements");
  parent-facing anything (blocked on C1).

### C8. Optional revenue modules — ❌
Unchanged: no HR/payroll, library, transport, cafeteria, inventory, hostel.

---

## D. UX / front-end gaps

| # | Status | Aug-28 finding | Sept-20 state (evidence) |
|---|---|---|---|
| D1 | ❌ | No i18n. | **Unchanged.** No i18n dependency in `frontend/package.json` (deps: react, react-router, axios, tailwind, lucide, clsx). `users.locale` column exists and the AI drafter/tutor are bilingual server-side, but the UI is English-only. Still the single biggest market blocker. |
| D2 | ⚠️ | No exports or print styles. | Two `window.print()` buttons (student report card, teacher timetable) and real PDF downloads for report card/transcript/receipt. No CSV/Excel export of students, grades, attendance or payments; no `@media print` stylesheet. |
| D3 | ❌ | No global search, no bulk actions. | Unchanged. Per-list search exists (`SearchInput` + `?search=`), nothing global; no bulk enroll/promote/message. |
| D4 | ✅ | Import UX. | `pages/admin/ImportPage.jsx`: downloadable CSV templates, EN/FR header mapping (`ImportMappingService`, `DeterministicHeaderMapper`), class-label resolution, `POST /admin/import/preview` dry run before `POST /admin/import`. `ImportMappingTest`, `HeaderMapperTest`. |
| D5 | ⚠️ | No analytics/charts. | Analytics now exist server-side: `AnalyticsService` (pass rate, average, per-class, grade distribution, attendance, engagement) and `AtRiskService` (pastoral register), surfaced on `admin/AnalyticsPage.jsx`, `teacher/InsightsPage.jsx`, student insights. Charts are limited to `BarList.jsx`, `ProgressRing.jsx` and one hand-rolled `super-admin/BarChart.jsx`; no chart library, no trends over time, no fee curves (no fees). |
| D6 | ❌ | No admin onboarding checklist. | Unchanged — `admin/DashboardPage.jsx` has no setup steps; a new school still lands on an empty dashboard. |
| D7 | ❌ | No PWA / offline / dark mode / a11y. | Unchanged — `frontend/public` holds only `favicon.svg`; no manifest, service worker or `vite-plugin-pwa`. |
| D8 | ⚠️ | No confirm dialogs, inconsistent feedback. | Eight `window.confirm()` calls guard deletes (academic years, subjects, students, teachers, grade components, homework, materials, quizzes). No `Modal`-based confirm, no toast system, no skeletons (`grep Skeleton|animate-pulse` = 0). |
| D9 | ✅ | No profile page. | `pages/account/ProfilePage.jsx` — name/phone/locale, password change, notification channel preferences, active sessions with "sign out others". |

---

## E. SaaS / super-admin gaps

| Gap | Status | Sept-20 state |
|---|---|---|
| Impersonation | ❌ | No `impersonat*`/`login-as` anywhere. Support still cannot see what a school admin sees. |
| MRR / ARR / churn / trial-conversion | ⚠️ | `SchoolService::revenue()` returns a single `mrr` = sum of active+trial subscription amounts (trials inflate it). No ARR, churn, cohort or conversion metrics; dashboard is otherwise counts (`platformStats()`). |
| Dunning | ⚠️ | `SweepSubscriptions` sends `SubscriptionReminderNotification` at `synapse.renewal_reminder_days` (7/3/1) for trial-ending and expiring, plus one "expired" notice, to school admins by bell/mail/SMS. No retry sequence after a *failed payment*, no grace period — `EnforceSubscription` still hard-403s the day after `end_date`. |
| Coupons, annual pricing, per-student tiers, proration | ❌ | `SubscriptionPlan.billing_interval` exists (monthly/yearly plans can be created) but there is no coupon table, no per-seat pricing, and `BillingService::upgrade()` → `SubscriptionService::changePlan()` charges the full new-plan amount with no proration (`grep -i prorat app/Services` = 0). |
| Self-service invoices / receipts | ⚠️ | ✅ PDF receipts: `GET /admin/payments/{payment}/receipt` and the super-admin equivalent (`ReceiptController`, `receipt.blade.php`), plus `PaymentReceiptNotification`. ❌ No invoice (pre-payment) document, no tax/VAT lines. |
| Usage metering & soft limits | ⚠️ | `SubscriptionService::usage()` is returned by `GET /admin/billing` and rendered on the billing page, so admins can *see* usage vs limits. No warning notification at 80/90 %; `assertCanCreate()` is still the only enforcement. |
| School offboarding | ❌ | `POST /super-admin/schools/{school}/status` can set `suspended`, but there is no export or purge (see B11). |
| Status page / maintenance mode / per-tenant flag rollout | ❌ | Feature flags exist only as plan-level lists (`synapse.features` incl. `advanced_analytics`, `ai_assistant`; `SubscriptionPlan::hasFeature()`), not per tenant. No maintenance mode beyond Laravel's `down`. |

---

## G. Delivered since the original audit that the audit did not ask for

Listed so the roadmap below is read against the real surface area, not the Aug-28 one.

- **Homework, lessons, quizzes, messaging, events, calendar, analytics, at-risk register** — README
  Phase 5.1–5.6.
- **Document-request type classifier and triage queue** (`DocumentTypeService`, README 6.1).
- **Evidence-based report-card comments** behind a `CommentWriter` contract with a deterministic default
  and optional HTTP/LLM provider (`Services/Ai/*CommentWriter`, README 6.2).
- **FR/EN announcement drafting** (`Services/Ai/*AnnouncementDrafter`, README 7.1).
- **Student AI study assistant** (`Services/Ai/StudentTutor`, `POST /student/assistant/chat`, throttled,
  receives no school records — README Phase 9).
- **Tenant scope fails closed** and `withoutTenant()` refuses to run inside an HTTP request (README 8.3).
- **Mock adapter role guards** on all admin handlers (README 8.2).

These add ~90 backend files and ~70 front-end files that the original audit never saw — and none of
them are covered by CI (B6).

---

## F. Suggested build order (revised)

Revision-1 "Phase 5 — Make it real" is done apart from CI. The remaining work is re-cut below by
value-per-week, in the numbering the README already uses (Phases 1–9 exist; next is 10).

**Phase 10 — Ship-safety (1–2 weeks)** — everything here is cheap and de-risks the rest
1. GitHub Actions: `composer test` + `npm run build` + `npm run lint` on every PR; Postman collection
   regenerated in CI (B6, B10, A8).
2. `Dockerfile` + `docker-compose.yml` (app, MySQL, queue worker, scheduler); document
   `queue:work` and `schedule:run`; Sentry (B4, B7).
3. Teacher double-booking check in `TimetableService` — one extra query, same pattern as the class
   check (C4).
4. Token TTL in `config/sanctum.php`; "MOCK MODE" banner in the app shell (A3, A8).

**Phase 11 — Money (3–4 weeks)** — the only remaining "fake" component, and the gate for fees
5. MTN MoMo Collections + Orange Money with signed webhooks, idempotent references, pending → settled
   polling, refunds; `PaymentResource` already exists (A2).
6. Grace period + failed-payment retry sequence in `SweepSubscriptions`; usage warnings at 80/90 %
   via a new `UsageThresholdNotification` (E).
7. Invoice PDF with tax fields, reusing `receipt.blade.php` layout (E).

**Phase 12 — Parents & fees (4–5 weeks)** — the two #1/#2 product gaps, in dependency order
8. `parent` role, `guardians` + `guardian_student` tables, read-only portal over existing grade /
   attendance / document / homework / message services, sibling switcher; absence alert by SMS on
   attendance write (C1, C5).
9. Fees module: fee structures, invoices, installments, MoMo/OM collection (from Phase 11), receipts,
   arrears report, optional report-card hold (C2).

**Phase 13 — Academic completeness (3 weeks)**
10. Student lifecycle statuses + year-rollover wizard (promote / repeat / graduate / transfer) (C3).
11. Report card: subject coefficients, class avg/min/max, conduct mark, FR and EN templates selected by
    school locale; GCE letter-grade scale option (C3).
12. Absence justification workflow (student/parent submits, admin approves → `excused`) (C5).
13. `rooms` entity + room clash check; exam invigilators (C4, C3).

**Phase 14 — Reach & scale (3 weeks)**
14. `react-i18next` with FR + EN bundles, per-user `locale` already stored (D1).
15. CSV export on every paginated list (server-side, chunked — `HandlesPagination` explicitly leaves
    this to exports) and `@media print` styles (D2).
16. Cache layer: per-tenant aggregate cache with invalidation on grade/attendance writes; DB-side
    ranking (B5). S3-compatible storage for documents, attachments and logos (B9).
17. Soft deletes + school export/purge commands (B11, E offboarding).
18. Impersonation with audit trail; MRR/ARR/churn from `subscriptions` history (E).

**Later** — PWA/offline attendance (D7), onboarding checklist (D6), global search and bulk actions
(D3), discipline/health/ID cards (C6), announcement read receipts (C7), optional modules (C8).
