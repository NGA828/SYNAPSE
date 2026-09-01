<?php

namespace App\Services\Import;

use App\Contracts\ImportHeaderMapper;
use Illuminate\Support\Str;

/**
 * Rule-based header mapping. Handles the realistic cases — English and French
 * school exports — and says so when it cannot.
 *
 * Matching is done on a normalised key (lowercase, accents folded, punctuation
 * removed) against an alias table, with a token-overlap fallback for headers
 * nobody thought to list. Nothing here is probabilistic in a way an
 * administrator cannot check: every match reports how it was reached.
 */
class DeterministicHeaderMapper implements ImportHeaderMapper
{
    /**
     * Canonical field => aliases. Order within a field does not matter; the
     * first header that matches exactly wins over one that matches fuzzily.
     *
     * @var array<string, list<string>>
     */
    public const ALIASES = [
        'name' => [
            'name', 'full name', 'names', 'student name', 'pupil name', 'surname',
            'name of student', 'nom', 'noms', 'nom complet', 'nom et prenom',
            'nom de l eleve', 'eleve', 'nom et prenoms',
        ],
        'email' => [
            'email', 'e mail', 'mail', 'electronic mail', 'email address',
            'courriel', 'adresse courriel', 'adresse e mail', 'adresse electronique', 'mel',
        ],
        'matricule' => [
            'matricule', 'matricule number', 'student number', 'student id',
            'registration number', 'reg no', 'numero d eleve', 'numero eleve',
            'code eleve', 'matricule eleve',
        ],
        'staff_no' => [
            'staff no', 'staff number', 'staff id', 'teacher number', 'teacher id',
            'numero du personnel', 'matricule enseignant', 'numero enseignant',
        ],
        'class' => [
            'class', 'class name', 'classe', 'level', 'niveau', 'form',
            'nom de la classe', 'classe eleve',
        ],
        'academic_year' => [
            'academic year', 'year', 'session', 'annee', 'annee scolaire', 'exercice',
        ],
        'phone' => [
            'phone', 'phone number', 'telephone', 'tel', 'mobile', 'cell',
            'contact', 'numero de telephone', 'telephone portable',
        ],
        'password' => [
            'password', 'temporary password', 'mot de passe', 'mot de passe temporaire',
        ],
    ];

    public function name(): string
    {
        return 'deterministic';
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     * @return array{mapping: array<string, string>, unmapped: list<string>, conflicts: array<string, list<string>>, confidence: array<string, string>}
     */
    public function map(array $headers, array $fields): array
    {
        $mapping = [];
        $confidence = [];
        $conflicts = [];
        $used = [];

        // Pass 1: exact alias matches. These are unambiguous, so they claim
        // their field first and a fuzzier header cannot displace them.
        foreach ($headers as $header) {
            $field = $this->exactField($header, $fields);

            if ($field === null) {
                continue;
            }

            if (isset($mapping[$field])) {
                $conflicts[$field][] = $header;

                continue;
            }

            $mapping[$field] = $header;
            $confidence[$field] = 'exact';
            $used[] = $this->normalise($header);
        }

        // Pass 2: token overlap, only for fields still unclaimed.
        foreach ($headers as $header) {
            $normalised = $this->normalise($header);

            if (in_array($normalised, $used, true)) {
                continue;
            }

            $field = $this->fuzzyField($header, $fields, array_keys($mapping));

            if ($field === null) {
                continue;
            }

            if (isset($mapping[$field])) {
                $conflicts[$field][] = $header;

                continue;
            }

            $mapping[$field] = $header;
            $confidence[$field] = 'fuzzy';
            $used[] = $normalised;
        }

        $unmapped = array_values(array_filter(
            $headers,
            fn (string $header) => ! in_array($header, $mapping, true),
        ));

        return [
            'mapping' => $mapping,
            'unmapped' => $unmapped,
            'conflicts' => $conflicts,
            'confidence' => $confidence,
        ];
    }

    /**
     * @param  list<string>  $fields
     */
    private function exactField(string $header, array $fields): ?string
    {
        $normalised = $this->normalise($header);

        foreach ($fields as $field) {
            foreach (self::ALIASES[$field] ?? [] as $alias) {
                if ($this->normalise($alias) === $normalised) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * Token-overlap fallback, scored rather than all-or-nothing.
     *
     * Each field scores the number of header tokens that appear anywhere in its
     * alias vocabulary. The winner must be unique and must cover at least half
     * the header. Both conditions matter:
     *
     * - Half coverage stops a single stray word claiming a column. "Nom du
     *   directeur" shares only "nom" with `name` and is reported unmapped.
     * - Uniqueness stops a shared word deciding it. "Student Phone" scores one
     *   for `name` (from "student name") and one for `phone`, so it ties and is
     *   left unmapped rather than guessed. An unmapped column is a visible
     *   question; a wrongly mapped one silently imports the wrong data.
     *
     * @param  list<string>  $fields
     * @param  list<string>  $claimed
     */
    private function fuzzyField(string $header, array $fields, array $claimed): ?string
    {
        $tokens = $this->tokens($header);

        if ($tokens === []) {
            return null;
        }

        $scores = [];

        foreach ($fields as $field) {
            if (in_array($field, $claimed, true)) {
                continue;
            }

            $vocabulary = [];

            foreach (self::ALIASES[$field] ?? [] as $alias) {
                $vocabulary = array_merge($vocabulary, $this->tokens($alias));
            }

            $vocabulary = array_unique($vocabulary);

            $score = count(array_intersect($tokens, $vocabulary));

            if ($score > 0) {
                $scores[$field] = $score;
            }
        }

        if ($scores === []) {
            return null;
        }

        $best = max($scores);
        $winners = array_keys(array_filter($scores, fn (int $score) => $score === $best));

        if (count($winners) !== 1) {
            return null;
        }

        return $best >= (int) ceil(count($tokens) / 2) ? $winners[0] : null;
    }

    /**
     * Folding accents is for *matching headers only*. Values are never
     * transliterated — stripping the accents out of a pupil's name would be
     * corrupting the data we were asked to import.
     */
    private function normalise(string $value): string
    {
        $ascii = Str::ascii($value);

        return (string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($ascii));
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        return array_values(array_filter(
            explode(' ', trim($this->normalise($value))),
            fn (string $token) => $token !== '',
        ));
    }
}
