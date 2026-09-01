<?php

namespace Tests\Unit;

use App\Services\Import\DeterministicHeaderMapper;
use App\Services\Import\ValueNormaliser;
use PHPUnit\Framework\TestCase;

/**
 * CSV header mapping and value normalisation (Phase 7.2).
 *
 * Two properties matter more than coverage of the alias table:
 *
 * - A header in either language maps to the right field, because a Cameroonian
 *   school's export says "Nom" and not "Name".
 * - A header the table has never seen is reported **unmapped** rather than
 *   guessed. A missing column is a question for the administrator; a wrong one
 *   silently writes the wrong data.
 */
class HeaderMapperTest extends TestCase
{
    private const STUDENT_FIELDS = ['name', 'email', 'matricule', 'class', 'academic_year', 'phone', 'password'];

    private function mapper(): DeterministicHeaderMapper
    {
        return new DeterministicHeaderMapper();
    }

    public function test_english_headers_map_exactly(): void
    {
        $result = $this->mapper()->map(
            ['Name', 'Email', 'Matricule', 'Class', 'Phone'],
            self::STUDENT_FIELDS,
        );

        $this->assertSame([
            'name' => 'Name',
            'email' => 'Email',
            'matricule' => 'Matricule',
            'class' => 'Class',
            'phone' => 'Phone',
        ], $result['mapping']);

        $this->assertSame([], $result['unmapped']);
        $this->assertSame('exact', $result['confidence']['name']);
    }

    /**
     * The regression this whole phase exists for: a French export used to
     * produce a file of nulls and a wall of per-row errors.
     */
    public function test_french_headers_map_exactly(): void
    {
        $result = $this->mapper()->map(
            ['Nom', 'Courriel', 'Matricule', 'Classe', 'Téléphone'],
            self::STUDENT_FIELDS,
        );

        $this->assertSame([
            'name' => 'Nom',
            'email' => 'Courriel',
            'matricule' => 'Matricule',
            'class' => 'Classe',
            'phone' => 'Téléphone',
        ], $result['mapping']);

        $this->assertSame([], $result['unmapped']);
    }

    public function test_accents_and_punctuation_do_not_break_matching(): void
    {
        $result = $this->mapper()->map(
            ['Numéro d\'élève', 'Adresse e-mail', 'Année scolaire'],
            self::STUDENT_FIELDS,
        );

        $this->assertSame('matricule', array_search("Numéro d'élève", $result['mapping'], true));
        $this->assertSame('email', array_search('Adresse e-mail', $result['mapping'], true));
        $this->assertSame('academic_year', array_search('Année scolaire', $result['mapping'], true));
    }

    public function test_an_unlisted_but_readable_header_is_found_fuzzily(): void
    {
        $result = $this->mapper()->map(
            ['Nom & Prénoms de l\'élève', 'Full Name of Student'],
            ['name'],
        );

        $this->assertSame('name', $result['mapping']['name'] ?? null);
        $this->assertSame('fuzzy', $result['confidence']['name'] ?? null);
    }

    public function test_a_shared_word_does_not_decide_a_column(): void
    {
        // "student" appears in `name`'s vocabulary and "phone" in `phone`'s, so
        // this scores one for each. A tie must leave it unmapped, not guess.
        $result = $this->mapper()->map(['Student Phone'], self::STUDENT_FIELDS);

        $this->assertSame([], $result['mapping']);
        $this->assertSame(['Student Phone'], $result['unmapped']);
    }

    public function test_a_single_stray_match_is_not_enough(): void
    {
        $result = $this->mapper()->map(['Nom du directeur général'], self::STUDENT_FIELDS);

        $this->assertSame([], $result['mapping']);
        $this->assertSame(['Nom du directeur général'], $result['unmapped']);
    }

    public function test_an_unknown_column_is_reported_not_dropped(): void
    {
        $result = $this->mapper()->map(['Name', 'Parent Occupation'], self::STUDENT_FIELDS);

        $this->assertSame(['name' => 'Name'], $result['mapping']);
        $this->assertSame(['Parent Occupation'], $result['unmapped']);
    }

    public function test_two_columns_claiming_one_field_are_a_conflict_not_a_coin_toss(): void
    {
        $result = $this->mapper()->map(['Name', 'Full Name'], ['name', 'email']);

        $this->assertSame('Name', $result['mapping']['name'], 'The exact match claims the field first.');
        $this->assertSame(['Full Name'], $result['conflicts']['name']);
    }

    public function test_an_exact_match_beats_a_fuzzy_one_for_the_same_field(): void
    {
        $result = $this->mapper()->map(['Nom de l\'élève', 'Nom'], ['name']);

        $this->assertSame('Nom', $result['mapping']['name']);
        $this->assertSame('exact', $result['confidence']['name']);
    }

    public function test_only_the_fields_this_import_accepts_are_considered(): void
    {
        // `class` is a student field; a teacher import must not claim it.
        $result = $this->mapper()->map(['Name', 'Classe'], ['name', 'email', 'staff_no']);

        $this->assertSame(['name' => 'Name'], $result['mapping']);
        $this->assertSame(['Classe'], $result['unmapped']);
    }

    public function test_the_mapper_reports_which_implementation_ran(): void
    {
        $this->assertSame('deterministic', $this->mapper()->name());
    }

    // ------------------------------------------------------------- normalising

    private function normaliser(): ValueNormaliser
    {
        return new ValueNormaliser();
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function phoneProvider(): array
    {
        return [
            'bare nine digits' => ['690123456', '+237690123456'],
            'international with spaces' => ['+237 690 123 456', '+237690123456'],
            'double-zero prefix' => ['00237690123456', '+237690123456'],
            'parentheses and dots' => ['(+237) 6.90.12.34.56', '+237690123456'],
            'landline' => ['222123456', '+237222123456'],
        ];
    }

    /**
     * @dataProvider phoneProvider
     */
    public function test_cameroonian_numbers_are_normalised_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normaliser()->phone($input));
    }

    public function test_an_unrecognisable_number_is_passed_through_not_guessed(): void
    {
        // Six digits is not a Cameroonian number. Prefixing it would mean texting
        // whoever happens to own that number, so it is left alone.
        $this->assertSame('123456', $this->normaliser()->phone('123456'));
    }

    public function test_an_empty_phone_is_null_not_an_empty_string(): void
    {
        $this->assertNull($this->normaliser()->phone('   '));
        $this->assertNull($this->normaliser()->phone(null));
    }

    public function test_emails_are_lowercased_and_trimmed(): void
    {
        $this->assertSame('ngo.bassa@aics.cm', $this->normaliser()->email('  Ngo.Bassa@AICS.cm '));
        $this->assertNull($this->normaliser()->email('  '));
    }

    /**
     * Folding accents is for matching headers and class names only. Stripping
     * them out of a person's name would corrupt the record being created.
     */
    public function test_names_keep_their_accents(): void
    {
        $this->assertSame('Ngo Bassa', $this->normaliser()->text('  Ngo Bassa  '));
        $this->assertSame('Élodie Mbarga', $this->normaliser()->text('Élodie Mbarga'));
    }

    public function test_the_match_key_folds_accents_for_comparison_only(): void
    {
        $normaliser = $this->normaliser();

        $this->assertSame('level 3a', $normaliser->matchKey('  Level  3A '));
        $this->assertSame($normaliser->matchKey('Seconde A'), $normaliser->matchKey('seconde a'));
        $this->assertSame($normaliser->matchKey('Seconde A'), $normaliser->matchKey('SECONDE-A'));
    }
}
