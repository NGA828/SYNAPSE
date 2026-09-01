<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The homework lifecycle — draft → publish → submit → replace → grade → return —
 * plus the authorisation and tenancy rules that hold it together.
 *
 * Every test builds its own homework rather than relying on whichever seeded row
 * happens to come back first, so the assertions cannot drift with the seed data.
 */
class HomeworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function actAs(string|User $identity): User
    {
        $user = $identity instanceof User
            ? $identity
            : User::where('email', $identity)->firstOrFail();

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** Mr. David (TCH-001) is assigned to English and History in Level 3A. */
    private function david(): Teacher
    {
        return Teacher::where('staff_no', 'TCH-001')->firstOrFail();
    }

    private function john(): Student
    {
        return Student::where('matricule', 'ST2026045')->firstOrFail();
    }

    /** Also enrolled in Level 3A — used to prove private files stay private. */
    private function mary(): Student
    {
        return Student::where('matricule', 'ST2026031')->firstOrFail();
    }

    private function level3a(): SchoolClass
    {
        return SchoolClass::where('name', 'Level 3A')->firstOrFail();
    }

    private function english(): Subject
    {
        return Subject::where('code', 'ENG')->firstOrFail();
    }

    private function year(): AcademicYear
    {
        return AcademicYear::current();
    }

    /**
     * Create homework directly through the model, bypassing the API, so tests
     * that exercise submission/grading are not coupled to the create endpoint.
     */
    private function makeHomework(array $overrides = []): HomeworkAssignment
    {
        return HomeworkAssignment::create(array_merge([
            'school_id' => $this->level3a()->school_id,
            'teacher_id' => $this->david()->id,
            'subject_id' => $this->english()->id,
            'class_id' => $this->level3a()->id,
            'academic_year_id' => $this->year()->id,
            'semester_id' => Semester::query()->where('academic_year_id', $this->year()->id)->value('id'),
            'title' => 'Fixture homework '.uniqid(),
            'instructions' => 'Answer every question in full sentences.',
            'max_score' => 20,
            'due_at' => now()->addDays(4),
            'is_published' => true,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function createViaApi(array $overrides = []): array
    {
        $this->actAs('teacher@synapse.test');

        $payload = array_merge([
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'API homework '.uniqid(),
            'instructions' => 'Answer every question in full sentences.',
            'max_score' => 20,
            'due_at' => now()->addDays(4)->toIso8601String(),
        ], $overrides);

        return $this->postJson('/api/teacher/homework', $payload)
            ->assertCreated()
            ->json('data');
    }

    // ------------------------------------------------------------- authoring

    public function test_teacher_can_create_homework_as_an_unpublished_draft(): void
    {
        $data = $this->createViaApi();

        $this->assertFalse($data['is_published']);
        $this->assertSame('English', $data['subject']['name']);
        $this->assertSame('Level 3A', $data['class']['name']);

        $this->assertDatabaseHas('homework_assignments', [
            'id' => $data['id'],
            'teacher_id' => $this->david()->id,
            'is_published' => false,
        ]);
    }

    public function test_teacher_cannot_set_homework_for_a_class_they_do_not_teach(): void
    {
        $this->actAs('teacher@synapse.test');

        $level1a = SchoolClass::where('name', 'Level 1A')->firstOrFail();

        $this->postJson('/api/teacher/homework', [
            'class_id' => $level1a->id,
            'subject_id' => $this->english()->id,
            'title' => 'Not my class',
            'max_score' => 20,
            'due_at' => now()->addDays(4)->toIso8601String(),
        ])->assertStatus(403);
    }

    public function test_a_past_deadline_is_rejected(): void
    {
        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/teacher/homework', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'Already overdue',
            'max_score' => 20,
            'due_at' => now()->subDay()->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors('due_at');
    }

    public function test_a_duplicate_title_for_the_same_class_and_subject_is_rejected(): void
    {
        $homework = $this->makeHomework(['title' => 'Unique fixture title']);

        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/teacher/homework', [
            'class_id' => $homework->class_id,
            'subject_id' => $homework->subject_id,
            'title' => 'Unique fixture title',
            'max_score' => 20,
            'due_at' => now()->addDays(4)->toIso8601String(),
        ])->assertStatus(422);
    }

    // ------------------------------------------------------------ visibility

    public function test_students_cannot_see_an_unpublished_draft(): void
    {
        $draft = $this->makeHomework(['is_published' => false, 'published_at' => null]);

        $this->actAs('student@synapse.test');

        $titles = collect($this->getJson('/api/student/homework')->assertOk()->json('data'))
            ->pluck('assignment.id')
            ->all();

        $this->assertNotContains($draft->id, $titles);
    }

    public function test_publishing_makes_the_homework_visible_to_the_class(): void
    {
        $data = $this->createViaApi();

        $this->actAs('student@synapse.test');
        collect($this->getJson('/api/student/homework')->assertOk()->json('data'))
            ->pluck('assignment.id')
            ->tap(fn ($ids) => $this->assertNotContains($data['id'], $ids->all()));

        $this->actAs('teacher@synapse.test');
        $this->postJson("/api/teacher/homework/{$data['id']}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->actAs('student@synapse.test');
        collect($this->getJson('/api/student/homework')->assertOk()->json('data'))
            ->pluck('assignment.id')
            ->tap(fn ($ids) => $this->assertContains($data['id'], $ids->all()));
    }

    // ----------------------------------------------------------- submitting

    public function test_student_can_submit_and_then_replace_before_the_deadline(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('student@synapse.test');

        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'First attempt.'])
            ->assertCreated()
            ->assertJsonPath('data.attempts', 1)
            ->assertJsonPath('data.status', 'submitted');

        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'Second, better attempt.'])
            ->assertCreated()
            ->assertJsonPath('data.attempts', 2)
            ->assertJsonPath('data.content', 'Second, better attempt.');

        // One row per student per homework — re-submission updates, never inserts.
        $this->assertSame(1, HomeworkSubmission::query()
            ->where('homework_assignment_id', $homework->id)
            ->where('student_id', $this->john()->id)
            ->count());
    }

    public function test_submission_is_refused_after_the_deadline(): void
    {
        $closed = $this->makeHomework(['due_at' => now()->subDay()]);

        $this->actAs('student@synapse.test');

        $this->postJson("/api/student/homework/{$closed->id}/submit", ['content' => 'Too late.'])
            ->assertStatus(422);
    }

    public function test_student_cannot_submit_to_a_class_they_are_not_in(): void
    {
        $level2a = SchoolClass::where('name', 'Level 2A')->firstOrFail();

        // Mr. David teaches English in Level 2A too, so the homework is valid;
        // John simply is not enrolled there this year.
        $homework = $this->makeHomework(['class_id' => $level2a->id]);

        $this->actAs('student@synapse.test');

        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'Wrong class.'])
            ->assertStatus(403);
    }

    public function test_an_empty_answer_is_rejected(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('student@synapse.test');

        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
    }

    // --------------------------------------------------------------- grading

    public function test_teacher_can_grade_and_the_mark_is_returned_to_the_student(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('student@synapse.test');
        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'My essay.'])->assertCreated();

        $submission = HomeworkSubmission::where('homework_assignment_id', $homework->id)->firstOrFail();

        $this->actAs('teacher@synapse.test');
        $this->postJson("/api/teacher/homework-submissions/{$submission->id}/grade", [
            'score' => 18,
            'feedback' => 'Excellent reasoning.',
        ])
            ->assertOk()
            ->assertJsonPath('data.score', 18)
            ->assertJsonPath('data.status', 'graded');

        $this->assertSame(18.0, (float) $submission->fresh()->score);
        $this->assertSame('Excellent reasoning.', $submission->fresh()->feedback);
        $this->assertSame($this->david()->id, $submission->fresh()->graded_by);

        // The student sees the mark in their own list.
        $this->actAs('student@synapse.test');
        $row = collect($this->getJson('/api/student/homework')->assertOk()->json('data'))
            ->firstWhere('assignment.id', $homework->id);

        $this->assertSame(18.0, (float) $row['submission']['score']);
        $this->assertSame('Excellent reasoning.', $row['submission']['feedback']);
    }

    public function test_a_score_above_the_maximum_is_rejected(): void
    {
        $homework = $this->makeHomework(['max_score' => 20]);

        $this->actAs('student@synapse.test');
        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'My essay.'])->assertCreated();

        $submission = HomeworkSubmission::where('homework_assignment_id', $homework->id)->firstOrFail();

        $this->actAs('teacher@synapse.test');
        $this->postJson("/api/teacher/homework-submissions/{$submission->id}/grade", ['score' => 45])
            ->assertStatus(422)
            ->assertJsonValidationErrors('score');
    }

    public function test_a_graded_submission_can_no_longer_be_replaced(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('student@synapse.test');
        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'My essay.'])->assertCreated();

        $submission = HomeworkSubmission::where('homework_assignment_id', $homework->id)->firstOrFail();

        $this->actAs('teacher@synapse.test');
        $this->postJson("/api/teacher/homework-submissions/{$submission->id}/grade", ['score' => 12])->assertOk();

        $this->actAs('student@synapse.test');
        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'Overwriting a mark.'])
            ->assertStatus(422);

        $this->assertSame('My essay.', $submission->fresh()->content);
    }

    public function test_the_roster_reports_submission_progress(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('student@synapse.test');
        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'Johns essay.'])->assertCreated();

        $this->actAs('teacher@synapse.test');

        $this->getJson("/api/teacher/homework/{$homework->id}/submissions")
            ->assertOk()
            ->assertJsonPath('stats.total', 3)
            ->assertJsonPath('stats.submitted', 1)
            ->assertJsonPath('stats.graded', 0)
            ->assertJsonStructure([
                'assignment',
                'students',
                'stats' => ['total', 'submitted', 'graded', 'late'],
            ]);
    }

    // -------------------------------------------------------- authorisation

    public function test_a_teacher_cannot_touch_another_teachers_homework(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('sarah@synapse.test');

        $this->putJson("/api/teacher/homework/{$homework->id}", ['title' => 'Hijacked'])->assertStatus(403);
        $this->postJson("/api/teacher/homework/{$homework->id}/publish")->assertStatus(403);
        $this->deleteJson("/api/teacher/homework/{$homework->id}")->assertStatus(403);

        $this->assertDatabaseHas('homework_assignments', ['id' => $homework->id, 'title' => $homework->title]);
    }

    public function test_a_teacher_cannot_grade_submissions_for_another_teachers_homework(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('student@synapse.test');
        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'My essay.'])->assertCreated();

        $submission = HomeworkSubmission::where('homework_assignment_id', $homework->id)->firstOrFail();

        $this->actAs('sarah@synapse.test');

        $this->postJson("/api/teacher/homework-submissions/{$submission->id}/grade", ['score' => 5])
            ->assertStatus(403);

        $this->assertNull($submission->fresh()->score);
    }

    public function test_homework_never_leaks_across_tenants(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('teacher.saintalbert@synapse.test');

        $this->getJson('/api/teacher/homework')->assertOk()->assertJsonCount(0, 'data');

        // The tenant scope turns another school's id into a 404, not a 403.
        $this->getJson("/api/teacher/homework/{$homework->id}")->assertNotFound();
        $this->getJson("/api/teacher/homework/{$homework->id}/submissions")->assertNotFound();

        $this->actAs('student.saintalbert@synapse.test');
        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => 'Other school.'])
            ->assertNotFound();

        $this->assertSame(
            0,
            HomeworkSubmission::where('homework_assignment_id', $homework->id)->count(),
        );
    }

    // ----------------------------------------------------------- attachments

    /**
     * A minimal, genuinely valid PDF — enough bytes for the upload pipeline to
     * store and stream back without depending on any document fixture.
     */
    private function fakePdf(string $name = 'brief.pdf'): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->create($name, 12, 'application/pdf');
    }

    public function test_a_teacher_can_attach_a_brief_document_to_homework(): void
    {
        $this->actAs('teacher@synapse.test');

        $response = $this->postJson('/api/teacher/homework', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'Homework with a document',
            'instructions' => 'Read the attached brief.',
            'max_score' => 20,
            'due_at' => now()->addDays(4)->toIso8601String(),
            'attachments' => [$this->fakePdf()],
        ])->assertCreated();

        $this->assertCount(1, $response->json('data.attachments'));
        $this->assertSame('brief.pdf', $response->json('data.attachments.0.file_name'));
        $this->assertSame('class', $response->json('data.attachments.0.visibility'));

        $this->assertDatabaseHas('attachments', [
            'attachable_id' => $response->json('data.id'),
            'file_name' => 'brief.pdf',
            'visibility' => 'class',
        ]);
    }

    public function test_an_enrolled_student_can_download_the_brief(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('teacher@synapse.test');
        $attachmentId = $this->postJson('/api/teacher/homework', [
            'class_id' => $homework->class_id,
            'subject_id' => $homework->subject_id,
            'title' => $homework->title.' brief',
            'max_score' => 20,
            'due_at' => now()->addDays(4)->toIso8601String(),
            'attachments' => [$this->fakePdf()],
        ])->assertCreated()->json('data.attachments.0.id');

        $this->actAs('student@synapse.test');

        $this->get("/api/attachments/{$attachmentId}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/teacher/homework', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'Homework with a bad file',
            'max_score' => 20,
            'due_at' => now()->addDays(4)->toIso8601String(),
            'attachments' => [
                \Illuminate\Http\UploadedFile::fake()->create('payload.exe', 4, 'application/x-msdownload'),
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('attachments.0');
    }

    public function test_a_student_can_submit_a_document_instead_of_text(): void
    {
        $homework = $this->makeHomework();

        $this->actAs($this->john()->user);

        $response = $this->postJson("/api/student/homework/{$homework->id}/submit", [
            'attachments' => [$this->fakePdf('my-essay.pdf')],
        ])->assertCreated();

        $this->assertCount(1, $response->json('data.attachments'));
        $this->assertSame('private', $response->json('data.attachments.0.visibility'));

        $this->assertDatabaseHas('homework_submissions', [
            'homework_assignment_id' => $homework->id,
            'student_id' => $this->john()->id,
        ]);
    }

    public function test_a_submission_with_neither_text_nor_file_is_rejected(): void
    {
        $homework = $this->makeHomework();

        $this->actAs($this->john()->user);

        $this->postJson("/api/student/homework/{$homework->id}/submit", ['content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
    }

    public function test_a_private_submission_cannot_be_read_by_another_student(): void
    {
        $homework = $this->makeHomework();

        $this->actAs($this->john()->user);
        $attachmentId = $this->postJson("/api/student/homework/{$homework->id}/submit", [
            'content' => 'Johns essay.',
            'attachments' => [$this->fakePdf('johns-work.pdf')],
        ])->assertCreated()->json('data.attachments.0.id');

        // Mary is in the same class, so she may read a class brief — but not
        // another student's private submission.
        $this->actAs($this->mary()->user);

        $this->get("/api/attachments/{$attachmentId}/download")->assertForbidden();
    }

    public function test_the_teacher_who_set_the_work_can_read_a_private_submission(): void
    {
        $homework = $this->makeHomework();

        $this->actAs($this->john()->user);
        $attachmentId = $this->postJson("/api/student/homework/{$homework->id}/submit", [
            'attachments' => [$this->fakePdf('johns-work.pdf')],
        ])->assertCreated()->json('data.attachments.0.id');

        $this->actAs('teacher@synapse.test');

        $this->get("/api/attachments/{$attachmentId}/download")->assertOk();
    }

    public function test_attachments_never_leak_across_tenants(): void
    {
        $homework = $this->makeHomework();

        $this->actAs('teacher@synapse.test');
        $attachmentId = $this->postJson('/api/teacher/homework', [
            'class_id' => $homework->class_id,
            'subject_id' => $homework->subject_id,
            'title' => $homework->title.' cross-tenant',
            'max_score' => 20,
            'due_at' => now()->addDays(4)->toIso8601String(),
            'attachments' => [$this->fakePdf()],
        ])->assertCreated()->json('data.attachments.0.id');

        $this->actAs('teacher.saintalbert@synapse.test');
        $this->get("/api/attachments/{$attachmentId}/download")->assertNotFound();

        $this->actAs('student.saintalbert@synapse.test');
        $this->get("/api/attachments/{$attachmentId}/download")->assertNotFound();
    }

    public function test_a_student_only_sees_homework_for_their_own_class(): void
    {
        $mine = $this->makeHomework();
        $level2a = SchoolClass::where('name', 'Level 2A')->firstOrFail();
        $theirs = $this->makeHomework(['class_id' => $level2a->id]);

        $this->actAs('student@synapse.test');

        $ids = collect($this->getJson('/api/student/homework')->assertOk()->json('data'))
            ->pluck('assignment.id')
            ->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }
}
