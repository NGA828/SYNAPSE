<?php

namespace Tests\Unit;

use App\Services\Pdf\ReportCardPresenter;
use Tests\TestCase;

class ReportCardPresenterTest extends TestCase
{
    private function presenter(): ReportCardPresenter
    {
        // Resolved rather than constructed: the presenter now takes a
        // CommentService, and the container is the honest way to build it.
        return app(ReportCardPresenter::class);
    }

    private function reportCard(array $grades, ?float $average, ?int $rank = null): array
    {
        return [
            'grades' => collect($grades),
            'average' => $average,
            'rank' => $rank,
            'class_size' => 30,
        ];
    }

    public function test_component_columns_are_the_union_across_subjects(): void
    {
        $presented = $this->presenter()->present($this->reportCard([
            [
                'subject' => 'Mathematics',
                'subject_code' => 'MTH',
                'average' => 15.5,
                'components' => [
                    ['name' => 'Test 1', 'weight' => 30, 'score' => 14],
                    ['name' => 'Exam', 'weight' => 70, 'score' => 16],
                ],
            ],
            [
                'subject' => 'Biology',
                'subject_code' => 'BIO',
                'average' => 11,
                'components' => [
                    ['name' => 'Exam', 'weight' => 70, 'score' => 11],
                    ['name' => 'Practical', 'weight' => 30, 'score' => 11],
                ],
            ],
        ], 13.25));

        $columns = array_column($presented['components'], 'name');

        sort($columns);

        $this->assertSame(['Exam', 'Practical', 'Test 1'], $columns);
        $this->assertCount(2, $presented['rows']);
        $this->assertSame('—', $presented['rows'][0]['scores']['practical'] ?? '—');
    }

    public function test_missing_scores_render_as_a_dash_rather_than_zero(): void
    {
        $presented = $this->presenter()->present($this->reportCard([
            [
                'subject' => 'History',
                'subject_code' => 'HIS',
                'average' => null,
                'components' => [['name' => 'Exam', 'weight' => 100, 'score' => null]],
            ],
        ], null));

        $this->assertSame('—', $presented['rows'][0]['scores']['exam']);
        $this->assertNull($presented['rows'][0]['average']);
        $this->assertSame('—', $presented['rows'][0]['remark']);
        $this->assertSame('—', $presented['mention']);
    }

    public function test_legacy_grades_without_components_still_render(): void
    {
        $presented = $this->presenter()->present($this->reportCard([
            [
                'subject' => 'Chemistry',
                'subject_code' => 'CHM',
                'average' => 12.5,
                'test1' => 12,
                'test2' => 13,
                'exam' => 12.5,
                'components' => [],
            ],
        ], 12.5));

        $this->assertSame(['Test 1', 'Test 2', 'Exam'], array_column($presented['components'], 'name'));
        $this->assertSame('12.00', $presented['rows'][0]['scores']['test-1']);
    }

    public function test_pass_and_fail_remarks_follow_the_configured_pass_mark(): void
    {
        $presented = $this->presenter()->present($this->reportCard([
            ['subject' => 'A', 'subject_code' => 'A', 'average' => 9.99, 'components' => []],
            ['subject' => 'B', 'subject_code' => 'B', 'average' => 10, 'components' => []],
        ], 10.0));

        $this->assertSame('Fail', $presented['rows'][0]['remark']);
        $this->assertSame('Pass', $presented['rows'][1]['remark']);
    }
}
