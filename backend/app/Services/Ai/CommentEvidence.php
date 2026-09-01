<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

/**
 * The facts a comment may describe.
 *
 * Deliberately free of identity: there is no student name, no matricule and no
 * school name here, because this object is what gets serialised to an external
 * provider. A writer that wants a name has no way to obtain one, which is a
 * stronger guarantee than redacting it later.
 *
 * Everything in here is computed by GradeService and config('synapse.grading');
 * nothing is derived by the writer.
 */
final readonly class CommentEvidence
{
    /**
     * @param  list<array{name: string, average: float|null}>  $subjects
     * @param  list<string>  $failing  Subject names below the pass mark, worst first.
     */
    public function __construct(
        public ?float $average,
        public int $scale,
        public float $passMark,
        public string $mention,
        public ?int $rank,
        public int $classSize,
        public array $subjects,
        public array $failing,
        public string $locale,
    ) {}

    /**
     * @param  array<string, mixed>  $reportCard  Output of GradeService::reportCard()
     */
    public static function fromReportCard(array $reportCard, ?string $locale = null): self
    {
        $scale = (float) config('synapse.grading.scale', 20);
        $passMark = (float) config('synapse.grading.pass_mark', 10);
        $average = $reportCard['average'] ?? null;

        $subjects = collect($reportCard['grades'] ?? [])
            ->map(fn (array $grade): array => [
                'name' => (string) ($grade['subject'] ?? '—'),
                'average' => $grade['average'] !== null ? round((float) $grade['average'], 2) : null,
            ])
            ->filter(fn (array $row): bool => $row['average'] !== null)
            ->values()
            ->all();

        $failing = collect($subjects)
            ->filter(fn (array $row): bool => $row['average'] < $passMark)
            ->sortBy('average')
            ->pluck('name')
            ->values()
            ->all();

        return new self(
            average: $average !== null ? round((float) $average, 2) : null,
            scale: $scale === (int) $scale ? (int) $scale : $scale,
            passMark: $passMark,
            mention: $average === null ? '—' : self::mentionFor((float) $average),
            rank: isset($reportCard['rank']) ? (int) $reportCard['rank'] : null,
            classSize: (int) ($reportCard['class_size'] ?? 0),
            subjects: $subjects,
            failing: $failing,
            locale: Str::lower($locale ?: config('app.locale', 'en')) === 'fr' ? 'fr' : 'en',
        );
    }

    /**
     * The configured mention label. Read from the same config GradeService uses
     * so the two can never disagree on a boundary.
     */
    private static function mentionFor(float $average): string
    {
        foreach (config('synapse.grading.mentions', []) as $mention) {
            if ($average >= (float) ($mention['min'] ?? 0)) {
                return (string) $mention['label'];
            }
        }

        return '—';
    }

    public function strongest(): ?array
    {
        return collect($this->subjects)->sortByDesc('average')->first();
    }

    public function weakest(): ?array
    {
        return collect($this->subjects)->sortBy('average')->first();
    }

    public function hasGrades(): bool
    {
        return $this->average !== null && $this->subjects !== [];
    }

    /**
     * A rank is only worth mentioning when there is a class to be ranked in.
     */
    public function hasMeaningfulRank(): bool
    {
        return $this->rank !== null && $this->classSize > 1;
    }
}
