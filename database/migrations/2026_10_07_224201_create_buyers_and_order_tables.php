<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('skcl_no', 50);
            $table->string('file_no', 50);
            $table->foreignId('buyer_id')->nullable()->constrained('buyers');
            $table->string('style', 100);
            $table->string('color', 60);
            $table->string('item_name', 100);
            $table->date('shipment_date')->nullable();
            $table->unsignedInteger('order_qty')->default(0);
            $table->timestamps();

            // একটি order = একটি SKCL + item + color
            $table->unique(['skcl_no', 'item_name', 'color'], 'orders_skcl_item_color_unique');
        });

        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('size', 30);
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['order_id', 'size'], 'order_details_order_size_unique');
        });

        Schema::create('order_fabrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('color', 60);                       // ফ্যাব্রিকের রং
            $table->unsignedInteger('gsm')->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('quantity', 12, 2);                // kg
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fabrics');
        Schema::dropIfExists('order_details');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('buyers');
    }
};
