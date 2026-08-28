# SYNAPSE — Gap Analysis & Improvement Roadmap

Audit date: 2026-08-28. Based on a read of `backend/app` (197 PHP files), `backend/routes/api.php`,
and `frontend/src` (155 files). Every item below is a **real** gap found in the code, not a guess.

---

## A. Things that are currently fake / non-functional (fix first)

| # | Gap | Evidence | What "done" looks like |
|---|-----|----------|------------------------|
| A1 | **Documents are not PDFs.** `DocumentService::generateForRequest()` writes a plain-text string, names it `*.pdf` and stores `mime_type: application/pdf`. Students download a broken file. | `app/Services/DocumentService.php` (comment says "signed text placeholder") | Real renderer (`barryvdh/laravel-dompdf`), school letterhead + logo, QR/verification code, signature block, public `/verify/{ref}` endpoint. |
| A2 | **No real payment collection.** `MtnMobileMoneyGateway`, `OrangeMoneyGateway` and `CardGateway` all `throw new RuntimeException(...)`. Only `MockPaymentGateway` works. | `app/Services/Payments/*` | MTN MoMo Collection API + Orange Money API + Stripe/Flutterwave; async callbacks/webhooks, idempotency keys, `pending → succeeded/failed` polling, retries, receipts, refunds. |
| A3 | **Auth is login/logout only.** No forgot-password, no reset, no email verification, no change-password, no profile edit, no 2FA, no session/device list, no token expiry. | `app/Http/Controllers/Api/AuthController.php` | Password reset (mail + token), forced first-login password change (imports default everyone to `password123`!), 2FA for admin/super-admin, token TTL + revoke-all. |
| A4 | **Notifications never leave the database.** `NotificationService` only inserts rows. `app/Jobs`, `app/Mail`, `app/Notifications` do not exist; `QUEUE_CONNECTION=database` but nothing is queued; `MAIL_MAILER=log`. | `app/Services/NotificationService.php` | Laravel Notification classes with `mail` + `database` + **SMS/WhatsApp** channels (critical in CM), queued jobs, a running worker, digest emails. |
| A5 | **`sendToRole()` is not tenant-scoped in intent** — it relies on the global scope; a super-admin context or a queued job without tenant context would blast every school. | `NotificationService::sendToRole()` | Explicit `where('school_id', …)`, plus tests. |
| A6 | **Default passwords in bulk import.** `ImportService` falls back to `password123` and nothing forces a change. | `app/Services/ImportService.php` | Random temp password, emailed/SMS'd, `must_change_password` flag enforced by middleware. |
| A7 | **Audit logs are write-only.** `AuditService` + `audit_logs` table exist, but there is no API route and no UI to read them. | `routes/api.php` (no audit route) | Admin + super-admin audit trail screens with filters and export. |
| A8 | **Mock adapter can mask a broken backend.** `frontend/src/services/mockAdapter.js` / `mockDb.js` reimplement the API in the browser. | `VITE_USE_MOCK` | Keep it, but add a visible "MOCK MODE" banner and a CI check that the real API satisfies the same contract. |

---

## B. Scalability / engineering gaps

1. **Zero pagination in the entire backend** — `grep "paginate("` returns **0** hits. Students, grades,
   payments, subscriptions, audit logs, notifications all return full result sets. A school with 3,000
   students will melt the admin list. → Cursor/length-aware pagination + server-side search, sort, filter.
