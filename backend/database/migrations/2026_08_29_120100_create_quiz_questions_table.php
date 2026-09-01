<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quiz questions and the attempts students make.
 *
 * Options are a JSON array of strings and `correct_option` is the index of the
 * right one. Storing the index rather than the text means a teacher can fix a
 * typo in an option without invalidating the answer key — and it keeps the
 * student payload free of anything that identifies the correct answer, because
 * the resource simply omits this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->text('prompt');

            /** @var list<string> */
            $table->json('options');

            // Index into `options`. Never serialised to a student.
            $table->unsignedTinyInteger('correct_option');

            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('sequence')->default(0);

            $table->timestamps();

            $table->index(['quiz_id', 'sequence']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            /**
             * question_id => chosen option index. JSON rather than a row per
             * answer because an attempt is only ever read or written whole.
             */
            $table->json('answers');

            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('total_questions')->default(0);

            // Scaled onto the quiz's own max_score, like every other mark here.
            $table->decimal('score', 5, 2)->nullable();

            $table->unsignedInteger('attempt')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            // Teacher commentary added after the auto-mark, plus an explicit
            // release so results are not shown before the teacher is ready.
            $table->text('feedback')->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('teachers')->nullOnDelete();

            $table->timestamps();

            // One row per attempt number, so a second attempt is a new row and
            // the first is preserved for comparison.
            $table->unique(['quiz_id', 'student_id', 'attempt'], 'quiz_attempts_unique_per_number');
            $table->index(['student_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
    }
};
