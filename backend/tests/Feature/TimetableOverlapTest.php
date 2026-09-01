<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\TimetableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Timetable overlap detection (Phase 8.1).
 *
 * The gap: `timetable_slot_unique` covers `(class_id, academic_year_id, day,
 * start)` — an *identical* start. So a class could hold Mathematics 08:00–10:00
 * and English 09:00–11:00 on the same Monday and the database would accept both.
 * Nothing downstream checked either, so the clash was simply stored.
 *
 * The seeded Level 3A Monday is 08:00–10:00 English, 10:00–12:00 Networking,
 * 14:00–16:00 Mathematics.
 */
class TimetableOverlapTest extends TestCase
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

    private function level1a(): SchoolClass
    {
        return SchoolClass::where('name', 'Level 1A')->firstOrFail();
    }

    private function subject(string $name = 'Physics'): Subject
    {
        return Subject::where('name', $name)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->subject()->id,
            'day' => 1,
            'start' => '09:00',
            'end' => '11:00',
        ], $overrides);
    }

    // ------------------------------------------------------------- the defect

    public function test_a_slot_straddling_an_existing_one_is_refused(): void
    {
        $this->actAs('admin@synapse.test');

        // 09:00–11:00 crosses both the seeded 08:00–10:00 and 10:00–12:00.
        $response = $this->postJson('/api/admin/timetable/entries', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('start');

        $this->assertStringContainsString('English', $response->json('errors.start.0'));
    }

    public function test_the_refusal_names_the_lesson_and_the_window_it_occupies(): void
    {
        $this->actAs('admin@synapse.test');

        $message = $this->postJson('/api/admin/timetable/entries', $this->payload())
            ->assertStatus(422)
            ->json('errors.start.0');

        $this->assertStringContainsString('English', $message);
        $this->assertStringContainsString('08:00–10:00', $message);
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function overlapProvider(): array
    {
        return [
            'inside an existing slot' => ['08:30', '09:30', 'fully contained'],
            'swallowing an existing slot' => ['07:00', '13:00', 'fully containing'],
            'overlapping the end' => ['09:30', '10:30', 'tail overlap'],
            'overlapping the start' => ['07:30', '08:30', 'head overlap'],
            'sharing only a minute' => ['09:59', '10:30', 'one minute of overlap'],
            'identical' => ['08:00', '10:00', 'the same slot'],
        ];
    }

    /**
     * @dataProvider overlapProvider
     */
    public function test_every_kind_of_overlap_is_refused(string $start, string $end, string $case): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/timetable/entries', $this->payload(
            start: $start,
            end: $end,
        ))->assertStatus(422)->assertJsonValidationErrors('start');

        $this->assertTrue(true, $case);
    }

    // ------------------------------------------------------- what must still work

    public function test_back_to_back_lessons_are_allowed(): void
    {
        $this->actAs('admin@synapse.test');

        // Monday 12:00–13:00 touches nothing: the 10:00–12:00 slot ends exactly
        // when this one begins, and that is not an overlap.
        $this->postJson('/api/admin/timetable/entries', $this->payload(
            start: '12:00',
            end: '13:00',
        ))->assertCreated();
    }

    public function test_the_same_time_on_another_day_is_allowed(): void
    {
        $this->actAs('admin@synapse.test');

        // Thursday is empty in the seed, so 08:00–10:00 is free even though
        // Monday and Tuesday both have it taken.
        $this->postJson('/api/admin/timetable/entries', $this->payload(
            day: 4,
            start: '08:00',
            end: '10:00',
        ))->assertCreated();
    }

    public function test_the_same_time_in_another_class_is_allowed(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/timetable/entries', $this->payload(
            class_id: $this->level1a()->id,
            start: '08:00',
            end: '10:00',
        ))->assertCreated();
    }

    public function test_an_end_at_or_before_the_start_is_refused(): void
    {
        $service = app(TimetableService::class);

        foreach ([['10:00', '10:00'], ['11:00', '10:00']] as [$start, $end]) {
            try {
                $service->create([
                    'class_id' => $this->level3a()->id,
                    'subject_id' => $this->subject()->id,
                    'day' => 4,
                    'start' => $start,
                    'end' => $end,
                ]);

                $this->fail("{$start}–{$end} should have been refused.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('end', $exception->errors());
            }
        }
    }

    // ------------------------------------------------------------- on update

    public function test_moving_a_slot_onto_an_existing_one_is_refused(): void
    {
        $this->actAs('admin@synapse.test');

        $free = $this->postJson('/api/admin/timetable/entries', $this->payload(
            start: '12:00',
            end: '13:00',
        ))->assertCreated()->json('data.id');

        $this->putJson("/api/admin/timetable/entries/{$free}", $this->payload(
            start: '08:30',
            end: '09:30',
        ))->assertStatus(422)->assertJsonValidationErrors('start');
    }

    public function test_saving_an_entry_unchanged_is_not_a_clash_with_itself(): void
    {
        $this->actAs('admin@synapse.test');

        $entry = TimetableEntry::query()
            ->where('class_id', $this->level3a()->id)
            ->where('day', 1)
            ->orderBy('start')
            ->firstOrFail();

        $this->putJson("/api/admin/timetable/entries/{$entry->id}", [
            'class_id' => $entry->class_id,
            'subject_id' => $entry->subject_id,
            'day' => $entry->day,
            'start' => substr((string) $entry->start, 0, 5),
            'end' => substr((string) $entry->end, 0, 5),
        ])->assertOk();
    }

    // ---------------------------------------------------------------- tenancy

    public function test_a_class_from_another_school_is_refused_before_overlap_is_considered(): void
    {
        $this->actAs('admin.saintalbert@synapse.test');

        $this->postJson('/api/admin/timetable/entries', $this->payload(
            class_id: $this->level3a()->id,
        ))->assertStatus(422);
    }

    public function test_only_an_admin_can_edit_the_timetable(): void
    {
        $payload = $this->payload();

        $this->actAs('teacher@synapse.test');
        $this->postJson('/api/admin/timetable/entries', $payload)->assertForbidden();

        $this->actAs('student@synapse.test');
        $this->postJson('/api/admin/timetable/entries', $payload)->assertForbidden();
    }
}
