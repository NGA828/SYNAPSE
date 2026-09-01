<?php

namespace App\Contracts;

/**
 * Maps a CSV header row onto canonical import fields.
 *
 * The problem this solves is concrete: `ImportService::parseCsv()` lowercases
 * the header and then matches by exact string, so a Cameroonian school that
 * exports `"Nom"`, `"Courriel"` and `"Classe"` produces a file full of nulls and
 * a wall of per-row errors — even though every column is plainly identifiable.
 *
 * Implementations must never guess a field they are unsure of. An unmapped
 * column is a visible question for the administrator; a wrongly-mapped one
 * silently writes the wrong data.
 */
interface ImportHeaderMapper
{
    /**
     * The implementation's identifier, surfaced to the administrator so they can
     * see whether a machine or a rule table produced the mapping.
     */
    public function name(): string;

    /**
     * @param  list<string>  $headers  The header row, exactly as written in the file
     * @param  list<string>  $fields   The canonical fields this import accepts
     * @return array{
     *     mapping: array<string, string>,
     *     unmapped: list<string>,
     *     conflicts: array<string, list<string>>,
     *     confidence: array<string, string>
     * }
     */
    public function map(array $headers, array $fields): array;
}
