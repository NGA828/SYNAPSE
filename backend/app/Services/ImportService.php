<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly RegistrationService $registrations,
        private readonly AuditService $audit,
    ) {}

    /**
     * Bulk-import students from parsed rows.
     *
     * Each row: name, email, matricule, class_id (optional), password (optional).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, skipped: int, errors: array<int, array<string, mixed>>}
     */
    public function importStudents(School $school, array $rows, ?User $actor = null): array
    {
        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $this->subscriptions->assertCanCreate($school, 'students');

                $data = [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    // Never a shared default: each account gets its own one-time password.
                    'password' => $row['password'] ?? $this->registrations->temporaryPassword(),
                    'matricule' => $row['matricule'],
                    'phone' => $row['phone'] ?? null,
                    'class_id' => $row['class_id'] ?? null,
                    'academic_year_id' => $row['academic_year_id'] ?? AcademicYear::current()?->id,
                ];

                if (! $data['class_id']) {
                    throw ValidationException::withMessages(['class_id' => ['A class is required for each imported student.']]);
                }

                $this->registrations->registerStudent($school, $data, $actor);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'name' => $row['name'] ?? null,
                    'message' => $e instanceof ValidationException
                        ? implode(' ', collect($e->errors())->flatten()->all())
                        : $e->getMessage(),
                ];
            }
        }

        $this->audit->log($school, $actor, 'students.imported', 'students', null, [
            'created' => $created,
            'errors' => count($errors),
        ]);

        return [
            'created' => $created,
            'skipped' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Bulk-import teachers from parsed rows.
     *
     * Each row: name, email, staff_no (optional), password (optional).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, skipped: int, errors: array<int, array<string, mixed>>}
     */
    public function importTeachers(School $school, array $rows, ?User $actor = null): array
    {
        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $this->subscriptions->assertCanCreate($school, 'teachers');

                $data = [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => $row['password'] ?? Str::random(10),
                    'staff_no' => $row['staff_no'] ?? null,
                ];

                $this->registrations->registerTeacher($school, $data, $actor);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'name' => $row['name'] ?? null,
                    'message' => $e instanceof ValidationException
                        ? implode(' ', collect($e->errors())->flatten()->all())
                        : $e->getMessage(),
                ];
            }
        }

        $this->audit->log($school, $actor, 'teachers.imported', 'teachers', null, [
            'created' => $created,
            'errors' => count($errors),
        ]);

        return [
            'created' => $created,
            'skipped' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Parse an uploaded CSV file into rows (first line = header).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function parseCsv(string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($contents));
        $header = str_getcsv(array_shift($lines) ?? '');

        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            $row = [];

            foreach ($header as $i => $column) {
                $row[strtolower(trim($column))] = $values[$i] ?? null;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
