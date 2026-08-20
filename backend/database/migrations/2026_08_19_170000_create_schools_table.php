<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school is a tenant. Every tenant-owned record links back here.
     */
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('logo')->nullable();
            $table->string('status')->default('trial')->index(); // active | trial | suspended | expired
            $table->string('timezone')->default('Africa/Douala');
            $table->string('primary_color')->nullable();

            // Denormalised subscription snapshot (mirrors the active subscription).
            // The FK is added in the subscriptions migration (after plans exist).
            $table->unsignedBigInteger('subscription_plan_id')->nullable()->index();
            $table->string('subscription_status')->default('none'); // none | trial | active | past_due | expired | suspended | cancelled
            $table->timestamp('subscription_started_at')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
