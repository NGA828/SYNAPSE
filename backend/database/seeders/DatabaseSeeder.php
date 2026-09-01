<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Conversation;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\Lesson;
use App\Models\Message;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Notification;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the SYNAPSE SaaS platform: subscription plans, a super admin and
     * three fully isolated demo schools.
     *
     * Demo credentials (local development only, all use password123):
     *   superadmin@synapse.test          → platform super admin
     *   admin@synapse.test               → School A admin (AICS Cameroon)
     *   teacher@synapse.test             → School A teacher (Mr. David)
     *   student@synapse.test             → School A student (John Doe)
     *   admin.saintalbert@synapse.test   → School B admin
     *   teacher.saintalbert@synapse.test → School B teacher
     *   student.saintalbert@synapse.test → School B student
     */
    public function run(): void
    {
        $plans = $this->seedPlans();
        $this->seedSuperAdmin();

        $aics = $this->seedSchoolAics($plans['professional']);
        $this->seedSchoolSaintAlbert($plans['starter']);
        $this->seedSchoolDemo($plans['starter']);

        // Ensure the AICS data also feeds the original single-school demo.
        $this->command?->info('SYNAPSE SaaS seed complete. Tenant isolation enforced per school.');
    }

    /**
     * @return array{starter: SubscriptionPlan, professional: SubscriptionPlan, enterprise: SubscriptionPlan}
     */
    private function seedPlans(): array
    {
        $starter = SubscriptionPlan::firstOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'For small schools getting started.',
                'price' => 15000,
                'billing_interval' => 'monthly',
                'currency' => 'XAF',
                'max_students' => 500,
                'max_teachers' => 20,
                'max_classes' => 30,
                'features' => ['basic_academics', 'report_cards', 'notifications'],
                'status' => 'active',
            ],
        );

        $professional = SubscriptionPlan::firstOrCreate(
            ['slug' => 'professional'],
            [
                'name' => 'Professional',
                'description' => 'For growing schools with full document management.',
                'price' => 25000,
                'billing_interval' => 'monthly',
                'currency' => 'XAF',
                'max_students' => 2000,
                'max_teachers' => 100,
                'max_classes' => 100,
                'features' => ['basic_academics', 'report_cards', 'document_management', 'notifications', 'custom_branding'],
                'status' => 'active',
            ],
        );

        $enterprise = SubscriptionPlan::firstOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'description' => 'Custom limits and advanced analytics.',
                'price' => 60000,
                'billing_interval' => 'monthly',
                'currency' => 'XAF',
                'max_students' => null,
                'max_teachers' => null,
                'max_classes' => null,
                'features' => ['basic_academics', 'report_cards', 'document_management', 'notifications', 'custom_branding', 'advanced_analytics'],
                'status' => 'active',
            ],
        );

        return [
            'starter' => $starter,
            'professional' => $professional,
            'enterprise' => $enterprise,
        ];
    }

    private function seedSuperAdmin(): User
    {
        return User::updateOrCreate(['email' => 'superadmin@synapse.test'], [
            'school_id' => null,
            'name' => 'Platform Super Admin',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_SUPER_ADMIN,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }

    private function seedSchoolAics(SubscriptionPlan $plan): School
    {
        $school = School::firstOrCreate(['slug' => 'aics'], [
            'name' => 'AICS Cameroon',
            'code' => 'AICS',
            'email' => 'contact@aics.cm',
            'phone' => '+237 600 000 001',
            'address' => 'Yaoundé, Centre',
            'status' => School::STATUS_ACTIVE,
            'timezone' => 'Africa/Douala',
            'primary_color' => '#4f46e5',
        ]);

        // Subscription (active, professional)
        Subscription::firstOrCreate(
            ['school_id' => $school->id, 'status' => Subscription::STATUS_ACTIVE],
            [
                'plan_id' => $plan->id,
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->addDays(335)->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => $plan->price,
                'currency' => $plan->currency,
            ],
        );

        // Users
        $chen = $this->user($school, 'Mrs. Chen', 'admin@synapse.test', User::ROLE_ADMIN);
        $david = $this->user($school, 'Mr. David', 'teacher@synapse.test', User::ROLE_TEACHER);
        $sarah = $this->user($school, 'Mrs. Sarah', 'sarah@synapse.test', User::ROLE_TEACHER);
        $felix = $this->user($school, 'Mr. Felix', 'felix@synapse.test', User::ROLE_TEACHER);
        $john = $this->user($school, 'John Doe', 'student@synapse.test', User::ROLE_STUDENT);
        $mary = $this->user($school, 'Mary Smith', 'mary@synapse.test', User::ROLE_STUDENT);
        $peter = $this->user($school, 'Peter Paul', 'peter@synapse.test', User::ROLE_STUDENT);

        // Profiles
        $davidT = $this->teacher($school, $david, 'TCH-001');
        $sarahT = $this->teacher($school, $sarah, 'TCH-002');
        $felixT = $this->teacher($school, $felix, 'TCH-003');
        $johnS = $this->student($school, $john, 'ST2026045');
        $maryS = $this->student($school, $mary, 'ST2026031');
        $peterS = $this->student($school, $peter, 'ST2026028');

        // Academic structure
        $prev = $this->year($school, '2025/2026', false);
        $current = $this->year($school, '2026/2027', true);
        $l1a = $this->class($school, 'Level 1A');
        $l1b = $this->class($school, 'Level 1B');
        $l2a = $this->class($school, 'Level 2A');
        $l2b = $this->class($school, 'Level 2B');
        $l3a = $this->class($school, 'Level 3A');
        $english = $this->subject($school, 'English', 'ENG');
        $math = $this->subject($school, 'Mathematics', 'MAT');
        $physics = $this->subject($school, 'Physics', 'PHY');
        $cs = $this->subject($school, 'Computer Science', 'CSC');
        $database = $this->subject($school, 'Database', 'DB');
        $networking = $this->subject($school, 'Networking', 'NET');
        $history = $this->subject($school, 'History', 'HIS');
        $programming = $this->subject($school, 'Programming', 'PRG');

        // Enrollments
        $this->enroll($school, $johnS, $l2a, $prev);
        $this->enroll($school, $johnS, $l3a, $current);
        $this->enroll($school, $maryS, $l3a, $current);
        $this->enroll($school, $peterS, $l3a, $current);

        // Assignments
        $this->assign($school, $davidT, $english, $l3a, $current);
        $this->assign($school, $davidT, $english, $l2a, $current);
        $this->assign($school, $davidT, $history, $l3a, $current);
        $this->assign($school, $sarahT, $math, $l3a, $current);
        $this->assign($school, $sarahT, $math, $l2a, $current);
        $this->assign($school, $sarahT, $math, $l1b, $current);
        $this->assign($school, $felixT, $database, $l3a, $current);
        $this->assign($school, $felixT, $networking, $l3a, $current);

        // Semesters (grading periods)
        $semester1 = $this->semester($school, $current, 'Semester 1', 1, true);
        $this->semester($school, $current, 'Semester 2', 2, false);

        // Grade components (weighted grading, school-wide default)
        $assignments = $this->component($school, null, 'Assignments', 30, 1);
        $quizzes = $this->component($school, null, 'Quizzes', 20, 2);
        $midterm = $this->component($school, null, 'Midterm', 20, 3);
        $examC = $this->component($school, null, 'Exam', 30, 4);

        // Grades (assigned to Semester 1)
        $this->grade($school, $johnS, $english, $l3a, $current, $davidT, 15, 16, 17, $semester1);
        $this->grade($school, $johnS, $math, $l3a, $current, $sarahT, 14, 16, 16, $semester1);
        $this->grade($school, $johnS, $database, $l3a, $current, $felixT, 16, 17, 18, $semester1);
        $this->grade($school, $johnS, $networking, $l3a, $current, $felixT, 13, 15, 15, $semester1);
        $this->grade($school, $maryS, $english, $l3a, $current, $davidT, 14, 15, 16, $semester1);
        $this->grade($school, $peterS, $english, $l3a, $current, $davidT, 12, 14, 15, $semester1);

        // Timetable (Level 3A)
        $slots = [
            [1, '08:00', '10:00', $english],
            [1, '10:00', '12:00', $networking],
            [1, '14:00', '16:00', $math],
            [2, '08:00', '10:00', $database],
            [2, '10:00', '12:00', $english],
            [2, '14:00', '16:00', $programming],
            [3, '08:00', '10:00', $math],
            [3, '10:00', '12:00', $programming],
            [3, '14:00', '16:00', $english],
        ];
        foreach ($slots as [$day, $start, $end, $subject]) {
            $this->slot($school, $l3a, $current, $subject, $day, $start, $end);
        }

        /*
         * A second class, so a teacher holding both (Mr. David teaches English
         * to 3A and 2A) sees every period on their calendar rather than only
         * the first class they were assigned.
         */
        foreach ([[1, '12:00', '13:00', $english], [3, '12:00', '13:00', $english]] as [$day, $start, $end, $subject]) {
            $this->slot($school, $l2a, $current, $subject, $day, $start, $end);
        }

        // Attendance (recent school days)
        $this->attendance($school, $l3a, $current, $davidT, $johnS, now()->toDateString(), Attendance::PRESENT);
        $this->attendance($school, $l3a, $current, $davidT, $maryS, now()->toDateString(), Attendance::PRESENT);
        $this->attendance($school, $l3a, $current, $davidT, $peterS, now()->toDateString(), Attendance::ABSENT);
        $this->attendance($school, $l3a, $current, $davidT, $johnS, now()->subDay()->toDateString(), Attendance::LATE);
        $this->attendance($school, $l3a, $current, $davidT, $maryS, now()->subDay()->toDateString(), Attendance::PRESENT);
        $this->attendance($school, $l3a, $current, $davidT, $peterS, now()->subDay()->toDateString(), Attendance::PRESENT);
        $this->attendance($school, $l3a, $current, $davidT, $johnS, now()->subDays(2)->toDateString(), Attendance::PRESENT);
        $this->attendance($school, $l3a, $current, $davidT, $maryS, now()->subDays(2)->toDateString(), Attendance::EXCUSED);
        $this->attendance($school, $l3a, $current, $davidT, $peterS, now()->subDays(2)->toDateString(), Attendance::PRESENT);

        // Requests + documents + announcements + notifications
        $req = $this->request($school, $johnS, 'REQ-1045', 'Certificate of Enrollment', 'submitted');
        $transcript = $this->request($school, $johnS, 'REQ-1030', 'Transcript Request', 'ready');
        $this->request($school, $maryS, 'REQ-1044', 'Certificate of Enrollment', 'under_review');
        $this->request($school, $peterS, 'REQ-1043', 'Certificate of Enrollment', 'approved');

        Document::firstOrCreate(
            ['request_id' => $transcript->id],
            [
                'school_id' => $school->id,
                'student_id' => $johnS->id,
                'title' => 'Transcript Request',
                'file_name' => 'transcript-req-1030.pdf',
                'mime_type' => 'application/pdf',
                'size' => 1840,
                'disk' => 'public',
                'path' => 'documents/transcript-req-1030.pdf',
            ],
        );

        $this->announcement($school, $chen, 'Exam Timetable Published', 'The first semester examination timetable has been published.', 'all');
        $this->notification($school, $john, 'Your transcript is ready', 'Your Transcript Request (REQ-1030) is ready to download.');

        // Exam sessions (Semester 1, Level 3A)
        $this->exam($school, $current, $semester1, $english, $l3a, now()->addDays(14)->toDateString(), '08:00', '10:00', 'Hall A');
        $this->exam($school, $current, $semester1, $math, $l3a, now()->addDays(15)->toDateString(), '08:00', '10:00', 'Hall A');
        $this->exam($school, $current, $semester1, $database, $l3a, now()->addDays(16)->toDateString(), '10:00', '12:00', 'Lab 2');

        // Homework: one closed + graded item, one open awaiting marks, one open
        // with nothing submitted yet, and one unpublished draft. This mirrors
        // the mock dataset so both modes demo the same lifecycle.
        $essay = $this->homework(
            $school, $davidT, $english, $l3a, $current, $semester1,
            'Essay: The Role of Technology in Education',
            'Write a 500-word argumentative essay. Use at least three examples and end with your own position.',
            now()->subDays(2),
        );

        $this->submission($school, $essay, $johnS, 'Technology has reshaped how knowledge reaches the classroom. First, digital libraries remove the cost barrier to reference material...', now()->subDays(3));
        $this->gradeSubmission($essay, $johnS, $davidT, 16.5, 'Well structured with a clear position. Develop your second example further.');
        $this->submission($school, $essay, $maryS, 'In my view technology helps students learn faster because information is available everywhere...', now()->subDay());

        $quadratics = $this->homework(
            $school, $sarahT, $math, $l3a, $current, $semester1,
            'Quadratic Equations — Exercise 4B',
            'Solve questions 1 to 10 of Exercise 4B. Show every step of your working, not only the final answer.',
            now()->addDays(3),
        );

        $this->submission($school, $quadratics, $johnS, 'Question 1: x² - 5x + 6 = 0, so (x - 2)(x - 3) = 0, giving x = 2 or x = 3.', now());

        $this->homework(
            $school, $davidT, $history, $l3a, $current, $semester1,
            'Research: Causes of the First World War',
            'Summarise the four long-term causes in your own words, one paragraph each.',
            now()->addDays(7),
        );

        $draft = $this->homework(
            $school, $davidT, $english, $l3a, $current, $semester1,
            'Comprehension: Draft Questions',
            null,
            now()->addDays(14),
        );
        $draft->update(['is_published' => false, 'published_at' => null]);

        // Course materials: two topics under English, one under History, one in
        // another class, and one draft. Mirrors the mock dataset.
        $this->lesson(
            $school, $davidT, $english, $l3a, $current, $semester1,
            'Argumentative Writing: Building a Thesis', 'Essay Writing', 1, 15,
            'How to turn an opinion into a defensible thesis, and how to support it with evidence.',
            'A thesis is a claim a reader could reasonably disagree with, plus the reason it holds. Start from your conclusion and work backwards to the strongest evidence you have. Then test it: if nobody could disagree, it is a topic, not a thesis. Revise until the sentence carries both the claim and the reason.',
            now()->subDays(6),
        );

        $this->lesson(
            $school, $davidT, $english, $l3a, $current, $semester1,
            'Citing Sources Without Plagiarising', 'Essay Writing', 2, null,
            'Quotation, paraphrase and summary — and when to use each.',
            'Quoting preserves exact wording, paraphrase restates an idea in your own words, and summary compresses several paragraphs into one. Plagiarism is not only copying: presenting a paraphrase without attribution counts too. Cite the author and year at the point of use.',
            now()->subDays(2),
        );

        $this->lesson(
            $school, $davidT, $history, $l3a, $current, $semester1,
            'Long-term Causes of the First World War', 'The World Wars', 1, null,
            'Militarism, alliances, imperialism and nationalism — the MAIN framework.',
            'Militarism meant arms races and war plans that assumed mobilisation. Alliances turned a regional dispute into a continental one. Imperialism created rivalries over territory and markets. Nationalism supplied both the will to fight and the grievances that made it feel justified.',
            now()->subDay(),
        );

        $this->lesson(
            $school, $sarahT, $math, $l3a, $current, $semester1,
            'Solving Quadratic Equations by Factorisation', 'Algebra', 1, 20,
            'Factorising ax2 + bx + c, and recognising when the method will not work.',
            'Factorisation works when the quadratic has rational roots. Move every term to one side so the equation equals zero, then find two numbers that multiply to ac and add to b. Split the middle term, factor by grouping, and set each bracket to zero.',
            now()->subDays(4),
        );

        $this->lesson(
            $school, $davidT, $english, $l2a, $current, $semester1,
            'Persuasive Speech Structure', 'Speech Writing', 1, 12,
            'Hook, argument, counter-argument and close — for Level 2A.',
            'A speech has one job: to be followed out loud. Signpost every turn, repeat the central claim three times in different words, and leave the strongest point for last.',
            now()->subDays(5),
        );

        $lessonDraft = $this->lesson(
            $school, $davidT, $english, $l3a, $current, $semester1,
            'Draft: Poetry Analysis Framework', 'Poetry', 3, null,
            null, null,
            now()->subDay(),
        );
        $lessonDraft->update(['is_published' => false, 'published_at' => null]);

        // Auto-marked quizzes: an open English paper, an open Mathematics one,
        // a closed History paper with a graded attempt, and a draft.
        $grammar = $this->quiz(
            $school, $davidT, $english, $l3a, $current, $semester1,
            'Grammar Check: Tenses and Agreement', 20, 20, now()->addDays(5),
            'Choose the one correct option for each sentence. There is no negative marking.',
        );
        $this->question($grammar, 'Choose the sentence with correct subject-verb agreement.',
            ['The list of items are on the desk.', 'The list of items is on the desk.', 'The list of items were on the desk.'], 1, 5, 1);
        $this->question($grammar, 'Which sentence uses the past perfect correctly?',
            ['She had left before we arrived.', 'She has left before we arrived.', 'She have left before we arrived.'], 0, 5, 2);
        $this->question($grammar, 'Select the correct passive form of "The teacher marked the papers."',
            ['The papers was marked by the teacher.', 'The papers were marked by the teacher.', 'The papers marked by the teacher.'], 1, 5, 3);
        $this->question($grammar, 'Which word is a subordinating conjunction?',
            ['and', 'but', 'although', 'or'], 2, 5, 4);

        $algebra = $this->quiz(
            $school, $sarahT, $math, $l3a, $current, $semester1,
            'Algebra Drill: Linear Equations', 20, 30, now()->addDays(9),
            'Work without a calculator.',
        );
        $this->question($algebra, 'Solve for x: 3x + 7 = 22', ['x = 3', 'x = 5', 'x = 7'], 1, 5, 1);
        $this->question($algebra, 'Solve for y: 2(y - 4) = 10', ['y = 3', 'y = 7', 'y = 9'], 2, 5, 2);
        $this->question($algebra, 'Which line is parallel to y = 2x + 1?',
            ['y = -2x + 1', 'y = 2x - 5', 'y = x/2 + 1'], 1, 5, 3);
        $this->question($algebra, 'If 5a - 3 = 2a + 9, then a =', ['a = 2', 'a = 3', 'a = 4'], 2, 5, 4);

        $wars = $this->quiz(
            $school, $davidT, $history, $l3a, $current, $semester1,
            'History Quiz: The World Wars', 20, null, now()->subDay(),
            null,
        );
        $this->question($wars, 'In which year did the First World War begin?', ['1912', '1914', '1916'], 1, 5, 1);
        $this->question($wars, 'The "MAIN" framework of long-term causes stands for:',
            ['Money, Arms, Industry, Nations', 'Militarism, Alliances, Imperialism, Nationalism', 'Monarchy, Army, Invasion, Negotiation'], 1, 5, 2);
        $this->question($wars, 'Which event is generally taken as the immediate trigger of the First World War?',
            ['The sinking of the Lusitania', 'The assassination at Sarajevo', 'The Treaty of Versailles'], 1, 5, 3);
        $this->question($wars, 'The Second World War ended in:', ['1943', '1944', '1945'], 2, 5, 4);

        // One completed attempt, so the results view has something to show.
        // Three of four right: 15 of 20.
        QuizAttempt::firstOrCreate(
            [
                'quiz_id' => $wars->id,
                'student_id' => $johnS->id,
                'attempt' => 1,
            ],
            [
                'school_id' => $school->id,
                'answers' => [
                    $wars->questions()->where('sequence', 1)->value('id') => 1,
                    $wars->questions()->where('sequence', 2)->value('id') => 1,
                    $wars->questions()->where('sequence', 3)->value('id') => 0,
                    $wars->questions()->where('sequence', 4)->value('id') => 2,
                ],
                'correct_count' => 3,
                'total_questions' => 4,
                'score' => 15,
                'started_at' => now()->subDays(10),
                'submitted_at' => now()->subDays(10)->addMinutes(18),
                'feedback' => 'Strong on dates. Revisit the Sarajevo trigger before the exam.',
                'is_reviewed' => true,
                'reviewed_at' => now()->subDays(9),
                'reviewed_by' => $davidT->id,
            ],
        );
        $wars->update(['is_locked' => true]);

        $quizDraft = $this->quiz(
            $school, $davidT, $english, $l3a, $current, $semester1,
            'Draft: Comprehension Skills', 20, 15, null,
            null,
        );
        $quizDraft->update(['is_published' => false, 'published_at' => null]);

        // Events — a mix of audiences, plus one draft that stays private.
        $this->event($school, $chen, 'First Semester Examinations Begin', 'Examinations run for two weeks. Arrive 30 minutes early.', Event::TYPE_EXAM, now()->addDays(7)->setTime(8, 0), now()->addDays(18)->setTime(14, 0), false, 'Hall A', Event::AUDIENCE_ALL, true);
        $this->event($school, $chen, 'Monday Assembly', 'Whole school assembly on the main field.', Event::TYPE_ASSEMBLY, now()->addDay()->setTime(7, 30), now()->addDay()->setTime(8, 15), false, 'Main Field', Event::AUDIENCE_STUDENTS, true);
        $this->event($school, $chen, 'Staff Meeting', 'Assessment moderation and the new marking policy.', Event::TYPE_MEETING, now()->addDays(3)->setTime(15, 0), now()->addDays(3)->setTime(16, 30), false, 'Staff Room', Event::AUDIENCE_TEACHERS, true);
        $this->event($school, $chen, 'Inter-house Sports Day', null, Event::TYPE_SPORTS, now()->addDays(25)->setTime(8, 0), now()->addDays(25)->setTime(16, 0), false, 'Sports Complex', Event::AUDIENCE_ALL, true);
        $this->event($school, $chen, 'Mid-term Break', 'School closed.', Event::TYPE_HOLIDAY, now()->addDays(30), null, true, null, Event::AUDIENCE_ALL, true);
        $this->event($school, $chen, 'Parent-Teacher Consultation', null, Event::TYPE_MEETING, now()->addDays(40)->setTime(9, 0), now()->addDays(40)->setTime(13, 0), false, 'Classrooms', Event::AUDIENCE_ALL, false);

        // One open thread: a student asking a teacher about a deadline.
        [$a, $b] = $david->id < $john->id ? [$david->id, $john->id] : [$john->id, $david->id];
        $conversation = Conversation::firstOrCreate([
            'school_id' => $school->id,
            'participant_a_id' => $a,
            'participant_b_id' => $b,
        ], ['last_message_at' => now()->subDay()]);

        Message::firstOrCreate([
            'conversation_id' => $conversation->id,
            'body' => 'Good afternoon sir. Is the essay due this Friday or next Monday?',
        ], [
            'school_id' => $school->id,
            'sender_id' => $john->id,
            'read_at' => now()->subDay(),
        ]);
        Message::firstOrCreate([
            'conversation_id' => $conversation->id,
            'body' => 'This Friday. Submit it through the homework page so it is recorded.',
        ], [
            'school_id' => $school->id,
            'sender_id' => $david->id,
            'read_at' => null,
        ]);

        return $school;
    }

    private function event(
        School $school,
        User $author,
        string $title,
        ?string $description,
        string $type,
        \Illuminate\Support\Carbon $startsAt,
        ?\Illuminate\Support\Carbon $endsAt,
        bool $allDay,
        ?string $location,
        string $audience,
        bool $published,
    ): Event {
        return Event::firstOrCreate(
            ['school_id' => $school->id, 'title' => $title],
            [
                'user_id' => $author->id,
                'description' => $description,
                'type' => $type,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'all_day' => $allDay,
                'location' => $location,
                'audience' => $audience,
                'is_published' => $published,
                'published_at' => $published ? now()->subDays(2) : null,
            ],
        );
    }

    private function seedSchoolSaintAlbert(SubscriptionPlan $plan): School
    {
        $school = School::firstOrCreate(['slug' => 'saintalbert'], [
            'name' => 'Saint Albert Comprehensive High School',
            'code' => 'SACHS',
            'email' => 'info@saintalbert.edu',
            'phone' => '+237 600 000 002',
            'address' => 'Douala, Littoral',
            'status' => School::STATUS_TRIAL,
            'timezone' => 'Africa/Douala',
            'primary_color' => '#0d9488',
        ]);

        Subscription::firstOrCreate(
            ['school_id' => $school->id, 'status' => Subscription::STATUS_TRIAL],
            [
                'plan_id' => $plan->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays((int) config('synapse.trial_days', 14))->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => $plan->price,
                'currency' => $plan->currency,
            ],
        );

        $admin = $this->user($school, 'Mrs. Ngo Bassa', 'admin.saintalbert@synapse.test', User::ROLE_ADMIN);
        $teacher = $this->user($school, 'Mr. Emeka', 'teacher.saintalbert@synapse.test', User::ROLE_TEACHER);
        $student = $this->user($school, 'Mary Bih', 'student.saintalbert@synapse.test', User::ROLE_STUDENT);

        $teacherP = $this->teacher($school, $teacher, 'TCH-101');
        $studentP = $this->student($school, $student, 'SA-2026-001');

        $current = $this->year($school, '2026/2027', true);
        $form3 = $this->class($school, 'Form 3');
        $biology = $this->subject($school, 'Biology', 'BIO');
        $chemistry = $this->subject($school, 'Chemistry', 'CHE');

        $this->enroll($school, $studentP, $form3, $current);
        $this->assign($school, $teacherP, $biology, $form3, $current);
        $this->grade($school, $studentP, $biology, $form3, $current, $teacherP, 16, 17, 18);

        $this->announcement($school, $admin, 'Welcome to Saint Albert', 'Term begins next week. Check your timetable.', 'all');

        return $school;
    }

    private function seedSchoolDemo(SubscriptionPlan $plan): School
    {
        $school = School::firstOrCreate(['slug' => 'demo'], [
            'name' => 'Demo International School',
            'code' => 'DEMO',
            'email' => 'admin@demo.edu',
            'status' => School::STATUS_EXPIRED,
            'timezone' => 'Africa/Douala',
        ]);

        Subscription::firstOrCreate(
            ['school_id' => $school->id, 'status' => Subscription::STATUS_EXPIRED],
            [
                'plan_id' => $plan->id,
                'start_date' => now()->subMonths(2)->toDateString(),
                'end_date' => now()->subMonth()->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => $plan->price,
                'currency' => $plan->currency,
            ],
        );

        $this->user($school, 'Mr. Demo Admin', 'admin.demo@synapse.test', User::ROLE_ADMIN);

        return $school;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function user(School $school, string $name, string $email, string $role): User
    {
        return User::updateOrCreate(['email' => $email], [
            'school_id' => $school->id,
            'name' => $name,
            'password' => Hash::make('password123'),
            'role' => $role,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }

    private function teacher(School $school, User $user, string $staffNo): Teacher
    {
        return Teacher::firstOrCreate(
            ['user_id' => $user->id],
            ['school_id' => $school->id, 'staff_no' => $staffNo],
        );
    }

    private function student(School $school, User $user, string $matricule): Student
    {
        return Student::firstOrCreate(
            ['user_id' => $user->id],
            ['school_id' => $school->id, 'matricule' => $matricule],
        );
    }

    private function year(School $school, string $name, bool $current): AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['school_id' => $school->id, 'name' => $name],
            [
                'start_date' => $current ? now()->startOfYear()->toDateString() : now()->subYear()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
                'is_current' => $current,
            ],
        );
    }

    private function class(School $school, string $name): SchoolClass
    {
        return SchoolClass::firstOrCreate(['school_id' => $school->id, 'name' => $name]);
    }

    private function subject(School $school, string $name, string $code): Subject
    {
        return Subject::firstOrCreate(['school_id' => $school->id, 'name' => $name], ['code' => $code]);
    }

    private function enroll(School $school, Student $student, SchoolClass $class, AcademicYear $year): void
    {
        Enrollment::firstOrCreate([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
        ], [
            'school_id' => $school->id,
        ]);
    }

    private function assign(School $school, Teacher $teacher, Subject $subject, SchoolClass $class, AcademicYear $year): void
    {
        TeachingAssignment::firstOrCreate([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
        ], [
            'school_id' => $school->id,
        ]);
    }

    private function grade(
        School $school,
        Student $student,
        Subject $subject,
        SchoolClass $class,
        AcademicYear $year,
        Teacher $teacher,
        ?float $test1,
        ?float $test2,
        ?float $exam,
        ?Semester $semester = null,
    ): void {
        Grade::firstOrCreate(
            [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'class_id' => $class->id,
                'academic_year_id' => $year->id,
            ],
            [
                'school_id' => $school->id,
                'semester_id' => $semester?->id,
                'teacher_id' => $teacher->id,
                'test1' => $test1,
                'test2' => $test2,
                'exam' => $exam,
            ],
        );
    }

    /**
     * A published piece of homework. Unpublish afterwards by updating the row.
     */
    private function homework(
        School $school,
        Teacher $teacher,
        Subject $subject,
        SchoolClass $class,
        AcademicYear $year,
        Semester $semester,
        string $title,
        ?string $instructions,
        \Illuminate\Support\Carbon $dueAt,
    ): HomeworkAssignment {
        return HomeworkAssignment::firstOrCreate(
            [
                'school_id' => $school->id,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $year->id,
                'title' => $title,
            ],
            [
                'teacher_id' => $teacher->id,
                'semester_id' => $semester->id,
                'instructions' => $instructions,
                'max_score' => 20,
                'due_at' => $dueAt->setTime(23, 59),
                'is_published' => true,
                'published_at' => $dueAt->copy()->subDays(7),
            ],
        );
    }

    /**
     * A published lesson. Unpublish afterwards by updating the row.
     */
    private function lesson(
        School $school,
        Teacher $teacher,
        Subject $subject,
        SchoolClass $class,
        AcademicYear $year,
        Semester $semester,
        string $title,
        string $topic,
        int $sequence,
        ?int $minutes,
        ?string $summary,
        ?string $body,
        \Illuminate\Support\Carbon $publishedAt,
    ): Lesson {
        return Lesson::firstOrCreate(
            [
                'school_id' => $school->id,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $year->id,
                'title' => $title,
            ],
            [
                'teacher_id' => $teacher->id,
                'semester_id' => $semester->id,
                'topic' => $topic,
                'summary' => $summary,
                'body' => $body,
                'minutes' => $minutes,
                'sequence' => $sequence,
                'is_published' => true,
                'published_at' => $publishedAt,
            ],
        );
    }

    /**
     * A published quiz. Unpublish afterwards by updating the row.
     */
    private function quiz(
        School $school,
        Teacher $teacher,
        Subject $subject,
        SchoolClass $class,
        AcademicYear $year,
        Semester $semester,
        string $title,
        int $maxScore,
        ?int $timeLimit,
        ?\Illuminate\Support\Carbon $closesAt,
        ?string $instructions,
    ): Quiz {
        return Quiz::firstOrCreate(
            [
                'school_id' => $school->id,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $year->id,
                'title' => $title,
            ],
            [
                'teacher_id' => $teacher->id,
                'semester_id' => $semester->id,
                'instructions' => $instructions,
                'max_score' => $maxScore,
                'time_limit_minutes' => $timeLimit,
                'closes_at' => $closesAt,
                'attempts_allowed' => 1,
                'is_published' => true,
                'published_at' => ($closesAt ?? now())->copy()->subDays(2),
            ],
        );
    }

    /**
     * @param  list<string>  $options
     */
    private function question(Quiz $quiz, string $prompt, array $options, int $correct, int $points, int $sequence): void
    {
        QuizQuestion::firstOrCreate(
            ['quiz_id' => $quiz->id, 'sequence' => $sequence],
            [
                'school_id' => $quiz->school_id,
                'prompt' => $prompt,
                'options' => $options,
                'correct_option' => $correct,
                'points' => $points,
            ],
        );
    }

    private function submission(
        School $school,
        HomeworkAssignment $homework,
        Student $student,
        string $content,
        \Illuminate\Support\Carbon $submittedAt,
    ): HomeworkSubmission {
        return HomeworkSubmission::firstOrCreate(
            [
                'homework_assignment_id' => $homework->id,
                'student_id' => $student->id,
            ],
            [
                'school_id' => $school->id,
                'content' => $content,
                'attempts' => 1,
                'submitted_at' => $submittedAt,
                'is_late' => false,
            ],
        );
    }

    private function gradeSubmission(
        HomeworkAssignment $homework,
        Student $student,
        Teacher $teacher,
        float $score,
        ?string $feedback,
    ): void {
        HomeworkSubmission::query()
            ->where('homework_assignment_id', $homework->id)
            ->where('student_id', $student->id)
            ->update([
                'score' => $score,
                'feedback' => $feedback,
                'graded_by' => $teacher->id,
                'graded_at' => now(),
                'returned_at' => now(),
            ]);
    }

    private function semester(School $school, AcademicYear $year, string $name, int $sequence, bool $current): Semester
    {
        return Semester::firstOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => $name],
            [
                'sequence' => $sequence,
                'start_date' => $current ? now()->startOfMonth()->toDateString() : now()->addMonths(3)->startOfMonth()->toDateString(),
                'end_date' => now()->addMonths(5)->endOfMonth()->toDateString(),
                'is_current' => $current,
            ],
        );
    }

    private function component(School $school, ?Subject $subject, string $name, float $weight, int $sequence): GradeComponent
    {
        return GradeComponent::firstOrCreate(
            ['school_id' => $school->id, 'subject_id' => $subject?->id, 'name' => $name],
            [
                'weight' => $weight,
                'sequence' => $sequence,
            ],
        );
    }

    private function exam(
        School $school,
        AcademicYear $year,
        Semester $semester,
        Subject $subject,
        SchoolClass $class,
        string $date,
        string $start,
        string $end,
        string $room,
    ): void {
        Exam::firstOrCreate(
            [
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'class_id' => $class->id,
                'date' => $date,
                'start' => $start,
            ],
            [
                'academic_year_id' => $year->id,
                'semester_id' => $semester->id,
                'end' => $end,
                'room' => $room,
            ],
        );
    }

    private function slot(School $school, SchoolClass $class, AcademicYear $year, Subject $subject, int $day, string $start, string $end): void
    {
        TimetableEntry::firstOrCreate([
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
            'day' => $day,
            'start' => $start,
        ], [
            'school_id' => $school->id,
            'subject_id' => $subject->id,
            'end' => $end,
        ]);
    }

    private function request(School $school, Student $student, string $reference, string $type, string $status): DocumentRequest
    {
        return DocumentRequest::firstOrCreate(
            ['school_id' => $school->id, 'reference' => $reference],
            [
                'student_id' => $student->id,
                'type' => $type,
                'reason' => 'Demo request',
                'status' => $status,
                'resolved_at' => $status === 'ready' ? now() : null,
            ],
        );
    }

    private function announcement(School $school, User $author, string $title, string $body, string $audience): void
    {
        Announcement::firstOrCreate(
            ['school_id' => $school->id, 'title' => $title],
            [
                'user_id' => $author->id,
                'body' => $body,
                'audience' => $audience,
                'published_at' => now(),
            ],
        );
    }

    private function attendance(
        School $school,
        SchoolClass $class,
        AcademicYear $year,
        Teacher $teacher,
        Student $student,
        string $date,
        string $status,
    ): void {
        Attendance::firstOrCreate(
            [
                'school_id' => $school->id,
                'class_id' => $class->id,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'date' => $date,
            ],
            [
                'teacher_id' => $teacher->id,
                'status' => $status,
            ],
        );
    }

    private function notification(School $school, User $user, string $title, string $message): void
    {
        Notification::firstOrCreate(
            ['school_id' => $school->id, 'user_id' => $user->id, 'title' => $title],
            [
                'type' => 'demo',
                'message' => $message,
                'data' => [],
            ],
        );
    }
}
