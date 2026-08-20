<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\School;
use App\Models\SchoolClass;
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
        return User::firstOrCreate(['email' => 'superadmin@synapse.test'], [
            'school_id' => null,
            'name' => 'Platform Super Admin',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_SUPER_ADMIN,
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

        // Grades
        $this->grade($school, $johnS, $english, $l3a, $current, $davidT, 15, 16, 17);
        $this->grade($school, $johnS, $math, $l3a, $current, $sarahT, 14, 16, 16);
        $this->grade($school, $johnS, $database, $l3a, $current, $felixT, 16, 17, 18);
        $this->grade($school, $johnS, $networking, $l3a, $current, $felixT, 13, 15, 15);
        $this->grade($school, $maryS, $english, $l3a, $current, $davidT, 14, 15, 16);
        $this->grade($school, $peterS, $english, $l3a, $current, $davidT, 12, 14, 15);

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

        return $school;
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
        return User::firstOrCreate(['email' => $email], [
            'school_id' => $school->id,
            'name' => $name,
            'password' => Hash::make('password123'),
            'role' => $role,
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
                'teacher_id' => $teacher->id,
                'test1' => $test1,
                'test2' => $test2,
                'exam' => $exam,
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
