<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Development stage: পুরনো marker plan ডাটা মুছে নতুন কাঠামো
        Schema::dropIfExists('marker_plan_details');
        Schema::dropIfExists('marker_plans');

        Schema::create('marker_plans', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no', 50)->unique();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('table_no_id')->nullable()->constrained('tables');
            $table->unsignedInteger('fixed_qty')->nullable(); // Lay Quantity
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('marker_plan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marker_plan_id')->constrained('marker_plans')->cascadeOnDelete();
            $table->string('size', 30);
            $table->unsignedInteger('ratio')->nullable();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marker_plan_details');
        Schema::dropIfExists('marker_plans');
    }
};
