<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plans are platform-global (no school_id) and fully configurable.
     * A null limit means "unlimited" (custom / enterprise).
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('billing_interval')->default('monthly'); // monthly | yearly
            $table->string('currency')->default('XAF');
            $table->unsignedInteger('max_students')->nullable();
            $table->unsignedInteger('max_teachers')->nullable();
            $table->unsignedInteger('max_classes')->nullable();
            $table->json('features')->nullable();
            $table->string('status')->default('active')->index(); // active | archived
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
