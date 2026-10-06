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
            $table->foreignId('table_no_id')->nullable()->after('skcl_no')->constrained('tables');
            $table->unsignedInteger('fixed_qty')->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('size_quantities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_no_id');
            $table->dropColumn('fixed_qty');      //
        });
    }
};
