<?php

namespace Tests\Unit;

use App\Services\Ai\CommentEvidence;
use App\Services\Ai\DeterministicCommentWriter;
use Tests\TestCase;

/**
 * The deterministic comment writer.
 *
 * Before this existed, `ReportCardPresenter::comment()` returned one of four
 * hard-coded sentences and nothing tested it — every student with a 14.5 got
 * the identical text in every subject. These tests cover both the improvement
 * (comments now respond to the actual spread of marks) and the invariant that
 * matters most: a writer may describe numbers, but may never invent one.
 */
class DeterministicCommentWriterTest extends TestCase
{
    private function writer(): DeterministicCommentWriter
    {
        return new DeterministicCommentWriter();
    }

    /**
     * @param  list<array{name: string, average: float}>  $subjects
     */
    private function evidence(
        array $subjects,
        ?float $average = null,
        ?int $rank = null,
        int $classSize = 0,
        string $locale = 'en',
    ): CommentEvidence {
        config()->set('synapse.grading.scale', 20);
        config()->set('synapse.grading.pass_mark', 10);
        config()->set('synapse.grading.mentions', [
            ['label' => 'Excellent', 'min' => 18],
            ['label' => 'Very Good', 'min' => 16],
            ['label' => 'Good', 'min' => 14],
            ['label' => 'Fairly Good', 'min' => 12],
            ['label' => 'Average', 'min' => 10],
            ['label' => 'Insufficient', 'min' => 0],
        ]);
        config()->set('synapse.comments.max_named_subjects', 3);

        return CommentEvidence::fromReportCard([
            'average' => $average ?? (count($subjects)
                ? round(array_sum(array_column($subjects, 'average')) / count($subjects), 2)
                : null),
            'rank' => $rank,
            'class_size' => $classSize,
            'grades' => array_map(
                fn (array $row) => ['subject' => $row['name'], 'average' => $row['average']],
                $subjects,
            ),
        ], $locale);
    }

    // ------------------------------------------------------------- specificity

    public function test_the_same_average_with_a_different_spread_says_something_different(): void
    {
        // Both average 12, but the weaknesses are different subjects.
        $balanced = $this->writer()->write($this->evidence([
            ['name' => 'English', 'average' => 12.0],
            ['name' => 'Mathematics', 'average' => 12.0],
        ]));

        $spiky = $this->writer()->write($this->evidence([
            ['name' => 'English', 'average' => 16.0],
            ['name' => 'Mathematics', 'average' => 8.0],
        ]));

        $this->assertNotSame($balanced, $spiky, 'Identical averages must not produce identical comments.');
        $this->assertStringContainsString('Mathematics', $spiky);
        $this->assertStringContainsString('English', $spiky);
    }

    public function test_a_pupil_with_no_weakness_is_not_told_they_have_one(): void
    {
        $comment = $this->writer()->write($this->evidence([
            ['name' => 'English', 'average' => 16.0],
            ['name' => 'Mathematics', 'average' => 17.0],
        ]));

        $this->assertStringNotContainsString('pass mark in', $comment);
    }

    // ---------------------------------------------------- never invent a number

    public function test_every_number_in_the_comment_comes_from_the_evidence(): void
    {
        $subjects = [
            ['name' => 'English', 'average' => 16.5],
            ['name' => 'Mathematics', 'average' => 8.25],
            ['name' => 'Database', 'average' => 14.0],
        ];

        $evidence = $this->evidence($subjects, average: 12.92, rank: 2, classSize: 31);
        $comment = $this->writer()->write($evidence);

        $allowed = ['20', '10', '12.92', '2', '31', '16.5', '8.25', '14', '1', '3'];

        preg_match_all('/\d+(?:\.\d+)?/', $comment, $matches);

        foreach ($matches[0] as $number) {
            $this->assertContains(
                $number,
                $allowed,
                "The comment contains {$number}, which is not in the evidence: {$comment}",
            );
        }
    }

