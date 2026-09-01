<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attachment;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Course materials — authoring, publishing, and the authorisation and tenancy
 * rules that decide who may read a lesson or download its files.
 *
 * Like HomeworkTest, every test builds its own fixture instead of relying on
 * whichever seeded row happens to come back first.
 */
class LessonTest extends TestCase
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

    /** Mr. David (TCH-001) teaches English and History in Level 3A. */
    private function david(): Teacher
    {
        return Teacher::where('staff_no', 'TCH-001')->firstOrFail();
    }

    /** Enrolled in Level 3A for the current year. */
    private function john(): Student
    {
        return Student::where('matricule', 'ST2026045')->firstOrFail();
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

    private function makeLesson(array $overrides = []): Lesson
    {
        return Lesson::create(array_merge([
            'school_id' => $this->level3a()->school_id,
            'teacher_id' => $this->david()->id,
            'subject_id' => $this->english()->id,
            'class_id' => $this->level3a()->id,
            'academic_year_id' => $this->year()->id,
            'semester_id' => Semester::query()->where('academic_year_id', $this->year()->id)->value('id'),
            'title' => 'Fixture lesson '.uniqid(),
            'topic' => 'Essay Writing',
            'summary' => 'How to build an argument.',
            'body' => 'A thesis is a claim a reader could reasonably disagree with.',
            'minutes' => null,
            'sequence' => 1,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function fakePdf(string $name = 'slides.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 12, 'application/pdf');
    }

    // ------------------------------------------------------------- authoring

    public function test_a_lesson_is_created_as_an_unpublished_draft(): void
    {
        $this->actAs('teacher@synapse.test');

        $response = $this->postJson('/api/teacher/materials', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'Building a Thesis',
            'topic' => 'Essay Writing',
            'body' => 'Start from your conclusion and work backwards.',
        ])->assertCreated();

        $this->assertFalse($response->json('data.is_published'));
        $this->assertNull($response->json('data.published_at'));
        $this->assertSame('Building a Thesis', $response->json('data.title'));
        $this->assertSame('Essay Writing', $response->json('data.topic'));
    }

    public function test_a_teacher_cannot_publish_into_a_class_they_do_not_teach(): void
    {
        $this->actAs('teacher@synapse.test');

        // Mr. David teaches English, not Mathematics.
        $this->postJson('/api/teacher/materials', [
            'class_id' => $this->level3a()->id,
            'subject_id' => Subject::where('code', 'MAT')->value('id'),
            'title' => 'Quadratics',
        ])->assertForbidden();
    }

    public function test_a_duplicate_title_for_the_same_class_and_subject_is_rejected(): void
    {
        $lesson = $this->makeLesson();

        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/teacher/materials', [
            'class_id' => $lesson->class_id,
            'subject_id' => $lesson->subject_id,
            'title' => $lesson->title,
        ])->assertStatus(422)->assertJsonValidationErrors('title');
    }

    public function test_reading_time_is_estimated_when_not_supplied(): void
    {
        $this->actAs('teacher@synapse.test');

        // 400 words at 200 wpm.
        $body = str_repeat('word ', 400);

        $response = $this->postJson('/api/teacher/materials', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'Long reading '.uniqid(),
            'body' => trim($body),
        ])->assertCreated();

        $this->assertSame(2, $response->json('data.minutes'));
    }

    public function test_publishing_makes_the_lesson_visible_to_the_class(): void
    {
        $lesson = $this->makeLesson(['is_published' => false, 'published_at' => null]);

        $this->actAs('student@synapse.test');
        $ids = $this->materialsLessonIds();
        $this->assertNotContains($lesson->id, $ids);

        $this->actAs('teacher@synapse.test');
        $this->postJson("/api/teacher/materials/{$lesson->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->actAs('student@synapse.test');
        $this->assertContains($lesson->id, $this->materialsLessonIds());
    }

    public function test_an_unpublished_draft_is_refused_to_students(): void
    {
        $lesson = $this->makeLesson(['is_published' => false, 'published_at' => null]);

        $this->actAs('student@synapse.test');

        $this->getJson("/api/student/materials/{$lesson->id}")->assertForbidden();
    }

    public function test_a_lesson_is_grouped_by_subject_and_topic_for_students(): void
    {
        $this->makeLesson(['topic' => 'Poetry']);

        $this->actAs('student@synapse.test');

        $data = $this->getJson('/api/student/materials')->assertOk()->json('data');

        $this->assertArrayHasKey('English', $data);
        $this->assertArrayHasKey('Poetry', $data['English']);
        $this->assertNotEmpty($data['English']['Poetry']);
    }

    public function test_the_student_list_omits_the_full_body_but_the_detail_includes_it(): void
    {
        $lesson = $this->makeLesson(['body' => 'The full text of the lesson.']);

        $this->actAs('student@synapse.test');

        $listing = $this->getJson('/api/student/materials')->assertOk()->json('data');
        $this->assertArrayNotHasKey('body', $listing['English']['Essay Writing'][0]);

        $detail = $this->getJson("/api/student/materials/{$lesson->id}")->assertOk()->json('data');
        $this->assertSame('The full text of the lesson.', $detail['body']);
    }

    public function test_a_student_cannot_read_a_lesson_from_another_class(): void
    {
        $level2a = SchoolClass::where('name', 'Level 2A')->firstOrFail();

        // David teaches English in Level 2A too, so the assignment guard passes
        // and only the enrolment rule can stop the student.
        $theirs = $this->makeLesson(['class_id' => $level2a->id]);

        // A student enrolled in Level 2A for the current year, so the refusal
        // comes from class membership rather than the academic year.
        $other = $this->enrolledInLevel2a();

        $this->actAs($other->user);
        $this->getJson("/api/student/materials/{$theirs->id}")->assertOk();

        $this->actAs('student@synapse.test');
        $this->getJson("/api/student/materials/{$theirs->id}")->assertForbidden();
    }

    public function test_a_teacher_cannot_touch_another_teachers_lesson(): void
    {
        $lesson = $this->makeLesson();

        $this->actAs('sarah@synapse.test');

        $this->putJson("/api/teacher/materials/{$lesson->id}", ['title' => 'Hijacked'])->assertForbidden();
        $this->deleteJson("/api/teacher/materials/{$lesson->id}")->assertForbidden();
        $this->postJson("/api/teacher/materials/{$lesson->id}/publish")->assertForbidden();
        $this->getJson("/api/teacher/materials/{$lesson->id}")->assertForbidden();
    }

    public function test_the_class_and_subject_are_frozen_after_creation(): void
    {
        $lesson = $this->makeLesson();
        $level2a = SchoolClass::where('name', 'Level 2A')->firstOrFail();

        $this->actAs('teacher@synapse.test');

        $this->putJson("/api/teacher/materials/{$lesson->id}", [
            'title' => 'Renamed lesson',
            'class_id' => $level2a->id,
        ])->assertOk();

        $this->assertSame('Renamed lesson', $lesson->fresh()->title);
        $this->assertSame($this->level3a()->id, $lesson->fresh()->class_id);
    }

    public function test_deleting_a_lesson_removes_its_stored_files(): void
    {
        $lesson = $this->makeLesson();

        $this->actAs('teacher@synapse.test');
        $id = $this->postJson('/api/teacher/materials', [
            'class_id' => $lesson->class_id,
            'subject_id' => $lesson->subject_id,
            'title' => 'To be deleted '.uniqid(),
            'attachments' => [$this->fakePdf()],
        ])->assertCreated()->json('data.id');

        $attachmentId = Attachment::query()
            ->where('attachable_type', Lesson::class)
            ->where('attachable_id', $id)
            ->value('id');

        $this->assertNotNull($attachmentId);

        $this->deleteJson("/api/teacher/materials/{$id}")->assertOk();

        $this->assertDatabaseMissing('lessons', ['id' => $id]);
        $this->assertDatabaseMissing('attachments', ['id' => $attachmentId]);
    }

    // ----------------------------------------------------------- attachments

    public function test_a_teacher_can_attach_slides_to_a_lesson(): void
    {
        $this->actAs('teacher@synapse.test');

        $response = $this->postJson('/api/teacher/materials', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'Lesson with slides '.uniqid(),
            'attachments' => [$this->fakePdf()],
        ])->assertCreated();

        $this->assertSame('slides.pdf', $response->json('data.attachments.0.file_name'));
        $this->assertSame('class', $response->json('data.attachments.0.visibility'));

        $this->assertDatabaseHas('attachments', [
            'attachable_id' => $response->json('data.id'),
            'attachable_type' => Lesson::class,
            'visibility' => 'class',
        ]);
    }

    public function test_an_enrolled_student_can_download_a_lesson_file(): void
    {
        $lesson = $this->makeLesson();

        $this->actAs('teacher@synapse.test');
        $attachmentId = $this->putJson("/api/teacher/materials/{$lesson->id}", [
            'attachments' => [$this->fakePdf('worksheet.pdf')],
        ])->assertOk()->json('data.attachments.0.id');

        $this->actAs('student@synapse.test');

        $response = $this->get("/api/attachments/{$attachmentId}/download")->assertOk();
        $this->assertStringContainsString('worksheet.pdf', $response->headers->get('content-disposition'));
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/teacher/materials', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'Bad file type '.uniqid(),
            'attachments' => [UploadedFile::fake()->create('payload.exe', 12, 'application/x-msdownload')],
        ])->assertStatus(422)->assertJsonValidationErrors('attachments.0');
    }

    public function test_a_lesson_file_never_leaks_across_tenants(): void
    {
        $lesson = $this->makeLesson();

        $this->actAs('teacher@synapse.test');
        $attachmentId = $this->putJson("/api/teacher/materials/{$lesson->id}", [
            'attachments' => [$this->fakePdf()],
        ])->assertOk()->json('data.attachments.0.id');

        $this->actAs('teacher.saintalbert@synapse.test');
        $this->get("/api/attachments/{$attachmentId}/download")->assertNotFound();

        $this->actAs('student.saintalbert@synapse.test');
        $this->get("/api/attachments/{$attachmentId}/download")->assertNotFound();
    }

    public function test_lessons_never_leak_across_tenants(): void
    {
        $lesson = $this->makeLesson();

        $this->actAs('teacher.saintalbert@synapse.test');

        $this->getJson('/api/teacher/materials')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/teacher/materials/{$lesson->id}")->assertNotFound();
    }

    // ------------------------------------------------------------- summaries

    public function test_the_student_summary_counts_lessons_subjects_and_files(): void
    {
        $lesson = $this->makeLesson();

        $this->actAs('teacher@synapse.test');
        $this->putJson("/api/teacher/materials/{$lesson->id}", [
            'attachments' => [$this->fakePdf()],
        ])->assertOk();

        $this->actAs('student@synapse.test');

        $summary = $this->getJson('/api/student/materials')->assertOk()->json('summary');

        $this->assertGreaterThanOrEqual(1, $summary['lessons']);
        $this->assertGreaterThanOrEqual(1, $summary['subjects']);
        $this->assertGreaterThanOrEqual(1, $summary['files']);
    }

    public function test_an_unauthenticated_request_is_refused(): void
    {
        $this->getJson('/api/student/materials')->assertUnauthorized();
        $this->getJson('/api/teacher/materials')->assertUnauthorized();
    }

    // --------------------------------------------------------------- helpers

    /**
     * @return list<int>
     */
    private function materialsLessonIds(): array
    {
        $data = $this->getJson('/api/student/materials')->assertOk()->json('data');

        $ids = [];

        foreach ($data as $topics) {
            foreach ($topics as $lessons) {
                foreach ($lessons as $lesson) {
                    $ids[] = $lesson['id'];
                }
            }
        }

        return $ids;
    }

    /**
     * A student enrolled in Level 2A for the current year. The seeded Level 2A
     * enrolment belongs to the previous year, so this one is created here to
     * keep the class-membership assertion independent of the academic year.
     */
    private function enrolledInLevel2a(): Student
    {
        $level2a = SchoolClass::where('name', 'Level 2A')->firstOrFail();

        $user = User::create([
            'school_id' => $level2a->school_id,
            'name' => 'Other Class Student',
            'email' => 'otherclass@synapse.test',
            'password' => 'password',
            'role' => User::ROLE_STUDENT,
        ]);

        $student = Student::create([
            'school_id' => $level2a->school_id,
            'user_id' => $user->id,
            'matricule' => 'ST2026900',
        ]);

        Enrollment::create([
            'school_id' => $level2a->school_id,
            'student_id' => $student->id,
            'class_id' => $level2a->id,
            'academic_year_id' => $this->year()->id,
        ]);

        return $student->load('user');
    }
}
