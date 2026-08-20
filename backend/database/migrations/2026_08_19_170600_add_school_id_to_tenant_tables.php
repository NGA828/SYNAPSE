<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant-owned tables that gain a `school_id` column.
     *
     * @var list<string>
     */
    private const TENANT_TABLES = [
        'students',
        'teachers',
        'classes',
        'subjects',
        'academic_years',
        'enrollments',
        'teaching_assignments',
        'grades',
        'timetable_entries',
        'requests',
        'documents',
        'announcements',
        'notifications',
    ];

    /**
     * Run the migrations (safe for existing data).
     */
    public function up(): void
    {
        $defaultSchoolId = $this->defaultSchoolId();

        // users: nullable school_id — platform super admins have none.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->after('id');
        });
        DB::table('users')->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('school_id')->references('id')->on('schools')->nullOnDelete();
        });

        foreach (self::TENANT_TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('school_id')->nullable()->after('id');
            });

            DB::table($table)->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);

            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('school_id')->nullable(false)->change();
                $table->foreign('school_id')->references('id')->on('schools');
            });
        }

        $this->schoolScopeUniques();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->restoreGlobalUniques();

        foreach (self::TENANT_TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }

    /**
     * Resolve (or create) the default school used to backfill legacy rows.
     */
    private function defaultSchoolId(): int
    {
        $existing = DB::table('schools')->orderBy('id')->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('schools')->insertGetId([
            'name' => 'SYNAPSE Default School',
            'slug' => 'default',
            'status' => 'trial',
            'timezone' => 'Africa/Douala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Convert globally-unique columns to school-scoped uniqueness.
     */
    private function schoolScopeUniques(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique('classes_name_unique');
            $table->unique(['school_id', 'name']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_name_unique');
            $table->dropUnique('subjects_code_unique');
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'code']);
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropUnique('academic_years_name_unique');
            $table->unique(['school_id', 'name']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_matricule_unique');
            $table->unique(['school_id', 'matricule']);
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropUnique('teachers_staff_no_unique');
            $table->unique(['school_id', 'staff_no']);
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->dropUnique('requests_reference_unique');
            $table->unique(['school_id', 'reference']);
        });
    }

    /**
     * Restore the original global unique constraints on rollback.
     */
    private function restoreGlobalUniques(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'name']);
            $table->unique('name');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'name']);
            $table->dropUnique(['school_id', 'code']);
            $table->unique('name');
            $table->unique('code');
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'name']);
            $table->unique('name');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'matricule']);
            $table->unique('matricule');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'staff_no']);
            $table->unique('staff_no');
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'reference']);
            $table->unique('reference');
        });
    }
};
