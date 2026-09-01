<?php

namespace App\Services;

use App\Contracts\ImportHeaderMapper;
use App\Models\School;
use App\Services\Import\ClassResolver;
use App\Services\Import\ValueNormaliser;

/**
 * Maps a raw CSV onto importable rows, and previews the result before anything
 * is written.
 *
 * The existing import endpoint already reports per-row errors, but only after it
 * has tried to create each account — so a French-headed spreadsheet produced a
 * wall of "name is required" messages for a file that was perfectly readable.
 * This answers the question beforehand: which column is which, which class each
 * pupil lands in, and which rows will fail and why.
 *
 * Nothing here writes. `preview()` returns a description; the administrator
 * confirms it and the existing endpoint does the importing.
 */
class ImportMappingService
{
    /**
     * Canonical fields accepted per import type. `class` and `academic_year`
     * arrive as labels and leave as ids.
     *
     * @var array<string, list<string>>
     */
    public const FIELDS = [
        'students' => ['name', 'email', 'matricule', 'class', 'academic_year', 'phone', 'password'],
        'teachers' => ['name', 'email', 'staff_no', 'phone', 'password'],
    ];

    public function __construct(
        private readonly ImportHeaderMapper $mapper,
        private readonly ValueNormaliser $normaliser,
        private readonly ClassResolver $classes,
    ) {}

    /**
     * Describe what an import would do, without doing it.
     *
     * @param  array<int, array<string, mixed>>  $rows  Keyed by the file's own header text
     * @return array<string, mixed>
     */
    public function preview(School $school, array $rows, string $type = 'students'): array
    {
        $fields = self::FIELDS[$type] ?? self::FIELDS['students'];
        $headers = $this->headersFrom($rows);

        $mapped = $this->mapper->map($headers, $fields);

        $previewRows = [];
        $importable = 0;

        foreach (array_values($rows) as $index => $row) {
            $values = $this->apply($row, $mapped['mapping'], $type);

            $class = $type === 'students'
                ? $this->classes->resolve($school, (string) ($values['class'] ?? ''))
                : ['class_id' => null, 'matched' => null, 'ambiguous' => false];

            $warnings = $this->warnings($values, $class, $type);

            if ($warnings === []) {
                $importable++;
            }

            $previewRows[] = [
                'row' => $index + 1,
                'values' => [
                    'name' => $values['name'] ?? null,
                    'email' => $values['email'] ?? null,
                    'matricule' => $values['matricule'] ?? null,
                    'staff_no' => $values['staff_no'] ?? null,
                    'phone' => $values['phone'] ?? null,
                    'class_id' => $class['class_id'],
                ],
                'class' => [
                    'label' => $values['class'] ?? null,
                    'matched' => $class['matched'],
                    'ambiguous' => $class['ambiguous'],
                ],
                'warnings' => $warnings,
            ];
        }

        return [
            'type' => $type,
            'source' => $this->mapper->name(),
            'fields' => $fields,
            'headers' => $headers,
            'mapping' => $mapped['mapping'],
            'confidence' => $mapped['confidence'],
            'unmapped' => $mapped['unmapped'],
            'conflicts' => (object) $mapped['conflicts'],
            'available_classes' => $type === 'students' ? $this->classes->available($school) : [],
            'rows' => $previewRows,
            'summary' => [
                'total' => count($previewRows),
                'importable' => $importable,
                'needs_attention' => count($previewRows) - $importable,
            ],
        ];
    }

    /**
     * Rewrite raw rows into the canonical shape `ImportService` expects, using a
     * mapping the administrator has confirmed.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $mapping  field => original header
     * @return array<int, array<string, mixed>>
     */
    public function applyMapping(array $rows, array $mapping, string $type = 'students'): array
    {
        return array_map(
            function (array $row) use ($mapping, $type) {
                $values = $this->apply($row, $mapping, $type);

                return array_filter($values, fn ($value) => $value !== null);
            },
            array_values($rows),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    private function apply(array $row, array $mapping, string $type): array
    {
        $pick = fn (string $field) => isset($mapping[$field])
            ? $row[$mapping[$field]] ?? null
            : null;

        $values = [
            'name' => $this->normaliser->text($this->scalar($pick('name'))),
            'email' => $this->normaliser->email($this->scalar($pick('email'))),
            'phone' => $this->normaliser->phone($this->scalar($pick('phone'))),
            'password' => $this->normaliser->text($this->scalar($pick('password'))),
        ];

        if ($type === 'teachers') {
            $values['staff_no'] = $this->normaliser->text($this->scalar($pick('staff_no')));

            return $values;
        }

        $values['matricule'] = $this->normaliser->text($this->scalar($pick('matricule')));

        // Kept as the raw label; resolution to an id needs the school and
        // happens where the school is known.
        $values['class'] = $this->normaliser->text($this->scalar($pick('class')));
        $values['academic_year'] = $this->normaliser->text($this->scalar($pick('academic_year')));

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array{class_id: int|null, matched: string|null, ambiguous: bool}  $class
     * @return list<string>
     */
    private function warnings(array $values, array $class, string $type): array
    {
        $warnings = [];

        if (($values['name'] ?? null) === null) {
            $warnings[] = 'No name — no column mapped to "name", or the cell is empty.';
        }

        if (($values['email'] ?? null) === null) {
            $warnings[] = 'No email address.';
        } elseif (! filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $warnings[] = '"'.$values['email'].'" is not a valid email address.';
        }

        if ($type !== 'students') {
            return $warnings;
        }

        if ($class['ambiguous']) {
            $warnings[] = 'The class "'.$values['class'].'" matches more than one class in this school.';
        } elseif ($class['class_id'] === null) {
            $warnings[] = 'No class in this school matches "'.$values['class'].'".';
        }

        return $warnings;
    }

    /**
     * The header row, taken from the first non-empty row's keys.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<string>
     */
    private function headersFrom(array $rows): array
    {
        foreach ($rows as $row) {
            if (is_array($row) && $row !== []) {
                return array_map('strval', array_keys($row));
            }
        }

        return [];
    }

    private function scalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
