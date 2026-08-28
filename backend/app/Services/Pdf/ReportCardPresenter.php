<?php

namespace App\Services\Pdf;

use Illuminate\Support\Str;

/**
 * Turns the raw arrays produced by GradeService into the flat, column-aligned
 * structure the report-card template renders (one column per grade component).
 */
class ReportCardPresenter
{
    /**
     * @param  array<string, mixed>  $reportCard  Output of GradeService::reportCard()
     * @return array<string, mixed>
     */
    public function present(array $reportCard): array
    {
        $grades = collect($reportCard['grades'] ?? []);

        $components = $this->columns($grades);
        $scale = (float) config('synapse.grading.scale', 20);

        $rows = $grades->map(function ($grade) use ($components, $scale) {
            $scores = [];

            foreach ($grade['components'] ?? [] as $component) {
                $scores[$this->key($component['name'] ?? '')] = $this->format($component['score'] ?? null);
            }

            // Legacy grades (pre grade-components) still carry test1/test2/exam.
            foreach (['test1' => 'Test 1', 'test2' => 'Test 2', 'exam' => 'Exam'] as $field => $label) {
                if (($grade[$field] ?? null) !== null) {
                    $scores[$this->key($label)] = $this->format($grade[$field]);
                }
            }

            return [
                'subject' => $grade['subject'] ?? '—',
                'code' => $grade['subject_code'] ?? null,
                'scores' => $scores,
                'average' => $grade['average'] !== null ? number_format((float) $grade['average'], 2) : null,
                'remark' => $this->remark($grade['average'] ?? null, $scale),
            ];
        })->values()->all();

        $average = $reportCard['average'] ?? null;

        return [
            'rows' => $rows,
            'components' => $components,
            'average' => $average,
            'scale' => $scale == (int) $scale ? (int) $scale : $scale,
            'mention' => $average === null ? '—' : $this->mention((float) $average),
            'subjects_count' => count($rows),
            'comment' => $this->comment($average, $reportCard['rank'] ?? null),
        ];
    }

    /**
     * Union of component columns across every subject, ordered by weight.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $grades
     * @return array<int, array{id: string, name: string, weight: mixed}>
     */
    private function columns($grades): array
    {
        $columns = [];

        foreach ($grades as $grade) {
            foreach ($grade['components'] ?? [] as $component) {
                $name = $component['name'] ?? null;

                if (! $name) {
                    continue;
                }

                $columns[$this->key($name)] ??= [
                    'id' => $this->key($name),
                    'name' => $name,
                    'weight' => $component['weight'] ?? '—',
                ];
            }

            foreach (['test1' => 'Test 1', 'test2' => 'Test 2', 'exam' => 'Exam'] as $field => $label) {
                if (($grade[$field] ?? null) !== null) {
                    $columns[$this->key($label)] ??= ['id' => $this->key($label), 'name' => $label, 'weight' => '—'];
                }
            }
        }

        return array_values($columns);
    }

    private function key(string $name): string
    {
        return Str::slug($name) ?: md5($name);
    }

    private function format(mixed $score): string
    {
        return $score === null ? '—' : number_format((float) $score, 2);
    }

    private function remark(mixed $average, float $scale): string
    {
        if ($average === null) {
            return '—';
        }

        $pass = (float) config('synapse.grading.pass_mark', $scale / 2);

        return (float) $average >= $pass ? 'Pass' : 'Fail';
    }

    public function mention(float $average): string
    {
        foreach (config('synapse.grading.mentions', []) as $mention) {
            if ($average >= (float) $mention['min']) {
                return $mention['label'];
            }
        }

        return '—';
    }

    private function comment(mixed $average, mixed $rank): ?string
    {
        if ($average === null) {
            return null;
        }

        $mention = $this->mention((float) $average);
        $rankText = $rank ? " Ranked {$rank} in class." : '';

        return match (true) {
            (float) $average >= 16 => "Outstanding work — keep it up.{$rankText}",
            (float) $average >= 12 => "Satisfactory results overall.{$rankText} Consistent effort will lift the average further.",
            (float) $average >= 10 => "Average performance.{$rankText} More regular revision is needed.",
            default => "Results are below the pass mark.{$rankText} Remedial support is strongly recommended.",
        };
    }
}
