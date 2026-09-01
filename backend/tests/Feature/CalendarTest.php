<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Exam;
use App\Models\HomeworkAssignment;
use App\Models\Quiz;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The personal calendar — one read-only projection of the timetable, exams,
 * homework due dates, quiz deadlines and school events.
 *
 * These tests run against the seeded school, so the ranges are chosen relative
 * to today rather than hard-coded to a date.
 */
class CalendarTest extends TestCase
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

    private function from(): string
    {
        return now()->subDays(10)->toDateString();
    }

    private function to(): string
    {
        return now()->addDays(20)->toDateString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsFor(string $email): array
    {
        $this->actAs($email);

        return $this->getJson('/api/calendar?from='.$this->from().'&to='.$this->to())->json('data');
    }

    // ----------------------------------------------------------------- shape

    public function test_every_item_carries_the_same_shape(): void
    {
        $items = $this->itemsFor('student@synapse.test');

        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertArrayHasKey('kind', $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('starts_at', $item);
            $this->assertArrayHasKey('all_day', $item);
            $this->assertArrayHasKey('url', $item);
        }
    }

    public function test_items_are_ordered_by_start_time(): void
    {
        $items = $this->itemsFor('student@synapse.test');

        $starts = array_column($items, 'starts_at');
        $sorted = $starts;
        sort($sorted);

        $this->assertSame($sorted, $starts);
    }

    public function test_the_calendar_merges_every_source(): void
    {
        $kinds = array_unique(array_column($this->itemsFor('student@synapse.test'), 'kind'));

        foreach (['lesson', 'event', 'homework', 'quiz'] as $expected) {
            $this->assertContains($expected, $kinds, "Expected the calendar to include {$expected} items.");
        }
    }

    // -------------------------------------------------------------- timetable

    public function test_a_recurring_timetable_row_is_expanded_onto_real_weekdays(): void
    {
        $items = $this->itemsFor('student@synapse.test');

        $lessonDates = array_unique(array_map(
            fn (array $item) => substr($item['starts_at'], 0, 10),
            array_filter($items, fn (array $item) => $item['kind'] === 'lesson'),
        ));

        $this->assertNotEmpty($lessonDates);

        // The seeded timetable occupies Monday, Tuesday and Wednesday only.
        foreach ($lessonDates as $date) {
            $this->assertContains(
                (int) Carbon::parse($date)->dayOfWeekIso,
                [1, 2, 3],
                "A lesson was placed on {$date}, which the timetable does not cover.",
            );
        }

        // One row per matching weekday, not one row for the whole range.
        $this->assertGreaterThan(3, count($lessonDates));
    }

    public function test_exams_appear_on_their_scheduled_date(): void
    {
        $this->actAs('student@synapse.test');

        $exam = Exam::query()->firstOrFail();

        // Move it inside the window so the assertion does not depend on the
        // seeded date being near today.
        $exam->update(['date' => now()->addDays(3)->toDateString()]);

        $items = $this->itemsFor('student@synapse.test');

        $this->assertContains(
            'exam',
            array_column($items, 'kind'),
            'The exam did not reach the calendar.',
        );
    }

    // ------------------------------------------------------- homework & quiz

    public function test_only_published_homework_reaches_the_calendar(): void
    {
        $items = $this->itemsFor('student@synapse.test');

        $draftTitles = HomeworkAssignment::query()
            ->where('is_published', false)
            ->pluck('title')
            ->all();

        $calendarTitles = array_column(
            array_filter($items, fn (array $item) => $item['kind'] === 'homework'),
            'title',
        );

        $this->assertNotEmpty($calendarTitles);
        $this->assertSame([], array_intersect($draftTitles, $calendarTitles));
    }

    public function test_a_quiz_without_a_closing_time_is_not_a_deadline(): void
    {
        $items = $this->itemsFor('student@synapse.test');

        $openEnded = Quiz::query()
            ->whereNull('closes_at')
            ->pluck('title')
            ->all();

        $calendarTitles = array_column(
            array_filter($items, fn (array $item) => $item['kind'] === 'quiz'),
            'title',
        );

        $this->assertSame([], array_intersect($openEnded, $calendarTitles));
    }

    public function test_items_link_back_to_the_screen_that_owns_them(): void
    {
        $items = $this->itemsFor('student@synapse.test');

        $urls = [];
        foreach ($items as $item) {
            $urls[$item['kind']] = $item['url'];
        }

        $this->assertSame('/student/timetable', $urls['lesson'] ?? null);
        $this->assertSame('/student/homework', $urls['homework'] ?? null);
        $this->assertSame('/student/quizzes', $urls['quiz'] ?? null);
        $this->assertNull($urls['event'] ?? 'missing');
    }

    // ---------------------------------------------------------------- events

    public function test_a_draft_event_never_reaches_the_calendar(): void
    {
        $admin = User::where('email', 'admin@synapse.test')->firstOrFail();

        Event::create([
            'school_id' => $admin->school_id,
            'user_id' => $admin->id,
            'title' => 'Secret Draft Event',
            'type' => Event::TYPE_MEETING,
            'starts_at' => now()->addDays(2),
            'audience' => Event::AUDIENCE_ALL,
            'is_published' => false,
        ]);

        $this->assertNotContains(
            'Secret Draft Event',
            array_column($this->itemsFor('student@synapse.test'), 'title'),
        );
    }

    public function test_a_teacher_only_event_is_absent_from_a_student_calendar(): void
    {
        $admin = User::where('email', 'admin@synapse.test')->firstOrFail();

        Event::create([
            'school_id' => $admin->school_id,
            'user_id' => $admin->id,
            'title' => 'Marking Moderation',
            'type' => Event::TYPE_MEETING,
            'starts_at' => now()->addDays(2),
            'audience' => Event::AUDIENCE_TEACHERS,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertNotContains(
            'Marking Moderation',
            array_column($this->itemsFor('student@synapse.test'), 'title'),
        );

        $this->assertContains(
            'Marking Moderation',
            array_column($this->itemsFor('teacher@synapse.test'), 'title'),
        );
    }

    // ------------------------------------------------------------- per role

    public function test_a_teacher_sees_every_class_they_teach_not_just_the_first(): void
    {
        $items = $this->itemsFor('teacher@synapse.test');

        // Mr. David teaches English in Level 3A and Level 2A, and both classes
        // have timetable rows.
        $classes = array_unique(array_column(
            array_filter($items, fn (array $item) => $item['kind'] === 'lesson'),
            'subtitle',
        ));

        $this->assertContains('Level 3A', $classes);
        $this->assertContains('Level 2A', $classes, 'A second class was dropped from the calendar.');
    }

    public function test_a_teacher_does_not_see_subjects_they_do_not_teach(): void
    {
        $items = $this->itemsFor('teacher@synapse.test');

        // Mr. David teaches English and History only; Mathematics belongs to
        // Mrs. Sarah even though Level 3A has maths periods.
        $lessonTitles = array_unique(array_column(
            array_filter($items, fn (array $item) => $item['kind'] === 'lesson'),
            'title',
        ));

        $this->assertContains('English', $lessonTitles);
        $this->assertNotContains('Mathematics', $lessonTitles);
    }

    public function test_an_administrator_sees_school_events_only(): void
    {
        $items = $this->itemsFor('admin@synapse.test');

        $this->assertNotEmpty($items);
        $this->assertSame(['event'], array_unique(array_column($items, 'kind')));
    }

    public function test_another_schools_data_never_appears(): void
    {
        $admin = User::where('email', 'admin@synapse.test')->firstOrFail();

        Event::create([
            'school_id' => $admin->school_id,
            'user_id' => $admin->id,
            'title' => 'AICS Only Event',
            'type' => Event::TYPE_OTHER,
            'starts_at' => now()->addDays(2),
            'audience' => Event::AUDIENCE_ALL,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertNotContains(
            'AICS Only Event',
            array_column($this->itemsFor('teacher.saintalbert@synapse.test'), 'title'),
        );
    }

    // --------------------------------------------------------------- window

    public function test_the_range_is_echoed_back(): void
    {
        $this->actAs('student@synapse.test');

        $response = $this->getJson('/api/calendar?from='.$this->from().'&to='.$this->to());

        $response->assertOk();
        $this->assertSame($this->from(), $response->json('from'));
        $this->assertSame($this->to(), $response->json('to'));
    }

    public function test_omitting_a_range_defaults_to_the_current_week(): void
    {
        $this->actAs('student@synapse.test');

        $response = $this->getJson('/api/calendar');

        $response->assertOk();
        $this->assertSame(now()->startOfWeek()->toDateString(), $response->json('from'));
        $this->assertSame(now()->endOfWeek()->toDateString(), $response->json('to'));
    }

    public function test_an_absurd_range_is_clamped_rather_than_honoured(): void
    {
        $this->actAs('student@synapse.test');

        $response = $this->getJson('/api/calendar?from='.now()->toDateString().'&to='.now()->addYears(2)->toDateString());

        $span = Carbon::parse($response->json('from'))->diffInDays(Carbon::parse($response->json('to')));

        $this->assertLessThanOrEqual(92, $span);
    }

    public function test_an_invalid_date_is_rejected(): void
    {
        $this->actAs('student@synapse.test');

        $this->getJson('/api/calendar?from=not-a-date')->assertStatus(422);
    }

    public function test_today_returns_only_todays_items(): void
    {
        $this->actAs('student@synapse.test');

        $response = $this->getJson('/api/calendar/today');

        $response->assertOk();
        $this->assertSame(now()->toDateString(), $response->json('date'));

        foreach ($response->json('data') as $item) {
            $this->assertSame(now()->toDateString(), substr($item['starts_at'], 0, 10));
        }
    }

    public function test_a_guest_cannot_read_the_calendar(): void
    {
        $this->getJson('/api/calendar')->assertUnauthorized();
        $this->getJson('/api/calendar/today')->assertUnauthorized();
    }

    public function test_the_calendar_reads_without_writing_anything(): void
    {
        $before = [
            TimetableEntry::count(),
            Exam::count(),
            HomeworkAssignment::count(),
            Quiz::count(),
            Event::count(),
        ];

        $this->itemsFor('student@synapse.test');
        $this->itemsFor('teacher@synapse.test');
        $this->itemsFor('admin@synapse.test');

        $this->assertSame($before, [
            TimetableEntry::count(),
            Exam::count(),
            HomeworkAssignment::count(),
            Quiz::count(),
            Event::count(),
        ]);
    }
}
