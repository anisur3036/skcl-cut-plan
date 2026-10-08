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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('extra_cut_percent')->default(0)->after('order_qty');
            $table->unsignedInteger('max_lay')->nullable()->after('extra_cut_percent');
            $table->decimal('cad_consumption', 8, 3)->nullable()->after('max_lay');
            $table->decimal('required_fabrics', 12, 2)->nullable()->after('cad_consumption'); // kg
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['extra_cut_percent', 'max_lay', 'cad_consumption', 'required_fabrics']);
        });
    }
};
