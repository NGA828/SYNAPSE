<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One score per (grade, component). The weighted average is computed from
     * these rows; the legacy test1/test2/exam columns remain as a fallback
     * for subjects without configured components.
     */
    public function up(): void
    {
        Schema::create('grade_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('grade_components')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['grade_id', 'component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_scores');
    }
};
