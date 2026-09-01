<?php

namespace App\Contracts;

use App\Services\Ai\CommentEvidence;

/**
 * Writes a report-card comment from evidence that has already been computed.
 *
 * The contract is deliberately one-directional: a writer receives numbers and
 * returns prose. It has no access to the grade book and cannot recalculate
 * anything, because averages, ranks and mentions appear on a legal document and
 * are already correct in GradeService. A writer that could derive a number
 * could also get one wrong.
 */
interface CommentWriter
{
    /**
     * A stable identifier recorded against the comment, so provenance survives
     * and a school can see which writer produced what.
     */
    public function name(): string;

    public function write(CommentEvidence $evidence): string;
}