2. **No API Resource layer** (`app/Http/Resources` doesn't exist). Eloquent models are returned raw, so
   every column (including future sensitive ones) leaks and the API contract is unstable. → `JsonResource`
   per entity, consistent envelope, `/api/v1` versioning.
3. **No scheduled tasks** — `routes/console.php` still contains only `inspire`. Nothing expires
   subscriptions, warns about trials ending, sends renewal/dunning notices, closes attendance, or backs up.
   → `app/Console/Commands` + `withSchedule()`.
4. **No queue worker / Horizon / Redis.** Imports, PDF batches, report-card generation and email blasts run
   synchronously inside HTTP requests.
5. **Caching is unused** — dashboards recompute aggregates on every request (`GradeService` ranks the whole
   class per report card, ~346 lines of in-PHP computation). → Cached per-tenant aggregates, DB-side
   aggregation, materialised rank tables.
6. **Testing is nearly absent** — 2 example tests + `TenantIsolationTest` for 197 backend files. No policy
   tests, no billing tests, no grade-computation tests, no frontend tests. **No CI** (`.github` missing).
7. **No containerisation / deploy story** — no `Dockerfile`, no `docker-compose.yml`, no `.env` template
   validation, no health-check endpoint, no error monitoring (Sentry), no structured logging.
8. **No rate limiting per tenant/role**, only Laravel's default `throttle:api`. Login has no lockout.
9. **File storage is local `public` disk**; the `expand_school_logo_column` migration suggests logos are
   stored as base64 in MySQL. → S3-compatible storage, signed temporary URLs, size/mime validation.
10. **No OpenAPI/Swagger documentation** or Postman collection for a platform with ~120 endpoints.
11. **No soft deletes / restore / archiving** on academic data, and no per-tenant data export or deletion
    (needed for offboarding and data-protection compliance).

---

## C. Missing school functionality (biggest product wins)

### C1. Parent / guardian portal — **the #1 gap**
There is no `parent` role (`User::ROLE_*` covers super_admin, admin, teacher, student). In an African
school market, parents are the payers and the decision makers. Needs: guardian accounts linked to one or
more students (siblings), read access to grades, attendance, report cards, discipline, fees and invoices,
absence alerts by SMS, and teacher–parent messaging.

### C2. School fees / tuition module — **the #2 gap**
The platform bills *schools* for SaaS, but a school cannot bill *students*. This is the feature schools
will actually pay for. Needs: fee structures per class/year, one-off + installment schedules, invoices,
MoMo/OM collection with receipts, arrears and collection-rate reports, partial payments, discounts and
scholarships, and an optional policy that blocks report-card download when fees are unpaid.

### C3. Academic depth
- **No promotion / end-of-year lifecycle**: no repeat/promote/graduate/transfer/withdraw status, no
  year rollover wizard, no alumni archive. Every year currently starts by hand.
- **No bulk report-card generation** for a whole class, and no PDF export or print stylesheet.
- **Report cards lack** teacher appreciations/comments, conduct/discipline marks, class average/min/max,
  subject coefficients presentation, and the Cameroon bilingual formats (Francophone /20 with
  *coefficients* & *mentions*, Anglophone GCE letter grades).
- **No exam scheduling**: exams exist as records, but there is no timetable, room/seat allocation, invigilator
  assignment or results-publication workflow.
- **No homework/assignments & submissions**, no lesson resources/library, no syllabus/scheme-of-work tracking.

### C4. Timetable
`TimetableService` has **no conflict detection** — the same teacher can be booked in two classes at the same
hour, and there is no `rooms` entity at all. Needs: clash validation (teacher, class, room), room management,
period templates, teacher workload limits, and an auto-generator or at least a drag-and-drop builder.

### C5. Attendance
Exists, but there is no absence-justification workflow, no automatic parent alert on absence, no
per-period (per-subject) attendance, no late/excused nuance beyond the current statuses, no monthly
attendance report or truancy analytics.

### C6. Discipline, health & student records
No discipline/incident register, no sanctions, no merit points, no health/medical record, no emergency
contacts, no student photos or printable ID cards / QR badges.

### C7. Communication
Announcements exist, but there is no direct messaging (teacher↔parent, admin↔staff), no SMS campaigns, no
event calendar, no PTA meeting notices, no read receipts on announcements.

### C8. Optional revenue modules
Staff HR (contracts, leave, payroll), library, transport/bus routes, cafeteria, inventory, hostel.

---

## D. UX / front-end gaps

1. **No i18n** — zero translation infrastructure, English-only, in a **bilingual (FR/EN) country**. This alone
   blocks half the Cameroonian market. → `react-i18next`, FR + EN, per-school default locale.
2. **No exports** — no CSV/Excel/PDF buttons anywhere (grades, students, attendance, payments), no print
   stylesheet for report cards and transcripts.
3. **No global search** and no bulk actions (bulk enroll, bulk promote, bulk delete, bulk message).
4. **Import UX**: no downloadable CSV template, no column mapping, no dry-run preview before committing.
5. **No charts/analytics** even though an `advanced_analytics` feature flag exists in `config/synapse.php`:
   pass rates, subject performance, attendance trends, fee-collection curves, teacher grade-submission
   completion.
6. **No admin onboarding checklist** ("create year → classes → subjects → teachers → students → assignments")
   — a brand-new school lands on an empty dashboard.
7. **Mobile & offline**: teachers take attendance on phones on flaky networks. No PWA, no offline queue,
   no low-data mode, no dark mode, no accessibility pass.
8. **No optimistic UI / skeletons / toast system consistency**, no confirm dialogs on destructive actions
   (several managers delete straight away).
9. **No user profile page** (change avatar, password, notification preferences, language).

---

## E. SaaS / super-admin gaps

- No **impersonation** ("log in as this school admin") for support — currently support is blind.
- No **MRR / ARR / churn / trial-conversion** metrics; the super-admin dashboard is counts only.
- No **dunning**: nothing chases a failed or overdue payment; `EnforceSubscription` just locks the school out.
- No **coupons/discounts**, no annual-vs-monthly pricing, no per-student pricing tiers, no proration on upgrade.
- No **self-service invoices/receipts** for schools (PDF, tax/VAT fields).
- No **usage metering & soft limits** with warnings before the hard `assertCanCreate()` block.
- No **school offboarding**: suspend → export data → delete, with retention window.
- No **status page / maintenance mode / feature-flag rollout** per tenant.

---

## F. Suggested build order

**Phase 5 — Make it real (2–3 weeks)**
1. Real PDF engine → report cards, transcripts, certificates, receipts (A1)
2. Pagination + search + API Resources + `/api/v1` (B1, B2)
3. Password reset, forced password change, profile page (A3, A6)
4. Queue worker + mail/SMS notification channels + scheduler (A4, B3, B4)
5. Audit-log UI (A7) and a test suite + CI for all of the above (B6)

**Phase 6 — Parents & money (3–4 weeks)**
6. Parent/guardian role + portal + sibling switching (C1)
7. School fees module: structures, invoices, installments, receipts, arrears (C2)
8. Live MTN MoMo + Orange Money integration with webhooks — powers both SaaS billing and school fees (A2)

**Phase 7 — Academic completeness (3–4 weeks)**
9. Year rollover / promotion / student lifecycle (C3)
10. Bulk report cards + Cameroon FR/EN formats + teacher comments & conduct (C3)
11. Timetable conflict detection + rooms (C4)
12. Absence justification + automatic parent alerts (C5)

**Phase 8 — Reach & polish (2–3 weeks)**
13. Full FR/EN i18n (D1)
14. Exports everywhere + print styles (D2)
15. Analytics dashboards with charts (D5)
16. PWA/offline attendance, mobile polish (D7)
17. Super-admin impersonation, MRR metrics, dunning, invoices (E)
