<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Grades become semester-scoped so each grading period can carry its own
     * average. Existing rows remain valid (semester_id is nullable).
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('academic_year_id')->constrained()->nullOnDelete();
            $table->index(['semester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropForeign(['semester_id']);
            $table->dropColumn('semester_id');
        });
    }
};
