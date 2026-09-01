<?php

namespace App\Services\Ai;

use App\Contracts\CommentWriter;

/**
 * Rule-based report-card comments.
 *
 * This is the default writer, not a stub waiting to be replaced. It composes a
 * comment from the specific facts on the card — the average, the mention, the
 * rank, the strongest subject and the subjects below the pass mark — so two
 * students with the same average but different subject spreads no longer
 * receive an identical sentence.
 *
 * It is also the fallback: every provider failure lands here, so a report card
 * always renders. Being deterministic means the same card produces the same
 * comment every time, which is what a school needs from a legal document.
 */
class DeterministicCommentWriter implements CommentWriter
{
    public function name(): string
    {
        return 'deterministic';
    }

    public function write(CommentEvidence $evidence): string
    {
        if (! $evidence->hasGrades()) {
            return $evidence->locale === 'fr'
                ? 'Aucune note enregistrée pour cette période.'
                : 'No marks have been recorded for this period.';
        }

        $sentences = array_filter([
            $this->overall($evidence),
            $this->rank($evidence),
            $this->strength($evidence),
            $this->concern($evidence),
            $this->outlook($evidence),
        ]);

        return implode(' ', $sentences);
    }

    private function overall(CommentEvidence $evidence): string
    {
        $average = $this->format($evidence->average);
        $scale = $this->format((float) $evidence->scale);

        return $evidence->locale === 'fr'
            ? "Moyenne générale de {$average} sur {$scale} — {$evidence->mention}."
            : "Overall average of {$average} out of {$scale} — {$evidence->mention}.";
    }

    private function rank(CommentEvidence $evidence): string
    {
        if (! $evidence->hasMeaningfulRank()) {
            return '';
        }

        return $evidence->locale === 'fr'
            ? "Classé {$evidence->rank}e sur {$evidence->classSize} dans la classe."
            : 'Ranked '.$this->ordinal($evidence->rank)." of {$evidence->classSize} in the class.";
    }

    private function strength(CommentEvidence $evidence): string
    {
        $strongest = $evidence->strongest();

        // Naming a strength only helps if it is not also the concern.
        if (! $strongest || count($evidence->subjects) < 2) {
            return '';
        }

        $weakest = $evidence->weakest();

        if ($weakest && $weakest['name'] === $strongest['name']) {
            return '';
        }

        $name = $strongest['name'];
        $score = $this->format((float) $strongest['average']);

        return $evidence->locale === 'fr'
            ? "Résultat le plus solide en {$name} ({$score})."
            : "Strongest result in {$name} ({$score}).";
    }

    private function concern(CommentEvidence $evidence): string
    {
        if ($evidence->failing === []) {
            return '';
        }

        $limit = (int) config('synapse.comments.max_named_subjects', 3);
        $named = collect($evidence->failing)->take($limit);

        $listed = $named
            ->map(function (string $subject) use ($evidence): string {
                $score = collect($evidence->subjects)->firstWhere('name', $subject)['average'] ?? null;

                return $score === null ? $subject : "{$subject} ({$this->format((float) $score)})";
            })
            ->implode($evidence->locale === 'fr' ? ', ' : ' and ');

        $pass = $this->format($evidence->passMark);

        return $evidence->locale === 'fr'
            ? "Sous la moyenne de passage de {$pass} en : {$listed}."
            : "Below the {$pass} pass mark in {$listed}.";
    }

    /**
     * A closing clause that follows from the numbers rather than from a fixed
     * ladder of four sentences.
     */
    private function outlook(CommentEvidence $evidence): string
    {
        $fr = $evidence->locale === 'fr';
        $pass = $evidence->passMark;
        $average = (float) $evidence->average;

        if ($average >= $pass * 1.6) {
            return $fr
                ? 'Un travail excellent : il s’agit maintenant de maintenir ce niveau.'
                : 'Excellent work; the task now is to hold this level.';
        }

        if ($average >= $pass * 1.2) {
            return $fr
                ? 'Des résultats solides ; un effort régulier permettrait encore de progresser.'
                : 'Solid results, and consistent effort would lift the average further.';
        }

        if ($average >= $pass) {
            return $fr
                ? 'Résultats satisfaisants ; une révision plus régulière renforcerait les matières les plus faibles.'
                : 'Satisfactory overall, with more regular revision needed in the weaker subjects.';
        }

        return $fr
            ? 'Un accompagnement supplémentaire est recommandé, en priorité dans les matières signalées ci-dessus.'
            : 'Additional support is recommended, starting with the subjects named above.';
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function ordinal(int $rank): string
    {
        $mod100 = $rank % 100;

        if ($mod100 >= 11 && $mod100 <= 13) {
            return $rank.'th';
        }

        return $rank.match ($rank % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
