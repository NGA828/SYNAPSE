<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report-card appreciations.
     *
     * A comment is drafted, then edited by a teacher, then locked into the PDF.
     * Storing it is what makes the human-in-the-loop real: without a row, a
     * generated draft is the final word and nobody has approved it.
     *
     * `subject_id` is nullable — a null row is the overall comment on the report
     * card, a non-null row is a per-subject appreciation.
     *
     * `source` records provenance so an AI-drafted comment that was never edited
     * can be told apart from one a teacher wrote, which matters both for audit
     * and for knowing whether the drafting is any good.
     */
    public function up(): void
    {
        Schema::create('report_card_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('source')->default('teacher')->index();
            $table->boolean('is_locked')->default(false);
            $table->foreignId('written_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['student_id', 'subject_id', 'academic_year_id', 'semester_id'],
                'report_card_comments_unique_per_period',
            );

            $table->index(['school_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_comments');
    }
};
