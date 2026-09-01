<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SYNAPSE platform configuration
    |--------------------------------------------------------------------------
    |
    | Central place for SaaS settings so values are never hard-coded across
    | the codebase.
    |
    */

    // Free-trial length for newly onboarded schools.
    'trial_days' => env('SYNAPSE_TRIAL_DAYS', 14),

    // Default billing currency (Cameroon-first).
    'currency' => env('SYNAPSE_CURRENCY', 'XAF'),

    // Available payment providers (mock is dev-only).
    'payment_providers' => ['mock', 'mtn_momo', 'orange_money', 'card'],

    // Default country for generated documents.
    'country' => env('SYNAPSE_COUNTRY', 'Cameroon'),

    /*
    |--------------------------------------------------------------------------
    | Grading
    |--------------------------------------------------------------------------
    |
    | `scale` is the maximum mark (20 in the Francophone system, 100 for the
    | Anglophone one). Mentions are matched from the highest threshold down.
    |
    */
    'grading' => [
        'scale' => env('SYNAPSE_GRADE_SCALE', 20),
        'pass_mark' => env('SYNAPSE_PASS_MARK', 10),
        'mentions' => [
            ['min' => 18, 'label' => 'Excellent'],
            ['min' => 16, 'label' => 'Very Good'],
            ['min' => 14, 'label' => 'Good'],
            ['min' => 12, 'label' => 'Fairly Good'],
            ['min' => 10, 'label' => 'Average'],
            ['min' => 0, 'label' => 'Insufficient'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */
    'documents' => [
        // Private disk: generated PDFs must never be publicly listable.
        'disk' => env('SYNAPSE_DOCUMENT_DISK', 'local'),

        // Public URL pattern used on the PDF footer for authenticity checks.
        'verification_url' => env('SYNAPSE_VERIFICATION_URL', env('APP_URL', 'http://localhost').'/verify'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | `log` writes to the application log (default, safe for dev). Configure a
    | provider below to send real messages.
    |
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'from' => env('SMS_FROM', 'SYNAPSE'),

        // Used to expand local numbers (6XXXXXXXX) into E.164 (+2376XXXXXXXX).
        'country_code' => env('SMS_COUNTRY_CODE', '237'),
    ],

    // Pagination defaults applied by the HandlesPagination concern.
    'pagination' => [
        'per_page' => env('SYNAPSE_PER_PAGE', 15),
        'max_per_page' => env('SYNAPSE_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments (homework briefs, student submissions, course materials)
    |--------------------------------------------------------------------------
    |
    | Stored on a private disk: uploaded files must never be publicly listable
    | or guessable. Access is authorised per request by AttachmentService.
    |
    */
    'attachments' => [
        'disk' => env('SYNAPSE_ATTACHMENT_DISK', 'local'),

        // Bytes. 10 MB is generous for a PDF/Word brief and keeps a shared
        // server from filling up on a school's first week.
        'max_size' => (int) env('SYNAPSE_ATTACHMENT_MAX_KB', 10240) * 1024,

        // Word + PDF + plain documents. Executables are never accepted.
        'mimes' => ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'png', 'jpg', 'jpeg'],

        'max_per_record' => (int) env('SYNAPSE_ATTACHMENT_MAX_FILES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quizzes (auto-marked)
    |--------------------------------------------------------------------------
    */
    'quizzes' => [
        // How many attempts one student gets. 1 is the honest setting for a
        // marked assessment; raise it for practice quizzes.
        'attempts' => (int) env('SYNAPSE_QUIZ_ATTEMPTS', 1),

        // Question count bounds, so a teacher cannot publish a 200-question
        // paper that no student finishes inside the time limit.
        'min_questions' => (int) env('SYNAPSE_QUIZ_MIN_QUESTIONS', 1),
        'max_questions' => (int) env('SYNAPSE_QUIZ_MAX_QUESTIONS', 50),

        // Options per multiple-choice question.
        'min_options' => 2,
        'max_options' => 6,

        // Minutes. A timed quiz is closed once the limit elapses from the
        // student's own start, so the deadline cannot be beaten by idling.
        'min_time_limit' => 1,
        'max_time_limit' => 300,

        // Whether a student sees per-question correctness straight away.
        // Off by default: results appear once the teacher releases them.
        'show_answers_immediately' => (bool) env('SYNAPSE_QUIZ_SHOW_ANSWERS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | At-risk students
    |--------------------------------------------------------------------------
    |
    | Thresholds for the pastoral register. A student is flagged when any one
    | signal fires, and each signal reports *why* in words — a bare score tells
    | a form teacher nothing about what to do on Monday morning.
    |
    | Everything here is a computed view over records that already exist: no
    | risk flag is stored, so a register can never go stale because someone
    | entered a grade after it was generated.
    |
    */
    'at_risk' => [
        // A term average below this is a warning; `critical_margin` below it is
        // critical. Defaults to the grading pass mark.
        'average' => env('SYNAPSE_ATRISK_AVERAGE', null),

        // How far below the average threshold counts as critical rather than
        // merely concerning.
        'critical_margin' => (float) env('SYNAPSE_ATRISK_CRITICAL_MARGIN', 2.0),

        // Number of subjects below the pass mark that warrants attention.
        'failing_subjects' => (int) env('SYNAPSE_ATRISK_FAILING_SUBJECTS', 2),

        // Semester-over-semester drop, on the 0–20 scale, that counts as
        // declining. A student slipping from 14 to 12 is more actionable than
        // one who has always sat at 12.
        'decline_points' => (float) env('SYNAPSE_ATRISK_DECLINE_POINTS', 2.0),

        // Published assignments past their deadline with nothing submitted.
        'missing_homework' => (int) env('SYNAPSE_ATRISK_MISSING_HOMEWORK', 2),

        // Percent of published assignments handed in.
        'submission_rate' => (float) env('SYNAPSE_ATRISK_SUBMISSION_RATE', 60.0),

        // Attendance rate. Excused absences sit outside both numerator and
        // denominator: a medical absence is not a warning sign.
        'attendance_rate' => (float) env('SYNAPSE_ATRISK_ATTENDANCE_RATE', 80.0),

        // Mean quiz score as a percentage of the marks available.
        'quiz_average' => (float) env('SYNAPSE_ATRISK_QUIZ_AVERAGE', 50.0),
    ],

    // Days before expiry that a school starts receiving renewal reminders.
    'renewal_reminder_days' => [14, 7, 3, 1],

    /*
    | Report-card comment drafting.
    |
    | The deterministic writer is always available — it is offline, costs
    | nothing and is what makes a report card say something specific. The
    | `ai_assistant` plan feature governs only whether a school may use an
    | external model to phrase those facts, so the upgrade path is real without
    | holding existing behaviour hostage.
    */
    'comments' => [
        'max_length' => (int) env('SYNAPSE_COMMENT_MAX_LENGTH', 400),

        // Subjects whose average falls below the pass mark are named in the
        // comment, up to this many, so the text stays readable.
        'max_named_subjects' => (int) env('SYNAPSE_COMMENT_MAX_SUBJECTS', 3),
    ],

    // Tenant feature flags exposed through subscription plans.
    'features' => [
        'basic_academics',
        'report_cards',
        'document_management',
        'notifications',
        'custom_branding',
        'advanced_analytics',
        'ai_assistant',
    ],

];
