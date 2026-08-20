<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subscription history per school. The school's current subscription is
     * denormalised onto `schools` for fast access; this table keeps history.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status')->default('trial')->index(); // trial | active | past_due | cancelled | expired | suspended
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('billing_interval')->default('monthly');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency')->default('XAF');
            $table->timestamps();
        });

        // The schools table is created before subscription_plans; attach the
        // denormalised plan FK now that both tables exist.
        Schema::table('schools', function (Blueprint $table) {
            $table->foreign('subscription_plan_id')
                ->references('id')->on('subscription_plans')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
        });

        Schema::dropIfExists('subscriptions');
    }
};
