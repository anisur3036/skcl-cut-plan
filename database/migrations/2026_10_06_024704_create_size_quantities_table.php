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
        Schema::create('size_quantities', function (Blueprint $table) {
            $table->id();
            $table->string('file_no');
            $table->string('skcl_no')->index();
            $table->string('order_no');
            $table->string('style_no');
            $table->string('country');
            $table->string('item_name');
            $table->string('color_name');
            $table->string('size');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('size_quantities');
    }
};
