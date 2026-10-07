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
        Schema::table('size_quantities', function (Blueprint $table) {
            $table->unsignedInteger('ratio')->nullable()->after('fixed_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('size_quantities', function (Blueprint $table) {
            $table->dropColumn('ratio');
        });
    }
};
