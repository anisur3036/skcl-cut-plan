<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marker_plans', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no', 50)->unique();
            $table->string('skcl_no')->index();
            $table->string('file_no');
            $table->string('order_no');
            $table->string('style_no');
            $table->string('country');
            $table->string('item_name');
            $table->string('color_name');
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marker_plans');
        Schema::dropIfExists('marker_plan_details');
    }
};
