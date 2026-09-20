# SYNAPSE — UML model (as built, September 2026)

UML 2 documentation of the SYNAPSE platform **as it exists in this repository today**
(`backend/` Laravel 13 API, `frontend/` React 19 SPA). Every element in the diagrams maps to
real code: models in `backend/app/Models`, routes in `backend/routes/api.php`, services in
`backend/app/Services`, pages in `frontend/src/pages`.

The diagrams follow the conventions of the course *UML level 2* (Mr MESSIO) and the look of the
Visual Paradigm examples kept in `../uml example diagrams/` (white shapes, amber outlines, stick-figure
actors, `<<include>>` to *Authenticate*, swim-lane activity diagrams, numbered sequence messages, 3-D
deployment nodes).

```
uml/synapse/
├── README.md                    ← this index
├── USE_CASE_DESCRIPTIONS.md     ← textual descriptions of the 6 detailed use cases (course template)
├── render.sh                    ← re-renders src/*.puml → png/ (and svg/ with --svg)
├── src/                         ← editable PlantUML sources (+ _style.iuml shared theme)
└── png/                         ← rendered diagrams
```

## Diagram index

### Structure

| File | Diagram | What it shows |
|------|---------|---------------|
| `01-use-case-global` | Use case (global) | All roles (Guest, Student, Teacher, School Administrator, Super Administrator → *Authenticated User*), the external systems (SMTP, SMS, payment gateway, AI provider) and the main use cases per role. |
| `01a-use-case-student` | Use case (student) | Student portal with explicit `<<include>>` *Authenticate* on every use case, `<<extend>>` for quiz review / PDF download. |
| `01b-use-case-teacher` | Use case (teacher) | Teacher portal: gradebook, attendance, homework, materials, quizzes, report-card comments (+ AI draft), analytics; *Verify teaching assignment* included by class-bound use cases. |
| `01c-use-case-admin` | Use case (school administration) | Structure, people, CSV import (dry-run preview), timetable, exams, document requests, report cards, announcements (+ AI draft), billing / payment, settings, audit. |
| `01d-use-case-platform` | Use case (public & platform) | Guest onboarding (plan → admin account → 14-day trial), document verification, password reset, super-admin console, scheduled jobs. |
| `02-class-diagram` | Class diagram | **All 37 Eloquent models** in one diagram, grouped in packages (Tenancy & Billing, People & Access, Academic Structure, Assessment, Documents, Learning, Communication) with attributes typed from the migrations, key methods, multiplicities, composition/aggregation and the `HasAttachments` interface. |
| `03-package-diagram` | Package diagram | Front-end (pages/routes/components/context/hooks/services) and back-end (Http, Services, Models, Policies, Notifications, Jobs, Contracts, Support) packages with `<<import>>` / `<<access>>` / `<<use>>` dependencies. |
| `04-component-diagram` | Component diagram | SPA and API components with ports and provided/required interfaces (REST/JSON, IDatabase, IMail, ISms, IPaymentGateway, IAiCompletion), queue worker, storage. |
| `05-deployment-diagram` | Deployment diagram | Browser / phone → Nginx + PHP-FPM (Laravel artifacts) + PHP CLI (queue, scheduler) → MySQL, local disk, third-party services. |

### Behaviour — the six detailed use cases

| Use case | Activity diagram | Sequence diagram | Textual description |
|----------|------------------|------------------|---------------------|
| UC-01 Authenticate | `10-activity-authenticate` | `20-sequence-authenticate` | `USE_CASE_DESCRIPTIONS.md` § UC-01 |
| UC-02 Enter grades & generate report card | `11-activity-enter-grades` | `21-sequence-enter-grades` | § UC-02 |
| UC-03 Request an administrative document | `12-activity-request-document` | `22-sequence-request-document` | § UC-03 |
| UC-04 Onboard a school & pay a subscription | `13-activity-onboard-subscribe` | `23-sequence-onboard-subscribe` | § UC-04 |
| UC-05 Take attendance | `14-activity-take-attendance` | `24-sequence-take-attendance` | § UC-05 |
| UC-06 Publish, submit & grade homework | `15-activity-homework` | `25-sequence-homework` | § UC-06 |

Activity diagrams use swim-lanes *Actor | Browser (React SPA) | API (Laravel) | DBMS* (plus *Queue worker*
or *Payment gateway* when relevant). Sequence diagrams use lifelines *Actor : Browser : API : Service(s) : DBMS
: external system*, numbered messages, `alt` / `opt` / `loop` / `ref` fragments, activations and dashed replies.
Error paths (HTTP 403 / 409 / 422 / 429) are the ones the code really returns.

### State machines

| File | Lifecycle |
|------|-----------|
| `30-state-document-request` | `requests.status`: submitted → under_review → approved → ready / rejected, with the actions performed on each transition (notifications, `resolved_at`, PDF generation guard). |
| `31-state-subscription` | `subscriptions.status` ↔ `schools.status`: trial → active → expired / suspended / cancelled, renewals, sweep command, super-admin actions. |
| `32-state-homework` | `HomeworkAssignment` (draft ↔ published → closed) and `HomeworkSubmission` (not submitted → submitted ⟲ → graded) with the refused transitions. |

## Reading notes / modelling decisions

* **Roles are not subclasses.** `User.role` is an enumeration and a user optionally owns a `Student` or
  `Teacher` profile (1 — 0..1). Actor generalisation is only used on the use case diagrams.
* **Tenant scoping is implicit.** Every class outside *Tenancy & Billing* uses the `BelongsToSchool` trait
  (`TenantScope`); the *School 1 — \* X* composition is stated once in the class-diagram note instead of
  being drawn 30 times.
* **Foreign keys appear as attributes** (`*_id`), like in the reference example; only the structurally important
  associations are drawn so the 37-class diagram stays readable.
* **Secondary actors** are the systems SYNAPSE calls: SMTP mail server, SMS gateway, payment gateway
  (MTN MoMo / Orange Money / card, mock in development), OpenAI-compatible AI provider.
* Anything that exists only as a constant but is not produced by the code (e.g. `past_due`, `is_late`)
  is flagged as *reserved* in the diagram notes rather than modelled as live behaviour.

## Re-rendering

```bash
cd uml/synapse
./render.sh          # PNG into png/
./render.sh --svg    # PNG + SVG
```

Requirements: Java 11+ and `plantuml.jar` (1.2024+; set `PLANTUML_JAR` if it is not in `~/.tools/`).
Graphviz is **not** needed — every non-sequence diagram declares `!pragma layout smetana`.
Sources can also be pasted into any PlantUML editor (IntelliJ / VS Code plug-ins, plantuml.com) — keep
`_style.iuml` next to them.