    public function test_a_rank_is_only_quoted_when_there_is_a_class_to_be_ranked_in(): void
    {
        $alone = $this->writer()->write($this->evidence(
            [['name' => 'English', 'average' => 14.0]],
            rank: 1,
            classSize: 1,
        ));

        $this->assertStringNotContainsString('Ranked', $alone);

        $inClass = $this->writer()->write($this->evidence(
            [['name' => 'English', 'average' => 14.0]],
            rank: 3,
            classSize: 28,
        ));

        $this->assertStringContainsString('Ranked 3rd of 28', $inClass);
    }

    public function test_ordinals_are_english_not_naive(): void
    {
        foreach ([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 11 => '11th', 12 => '12th', 13 => '13th', 21 => '21st', 22 => '22nd'] as $rank => $ordinal) {
            $comment = $this->writer()->write($this->evidence(
                [['name' => 'English', 'average' => 14.0]],
                rank: $rank,
                classSize: 40,
            ));

            $this->assertStringContainsString("Ranked {$ordinal} of 40", $comment, "rank {$rank}");
        }
    }

    // ----------------------------------------------------------------- limits

    public function test_failing_subjects_are_named_but_capped(): void
    {
        $comment = $this->writer()->write($this->evidence([
            ['name' => 'English', 'average' => 4.0],
            ['name' => 'Mathematics', 'average' => 5.0],
            ['name' => 'Physics', 'average' => 6.0],
            ['name' => 'Chemistry', 'average' => 7.0],
            ['name' => 'Database', 'average' => 18.0],
        ]));

        // Worst three only, so the sentence stays readable.
        $this->assertStringContainsString('English', $comment);
        $this->assertStringContainsString('Mathematics', $comment);
        $this->assertStringContainsString('Physics', $comment);
        $this->assertStringNotContainsString('Chemistry', $comment);
    }

    public function test_a_single_subject_does_not_claim_a_strongest_result(): void
    {
        $comment = $this->writer()->write($this->evidence([
            ['name' => 'English', 'average' => 14.0],
        ]));

        $this->assertStringNotContainsString('Strongest result', $comment);
    }

    public function test_no_marks_produces_a_statement_rather_than_a_number(): void
    {
        $comment = $this->writer()->write($this->evidence([]));

        $this->assertSame('No marks have been recorded for this period.', $comment);
    }

    // ------------------------------------------------------------------ french

    public function test_the_french_locale_produces_french(): void
    {
        $comment = $this->writer()->write($this->evidence(
            [
                ['name' => 'Anglais', 'average' => 16.0],
                ['name' => 'Mathématiques', 'average' => 8.0],
            ],
            rank: 2,
            classSize: 30,
            locale: 'fr',
        ));

        $this->assertStringContainsString('Moyenne générale', $comment);
        $this->assertStringContainsString('Classé 2e sur 30', $comment);
        $this->assertStringContainsString('Mathématiques', $comment);
        $this->assertStringNotContainsString('Overall average', $comment);
    }

    public function test_an_unknown_locale_falls_back_to_english(): void
    {
        $comment = $this->writer()->write($this->evidence(
            [['name' => 'English', 'average' => 14.0]],
            locale: 'de',
        ));

        $this->assertStringContainsString('Overall average', $comment);
    }

    // --------------------------------------------------------------- evidence

    public function test_evidence_carries_no_student_identity(): void
    {
        $evidence = $this->evidence([['name' => 'English', 'average' => 14.0]]);

        $serialised = json_encode($evidence);

        $this->assertIsString($serialised);

        foreach (['name', 'matricule', 'school', 'student_id', 'email'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $serialised,
                "Evidence must not carry {$forbidden}; it is what gets sent to a provider.",
            );
        }
    }

    public function test_the_mention_comes_from_configuration_not_a_hard_coded_ladder(): void
    {
        config()->set('synapse.grading.mentions', [
            ['label' => 'Outstanding', 'min' => 15],
            ['label' => 'Developing', 'min' => 0],
        ]);

        $evidence = CommentEvidence::fromReportCard([
            'average' => 15.0,
            'grades' => [['subject' => 'English', 'average' => 15.0]],
        ], 'en');

        $this->assertSame('Outstanding', $evidence->mention);
    }
}
