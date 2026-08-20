<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Weighted grade components. A subject_id of NULL means the component is a
     * school-wide default applied to every subject unless overridden.
     */
    public function up(): void
    {
        Schema::create('grade_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('weight', 5, 2)->default(0); // percentage weight
            $table->unsignedTinyInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'subject_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_components');
    }
};
