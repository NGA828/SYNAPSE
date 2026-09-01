<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Academic analytics (Phase 5.6).
 *
 * The figures are aggregated from the grade book, attendance, homework and
 * quizzes that already exist — nothing is entered twice, so the point of these
 * tests is that the aggregation agrees with the records, and that each role
 * sees only its own slice.
 */
class AnalyticsTest extends TestCase
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

    private function aics(): School
    {
        return School::where('name', 'like', 'Alpine%')->firstOrFail();
    }

    private function level3a(): SchoolClass
    {
        return SchoolClass::where('name', 'Level 3A')->firstOrFail();
    }

    private function level2a(): SchoolClass
    {
        return SchoolClass::where('name', 'Level 2A')->firstOrFail();
    }

    // ----------------------------------------------------------------- shape

    public function test_admin_overview_returns_every_section(): void
    {
        $this->actAs('admin@synapse.test');

        $this->getJson('/api/admin/analytics')->assertOk()->assertJsonStructure([
            'data' => [
                'academic_year' => ['id', 'name'],
                'scope' => ['type'],
                'counts' => ['students', 'teachers', 'classes', 'subjects'],
                'performance' => ['average', 'pass_rate', 'graded_students'],
                'by_class',
                'distribution',
                'attendance' => ['rate', 'records', 'present', 'late', 'absent', 'excused'],
                'engagement' => [
                    'assignments_published', 'submissions', 'submission_rate',
                    'quizzes_published', 'quiz_attempts', 'quiz_average',
                ],
                'at_risk' => ['flagged', 'critical', 'warning', 'monitored', 'by_class'],
            ],
        ]);
    }

    public function test_an_admin_sees_a_school_wide_scope(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->getJson('/api/admin/analytics')->assertOk()->json('data');

        $this->assertSame('school', $data['scope']['type']);
        $this->assertArrayNotHasKey('classes', $data['scope']);
    }

    public function test_counts_reflect_the_seeded_structure(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->getJson('/api/admin/analytics')->assertOk()->json('data');

        // Seeded: John, Mary and Peter in Level 3A.
        $this->assertSame(3, $data['counts']['students']);

        $enrolled = Enrollment::query()
            ->where('academic_year_id', AcademicYear::current()->id)
            ->distinct()
            ->count('class_id');

        $this->assertSame($enrolled, count($data['by_class']));
    }

    // ------------------------------------------------------------- by class

    public function test_a_class_with_no_enrolled_students_is_omitted(): void
    {
        // Level 2A is taught but nobody is enrolled in it this year.
        $this->assertSame(
            0,
            Enrollment::query()
                ->where('academic_year_id', AcademicYear::current()->id)
                ->where('class_id', $this->level2a()->id)
                ->count(),
        );

        $this->actAs('admin@synapse.test');

        $rows = $this->getJson('/api/admin/analytics')->assertOk()->json('data.by_class');

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertNotSame('Level 2A', $row['label']);
            $this->assertGreaterThan(0, $row['students']);
        }
    }

    public function test_a_class_average_matches_the_grades_behind_it(): void
    {
        $this->actAs('admin@synapse.test');

        $rows = $this->getJson('/api/admin/analytics')->assertOk()->json('data.by_class');

        $level3a = collect($rows)->firstWhere('label', 'Level 3A');
        $this->assertNotNull($level3a, 'Level 3A should be reported.');

        // Mean of the six seeded Level 3A grade averages.
        $expected = Grade::query()
            ->where('class_id', $this->level3a()->id)
            ->get()
            ->map(fn (Grade $grade) => $grade->average)
            ->filter(fn ($value) => $value !== null)
            ->avg();

        $this->assertEquals(round($expected, 2), $level3a['value']);
    }

    // ----------------------------------------------------------- distribution

    public function test_the_distribution_sums_to_the_graded_students(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->getJson('/api/admin/analytics')->assertOk()->json('data');

        $total = collect($data['distribution'])->sum('value');

        $this->assertSame($data['performance']['graded_students'], $total);
        $this->assertSame(3, $total, 'John, Mary and Peter all have grades.');
    }

    public function test_the_distribution_uses_the_configured_mentions(): void
    {
        $this->actAs('admin@synapse.test');

        $labels = collect($this->getJson('/api/admin/analytics')->assertOk()->json('data.distribution'))
            ->pluck('label')
            ->all();

        $this->assertSame(
            collect(config('synapse.grading.mentions'))->pluck('label')->all(),
            $labels,
        );
    }

    // -------------------------------------------------------------- attendance

    public function test_the_attendance_rate_excludes_excused_absences(): void
    {
        $this->actAs('admin@synapse.test');

        $attendance = $this->getJson('/api/admin/analytics')->assertOk()->json('data.attendance');

        // Seeded: 6 present, 1 late, 1 absent, 1 excused.
        $this->assertSame(6, $attendance['present']);
        $this->assertSame(1, $attendance['late']);
        $this->assertSame(1, $attendance['absent']);
        $this->assertSame(1, $attendance['excused']);

        // (6 + 1) / (6 + 1 + 1) — the excused absence sits outside both sides.
        $this->assertSame(87.5, $attendance['rate']);
        $this->assertSame(9, $attendance['records']);
    }

    public function test_attendance_with_no_records_reports_null_rather_than_zero(): void
    {
        Attendance::query()->delete();

        $this->actAs('admin@synapse.test');

        $attendance = $this->getJson('/api/admin/analytics')->assertOk()->json('data.attendance');

        $this->assertNull($attendance['rate'], 'No registers means no rate, not a rate of 0%.');
        $this->assertSame(0, $attendance['records']);
    }

    // ------------------------------------------------------------- engagement

    public function test_engagement_counts_only_published_homework(): void
    {
        $this->actAs('admin@synapse.test');

        $engagement = $this->getJson('/api/admin/analytics')->assertOk()->json('data.engagement');

        // Seeded: three published plus one draft, and three submissions.
        $this->assertSame(3, $engagement['assignments_published']);
        $this->assertSame(3, $engagement['submissions']);
    }

    // ----------------------------------------------------------- teacher scope

    public function test_a_teacher_sees_only_their_own_classes(): void
    {
        $this->actAs('teacher@synapse.test');

        $data = $this->getJson('/api/teacher/analytics')->assertOk()->json('data');

        $this->assertSame('teacher', $data['scope']['type']);
        $this->assertSame(['Level 2A', 'Level 3A'], collect($data['scope']['classes'])->sort()->values()->all());

        $labels = collect($data['by_class'])->pluck('label')->all();
        foreach ($labels as $label) {
            $this->assertContains($label, ['Level 2A', 'Level 3A']);
        }
    }

    public function test_a_teacher_does_not_count_homework_set_by_someone_else(): void
    {
        $this->actAs('admin@synapse.test');
        $schoolWide = $this->getJson('/api/admin/analytics')->assertOk()->json('data.engagement');

        $this->actAs('teacher@synapse.test');
        $mine = $this->getJson('/api/teacher/analytics')->assertOk()->json('data.engagement');

        // David set the essay and the history research; Sarah set the maths.
        $this->assertSame(3, $schoolWide['assignments_published']);
        $this->assertSame(2, $mine['assignments_published']);
    }

    public function test_a_teacher_counts_their_own_subjects_only(): void
    {
        $this->actAs('teacher@synapse.test');

        $data = $this->getJson('/api/teacher/analytics')->assertOk()->json('data');

        // English and History.
        $this->assertSame(2, $data['counts']['subjects']);
    }

    // ----------------------------------------------------------- access control

    public function test_a_student_cannot_read_school_analytics(): void
    {
        $this->actAs('student@synapse.test');

        $this->getJson('/api/admin/analytics')->assertForbidden();
    }

    public function test_a_student_cannot_read_the_teacher_view(): void
    {
        $this->actAs('student@synapse.test');

        $this->getJson('/api/teacher/analytics')->assertForbidden();
    }

    public function test_a_teacher_cannot_read_the_admin_view(): void
    {
        $this->actAs('teacher@synapse.test');

        $this->getJson('/api/admin/analytics')->assertForbidden();
    }

    public function test_a_guest_cannot_read_analytics(): void
    {
        $this->getJson('/api/admin/analytics')->assertUnauthorized();
        $this->getJson('/api/teacher/analytics')->assertUnauthorized();
        $this->getJson('/api/student/insights')->assertUnauthorized();
    }

    // ----------------------------------------------------------------- tenancy

    public function test_another_school_sees_only_its_own_figures(): void
    {
        $this->actAs('admin.saintalbert@synapse.test');

        $data = $this->getJson('/api/admin/analytics')->assertOk()->json('data');

        // Saint Albert seeds a single student in Form 3.
        $this->assertSame(1, $data['counts']['students']);

        foreach ($data['by_class'] as $row) {
            $this->assertSame('Form 3', $row['label']);
        }

        // The two schools really are different tenants, so the count above is
        // isolation and not merely a smaller seed.
        $this->assertNotSame(
            $this->aics()->id,
            User::where('email', 'admin.saintalbert@synapse.test')->value('school_id'),
        );
    }

    public function test_no_foreign_pupil_appears_in_the_totals(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->getJson('/api/admin/analytics')->assertOk()->json('data');

        // Three AICS pupils, not the four in the database.
        $this->assertSame(3, $data['counts']['students']);
        $this->assertSame(3, $data['performance']['graded_students']);
    }

    // ------------------------------------------------------------- read only

    public function test_analytics_never_writes(): void
    {
        $before = [
            Grade::query()->count(),
            Attendance::query()->count(),
            Student::query()->count(),
        ];

        $this->actAs('admin@synapse.test');

        $this->getJson('/api/admin/analytics')->assertOk();
        $this->getJson('/api/admin/analytics/at-risk')->assertOk();

        $this->assertSame($before, [
            Grade::query()->count(),
            Attendance::query()->count(),
            Student::query()->count(),
        ]);
    }

    public function test_analytics_survive_a_school_with_no_academic_data(): void
    {
        Grade::query()->delete();
        Attendance::query()->delete();

        $this->actAs('admin@synapse.test');

        $data = $this->getJson('/api/admin/analytics')->assertOk()->json('data');

        $this->assertNull($data['performance']['average']);
        $this->assertNull($data['performance']['pass_rate']);
        $this->assertSame(0, $data['performance']['graded_students']);

        // A class still appears, with no average to report.
        $this->assertNotEmpty($data['by_class']);
        $this->assertNull($data['by_class'][0]['value']);
    }

    public function test_the_at_risk_summary_agrees_with_the_register(): void
    {
        $this->actAs('admin@synapse.test');

        $overview = $this->getJson('/api/admin/analytics')->assertOk()->json('data.at_risk');
        $register = $this->getJson('/api/admin/analytics/at-risk')->assertOk()->json('data');

        $this->assertSame(count($register), $overview['flagged']);
        $this->assertSame(
            collect($register)->where('severity', 'critical')->count(),
            $overview['critical'],
        );
        $this->assertSame(
            $overview['flagged'] + $overview['monitored'],
            3,
            'Every student is either flagged or monitored.',
        );
    }

    public function test_the_academic_year_is_reported_so_the_reader_knows_the_period(): void
    {
        $this->actAs('admin@synapse.test');

        $year = $this->getJson('/api/admin/analytics')->assertOk()->json('data.academic_year');

        $current = AcademicYear::current();

        $this->assertSame($current->id, $year['id']);
        $this->assertSame($current->name, $year['name']);
    }
}
