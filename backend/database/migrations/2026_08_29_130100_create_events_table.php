<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School events: assemblies, sports days, holidays, meetings, deadlines.
 *
 * An event differs from an announcement in that it occupies time — which is
 * what lets the personal calendar show it alongside lessons, exams and due
 * homework. `audience` reuses the announcement vocabulary so staff already know
 * what it means.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();

            // assembly | exam | holiday | sports | meeting | deadline | other
            $table->string('type')->default('other')->index();

            $table->timestamp('starts_at');
            // Null end means the event is a point in time, not a span.
            $table->timestamp('ends_at')->nullable();

            // An all-day event (a holiday, an inset day) has no clock time.
            $table->boolean('all_day')->default(false);

            $table->string('location')->nullable();

            // all | students | teachers — mirrors announcements.
            $table->string('audience')->default('all')->index();

            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'starts_at']);
            $table->index(['school_id', 'is_published', 'audience']);

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
