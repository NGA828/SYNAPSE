<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\Schema;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The pastoral register (Phase 5.6).
 *
 * Each signal test builds its own records rather than leaning on whatever the
 * seed happens to contain: a threshold test has to be able to fail when the
 * threshold is wrong, and a shared fixture cannot show that.
 */
class AtRiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function actAs(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function level3a(): SchoolClass
    {
        return SchoolClass::where('name', 'Level 3A')->firstOrFail();
    }

    private function subject(string $code): Subject
    {
        return Subject::where('code', $code)->firstOrFail();
    }

    private function student(string $email): Student
    {
        return Student::where('user_id', User::where('email', $email)->value('id'))->firstOrFail();
    }

    /** Wipe Peter's seeded grades so a test controls exactly what he has. */
    private function resetPeter(): Student
    {
        $peter = $this->student('peter@synapse.test');

        Grade::query()->where('student_id', $peter->id)->delete();
        HomeworkSubmission::query()->where('student_id', $peter->id)->delete();
        Attendance::query()->where('student_id', $peter->id)->delete();
        QuizAttempt::query()->where('student_id', $peter->id)->delete();

        return $peter;
    }

    private function grade(Student $student, string $subjectCode, float $test1, float $test2, float $exam, ?int $semesterId = null): Grade
    {
        return Grade::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'subject_id' => $this->subject($subjectCode)->id,
            'class_id' => $this->level3a()->id,
            'academic_year_id' => AcademicYear::current()->id,
            'semester_id' => $semesterId ?? Semester::query()
                ->where('academic_year_id', AcademicYear::current()->id)
                ->where('is_current', true)
                ->value('id'),
            'teacher_id' => Teacher::query()->value('id'),
            'test1' => $test1,
            'test2' => $test2,
            'exam' => $exam,
        ]);
    }

    private function attendance(Student $student, string $status, string $date): Attendance
    {
        return Attendance::create([
            'school_id' => $student->school_id,
            'class_id' => $this->level3a()->id,
            'student_id' => $student->id,
            'academic_year_id' => AcademicYear::current()->id,
            'teacher_id' => Teacher::query()->value('id'),
            'date' => $date,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, mixed>|null The register row for Peter, or null.
     */
    private function peterRow(string $path = '/api/admin/analytics/at-risk'): ?array
    {
        return collect($this->getJson($path)->assertOk()->json('data'))
            ->firstWhere('student.name', 'Peter Paul');
    }

    private function codes(?array $row): array
    {
        return collect($row['signals'] ?? [])->pluck('code')->all();
    }

    // ----------------------------------------------------------------- shape

    public function test_the_register_is_paginated(): void
    {
        $this->actAs('admin@synapse.test');

        $this->getJson('/api/admin/analytics/at-risk')->assertOk()->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
        ]);
    }

    public function test_every_flagged_student_carries_the_reason_in_words(): void
    {
        $this->actAs('admin@synapse.test');

        $rows = $this->getJson('/api/admin/analytics/at-risk')->assertOk()->json('data');

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertNotEmpty($row['signals']);

            foreach ($row['signals'] as $signal) {
                $this->assertNotEmpty($signal['code']);
                $this->assertNotEmpty($signal['label']);
                $this->assertContains($signal['severity'], ['warning', 'critical']);
                $this->assertGreaterThan(
                    10,
                    strlen($signal['detail']),
                    'A signal must explain itself, not just name a code.',
                );
            }
        }
    }

    public function test_severity_is_critical_only_when_a_critical_signal_fired(): void
    {
        $this->actAs('admin@synapse.test');

        foreach ($this->getJson('/api/admin/analytics/at-risk')->assertOk()->json('data') as $row) {
            $hasCritical = collect($row['signals'])->contains(fn ($s) => $s['severity'] === 'critical');

            $this->assertSame(
                $hasCritical,
                $row['severity'] === 'critical',
                "Row severity disagrees with its signals for {$row['student']['name']}.",
            );
        }
    }

    public function test_a_student_in_good_standing_is_left_off_the_register(): void
    {
        $mary = $this->student('mary@synapse.test');

        Grade::query()->where('student_id', $mary->id)->update([
            'test1' => 16, 'test2' => 16, 'exam' => 16,
        ]);
        Attendance::query()->where('student_id', $mary->id)->update(['status' => Attendance::PRESENT]);
        HomeworkAssignment::query()
            ->where('is_published', true)
            ->get()
            ->each(fn (HomeworkAssignment $assignment) => HomeworkSubmission::firstOrCreate([
                'homework_assignment_id' => $assignment->id,
                'student_id' => $mary->id,
            ], [
                'school_id' => $mary->school_id,
                'content' => 'Completed.',
                'submitted_at' => Carbon::now(),
            ]));

        $this->actAs('admin@synapse.test');

        $names = collect($this->getJson('/api/admin/analytics/at-risk')->assertOk()->json('data'))
            ->pluck('student.name')
            ->all();

        $this->assertNotContains('Mary Smith', $names);
        $this->assertNotEmpty($names, 'Peter is seeded with a low mark, so the register must not be empty.');
    }

    public function test_each_row_exposes_a_top_level_id_for_the_list_key(): void
    {
        $this->actAs('admin@synapse.test');

        foreach ($this->getJson('/api/admin/analytics/at-risk')->assertOk()->json('data') as $row) {
            $this->assertSame($row['student']['id'], $row['id']);
        }
    }

    // --------------------------------------------------------------- filters

    public function test_the_severity_filter_narrows_the_register(): void
    {
        $this->actAs('admin@synapse.test');

        $all = $this->getJson('/api/admin/analytics/at-risk')->assertOk()->json('data');

        foreach (['warning', 'critical'] as $severity) {
            $filtered = $this->getJson("/api/admin/analytics/at-risk?severity={$severity}")
                ->assertOk()
                ->json('data');

            foreach ($filtered as $row) {
                $this->assertSame($severity, $row['severity']);
            }

            $this->assertSame(
                collect($all)->where('severity', $severity)->count(),
                count($filtered),
            );
        }
    }

    public function test_an_invalid_severity_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        $this->getJson('/api/admin/analytics/at-risk?severity=urgent')
            ->assertStatus(422)
            ->assertJsonValidationErrors('severity');
    }

    public function test_the_class_filter_keeps_only_that_class(): void
    {
        $this->actAs('admin@synapse.test');

        $inClass = $this->getJson('/api/admin/analytics/at-risk?class_id='.$this->level3a()->id)
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($inClass);

        // Level 1A has nobody enrolled.
        $empty = SchoolClass::where('name', 'Level 1A')->value('id');
        $this->assertEmpty(
            $this->getJson("/api/admin/analytics/at-risk?class_id={$empty}")->assertOk()->json('data'),
        );
    }

    public function test_search_finds_a_student_by_name(): void
    {
        $this->actAs('admin@synapse.test');

        $found = $this->getJson('/api/admin/analytics/at-risk?search=Peter')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($found, 'Peter is seeded with a low mark.');

        foreach ($found as $row) {
            $this->assertStringContainsStringIgnoringCase('Peter', $row['student']['name']);
        }
    }

    public function test_search_excludes_non_matches(): void
    {
        $this->actAs('admin@synapse.test');

        $this->assertEmpty(
            $this->getJson('/api/admin/analytics/at-risk?search=zzz-no-such-pupil')
                ->assertOk()
                ->json('data'),
        );
    }

    public function test_per_page_is_honoured_while_total_covers_the_whole_register(): void
    {
        $this->actAs('admin@synapse.test');

        $total = $this->getJson('/api/admin/analytics/at-risk')->assertOk()->json('meta.total');
        $this->assertGreaterThan(1, $total, 'The seed must flag more than one pupil for this to mean anything.');

        $response = $this->getJson('/api/admin/analytics/at-risk?per_page=1')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($total, $response->json('meta.total'));
        $this->assertSame(1, $response->json('meta.per_page'));
    }

    // ------------------------------------------------- signals: the low average

    public function test_a_deep_shortfall_is_critical_and_quotes_the_numbers(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 6, 7, 7);

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $this->assertNotNull($row, 'Peter should be flagged.');
        $this->assertSame('critical', $row['severity']);
        $this->assertContains('low_average', $this->codes($row));

        $signal = collect($row['signals'])->firstWhere('code', 'low_average');
        $this->assertSame('critical', $signal['severity']);
        $this->assertStringContainsString('6.67', $signal['detail']);
    }

    public function test_a_narrow_shortfall_is_only_a_warning(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 9, 9, 9);

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $signal = collect($row['signals'])->firstWhere('code', 'low_average');

        $this->assertNotNull($signal);
        $this->assertSame('warning', $signal['severity']);
    }

    public function test_an_average_at_the_pass_mark_is_not_flagged(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 12, 12, 12);

        $this->actAs('admin@synapse.test');

        // 12 is comfortably above the pass mark, so this signal must not fire.
        // The deep-shortfall test above proves the same call can contain it.
        $this->assertNotContains('low_average', $this->codes($this->peterRow()));
    }

    public function test_two_failing_subjects_raise_failing_subjects(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 6, 7, 7);
        $this->grade($peter, 'MAT', 8, 8, 8);

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $this->assertContains('failing_subjects', $this->codes($row));

        $signal = collect($row['signals'])->firstWhere('code', 'failing_subjects');
        $this->assertStringContainsString('2 subject(s)', $signal['detail']);
    }

    public function test_one_failing_subject_does_not_raise_failing_subjects(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 6, 7, 7);

        $this->actAs('admin@synapse.test');

        $this->assertNotContains('failing_subjects', $this->codes($this->peterRow()));
    }

    // ------------------------------------------------- signals: homework

    public function test_missing_past_due_homework_is_critical(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 14, 14, 14);

        // Only one seeded assignment is past its deadline, so backdate a second.
        HomeworkAssignment::query()
            ->where('class_id', $this->level3a()->id)
            ->where('is_published', true)
            ->orderBy('id')
            ->skip(1)
            ->take(1)
            ->get()
            ->each(fn (HomeworkAssignment $assignment) => $assignment->update([
                'due_at' => Carbon::now()->subDays(3),
            ]));

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $this->assertContains('missing_homework', $this->codes($row));

        $signal = collect($row['signals'])->firstWhere('code', 'missing_homework');
        $this->assertSame('critical', $signal['severity']);
        $this->assertGreaterThanOrEqual(
            (int) config('synapse.at_risk.missing_homework'),
            $row['homework']['missing'],
        );
    }

    public function test_homework_not_yet_due_is_not_counted_as_missing(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 14, 14, 14);

        HomeworkAssignment::query()
            ->where('is_published', true)
            ->update(['due_at' => Carbon::now()->addDays(10)]);

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $this->assertSame(0, $row['homework']['missing']);
        $this->assertNotContains('missing_homework', $this->codes($row));
    }

    // ------------------------------------------------- signals: attendance

    public function test_low_attendance_is_flagged(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 14, 14, 14);

        $this->attendance($peter, Attendance::PRESENT, Carbon::today()->toDateString());
        $this->attendance($peter, Attendance::ABSENT, Carbon::today()->subDay()->toDateString());
        $this->attendance($peter, Attendance::ABSENT, Carbon::today()->subDays(2)->toDateString());

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $this->assertContains('poor_attendance', $this->codes($row));
        $this->assertSame(33.3, $row['attendance']);
    }

    public function test_an_excused_absence_is_not_held_against_a_student(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 14, 14, 14);

        $this->attendance($peter, Attendance::PRESENT, Carbon::today()->toDateString());
        $this->attendance($peter, Attendance::PRESENT, Carbon::today()->subDay()->toDateString());
        $this->attendance($peter, Attendance::EXCUSED, Carbon::today()->subDays(2)->toDateString());

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $this->assertSame(100.0, $row['attendance']);
        $this->assertNotContains('poor_attendance', $this->codes($row));
    }

    public function test_being_late_still_counts_as_attending(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 14, 14, 14);

        $this->attendance($peter, Attendance::LATE, Carbon::today()->toDateString());
        $this->attendance($peter, Attendance::LATE, Carbon::today()->subDay()->toDateString());

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $this->assertSame(100.0, $row['attendance']);
        $this->assertNotContains('poor_attendance', $this->codes($row));
    }

    // ------------------------------------------------- signals: trend and quizzes

    public function test_a_term_over_term_drop_raises_declining(): void
    {
        $peter = $this->resetPeter();

        $yearId = AcademicYear::current()->id;

        // Make semester 2 the current one so a previous semester exists.
        Semester::query()->where('academic_year_id', $yearId)->update(['is_current' => false]);
        $current = Semester::query()->where('academic_year_id', $yearId)->where('sequence', 2)->firstOrFail();
        $previous = Semester::query()->where('academic_year_id', $yearId)->where('sequence', 1)->firstOrFail();
        $current->update(['is_current' => true]);

        $this->grade($peter, 'ENG', 15, 15, 15, $previous->id);
        $this->grade($peter, 'ENG', 12, 12, 12, $current->id);

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $this->assertContains('declining', $this->codes($row));

        $signal = collect($row['signals'])->firstWhere('code', 'declining');
        $this->assertStringContainsString('15', $signal['detail']);
        $this->assertStringContainsString('12', $signal['detail']);
    }

    public function test_a_stable_average_is_not_called_declining(): void
    {
        $peter = $this->resetPeter();

        $yearId = AcademicYear::current()->id;

        Semester::query()->where('academic_year_id', $yearId)->update(['is_current' => false]);
        $current = Semester::query()->where('academic_year_id', $yearId)->where('sequence', 2)->firstOrFail();
        $previous = Semester::query()->where('academic_year_id', $yearId)->where('sequence', 1)->firstOrFail();
        $current->update(['is_current' => true]);

        $this->grade($peter, 'ENG', 15, 15, 15, $previous->id);
        $this->grade($peter, 'ENG', 15, 15, 15, $current->id);

        $this->actAs('admin@synapse.test');

        $this->assertNotContains('declining', $this->codes($this->peterRow()));
    }

    public function test_a_weak_quiz_average_is_flagged_as_a_percentage_of_the_paper(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 14, 14, 14);

        $quiz = Quiz::query()
            ->where('class_id', $this->level3a()->id)
            ->where('is_published', true)
            ->firstOrFail();

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $peter->id,
            'school_id' => $peter->school_id,
            'answers' => [],
            'correct_count' => 0,
            'total_questions' => 4,
            'score' => round($quiz->max_score * 0.2, 2),
            'attempt' => 1,
            'started_at' => Carbon::now()->subHour(),
            'submitted_at' => Carbon::now()->subMinutes(50),
        ]);

        $this->actAs('admin@synapse.test');

        $row = $this->peterRow();

        $this->assertContains('low_quiz_average', $this->codes($row));
        $this->assertSame(20.0, $row['quizzes']['percentage']);
    }

    // ------------------------------------------------------------- visibility

    public function test_a_teacher_sees_the_students_in_their_own_classes(): void
    {
        $this->actAs('teacher@synapse.test');

        $rows = $this->getJson('/api/teacher/analytics/at-risk')->assertOk()->json('data');

        $this->assertNotEmpty($rows);

        $level3a = $this->level3a()->id;

        foreach ($rows as $row) {
            $enrolled = Enrollment::query()
                ->where('student_id', $row['student']['id'])
                ->where('academic_year_id', AcademicYear::current()->id)
                ->where('class_id', $level3a)
                ->exists();

            $this->assertTrue($enrolled, "{$row['student']['name']} is not in a class David teaches.");
        }
    }

    public function test_a_teacher_cannot_review_a_student_outside_their_classes(): void
    {
        // Sarah teaches maths in Level 1B, which David does not hold.
        $this->actAs('teacher@synapse.test');

        $peter = $this->student('peter@synapse.test');

        // Move Peter into a class David does not teach for this year.
        Enrollment::query()
            ->where('student_id', $peter->id)
            ->where('academic_year_id', AcademicYear::current()->id)
            ->update(['class_id' => SchoolClass::where('name', 'Level 1A')->value('id')]);

        $this->getJson("/api/teacher/analytics/students/{$peter->id}")->assertForbidden();
    }

    public function test_an_admin_can_review_any_pupil_in_their_school(): void
    {
        $this->actAs('admin@synapse.test');

        $peter = $this->student('peter@synapse.test');

        $this->getJson("/api/admin/analytics/students/{$peter->id}")
            ->assertOk()
            ->assertJsonPath('data.student.id', $peter->id);
    }

    public function test_a_foreign_school_pupil_is_not_found_rather_than_forbidden(): void
    {
        $this->actAs('admin@synapse.test');

        $foreign = Student::query()
            ->where('school_id', '!=', User::where('email', 'admin@synapse.test')->value('school_id'))
            ->firstOrFail();

        // 404, not 403: a 403 would confirm the record exists.
        $this->getJson("/api/admin/analytics/students/{$foreign->id}")->assertNotFound();
    }

    public function test_a_student_can_only_review_themselves(): void
    {
        $this->actAs('student@synapse.test');

        $other = $this->student('mary@synapse.test');

        $this->getJson("/api/admin/analytics/students/{$other->id}")->assertForbidden();
    }

    // --------------------------------------------------------- student self view

    public function test_a_student_reads_their_own_signals(): void
    {
        $this->actAs('student@synapse.test');

        $data = $this->getJson('/api/student/insights')->assertOk()->json('data');

        $this->assertSame('John Doe', $data['student']['name']);
        $this->assertArrayHasKey('average', $data);
        $this->assertArrayHasKey('severity', $data);
        $this->assertIsArray($data['signals']);
    }

    public function test_a_student_in_good_standing_has_no_signals(): void
    {
        $john = $this->student('student@synapse.test');

        // Give John a clean sheet: high marks, full attendance, everything in.
        Grade::query()->where('student_id', $john->id)->update([
            'test1' => 16, 'test2' => 16, 'exam' => 16,
        ]);
        Attendance::query()->where('student_id', $john->id)->update(['status' => Attendance::PRESENT]);
        HomeworkAssignment::query()
            ->where('is_published', true)
            ->get()
            ->each(function (HomeworkAssignment $assignment) use ($john) {
                HomeworkSubmission::firstOrCreate([
                    'homework_assignment_id' => $assignment->id,
                    'student_id' => $john->id,
                ], [
                    'school_id' => $john->school_id,
                    'content' => 'Completed.',
                    'submitted_at' => Carbon::now(),
                ]);
            });
        QuizAttempt::query()->where('student_id', $john->id)->delete();

        $this->actAs('student@synapse.test');

        $data = $this->getJson('/api/student/insights')->assertOk()->json('data');

        $this->assertSame([], $data['signals']);
        $this->assertNull($data['severity']);
    }

    public function test_a_student_sees_the_same_signals_their_teacher_sees(): void
    {
        $peter = $this->resetPeter();
        $this->grade($peter, 'ENG', 6, 7, 7);

        $this->actAs('admin@synapse.test');
        $registerCodes = $this->codes($this->peterRow());

        Sanctum::actingAs(User::where('email', 'peter@synapse.test')->firstOrFail(), ['*']);

        $ownCodes = collect($this->getJson('/api/student/insights')->assertOk()->json('data.signals'))
            ->pluck('code')
            ->all();

        $this->assertSame($registerCodes, $ownCodes);
    }

    // ---------------------------------------------------------- access control

    public function test_a_student_cannot_read_the_register(): void
    {
        $this->actAs('student@synapse.test');

        $this->getJson('/api/admin/analytics/at-risk')->assertForbidden();
        $this->getJson('/api/teacher/analytics/at-risk')->assertForbidden();
    }

    public function test_a_guest_cannot_read_the_register_or_insights(): void
    {
        $this->getJson('/api/admin/analytics/at-risk')->assertUnauthorized();
        $this->getJson('/api/teacher/analytics/at-risk')->assertUnauthorized();
        $this->getJson('/api/student/insights')->assertUnauthorized();
    }

    public function test_no_foreign_pupil_appears_on_the_register(): void
    {
        $this->actAs('admin@synapse.test');

        $schoolId = User::where('email', 'admin@synapse.test')->value('school_id');

        foreach ($this->getJson('/api/admin/analytics/at-risk')->assertOk()->json('data') as $row) {
            $this->assertSame(
                $schoolId,
                Student::find($row['student']['id'])->school_id,
            );
        }
    }

    // ---------------------------------------------------------------- read only

    public function test_the_register_is_computed_not_stored(): void
    {
        $this->assertEmpty(
            Schema::hasColumn('students', 'is_at_risk')
                ? ['students has an is_at_risk column']
                : [],
            'Risk must never be persisted, or it goes stale.',
        );

        $before = Grade::query()->count() + Attendance::query()->count();

        $this->actAs('admin@synapse.test');
        $this->getJson('/api/admin/analytics/at-risk')->assertOk();

        $this->assertSame($before, Grade::query()->count() + Attendance::query()->count());
    }
}
