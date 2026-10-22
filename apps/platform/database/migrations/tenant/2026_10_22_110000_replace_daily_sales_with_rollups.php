<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Architecture §7.3 rollups replace daily_sales (approved by Daniel, 2026-10-22).
| Daily figures are the sum of a day's hours. Every count and amount is signed,
| because a void's job may run before its sale's job.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('daily_sales');

        // One branch's sales per Africa/Nairobi business date and hour (0–23).
        Schema::create('sales_hourly', function (Blueprint $table) {
            $table->id();
            $table->uuid('branch_id');
            $table->date('business_date');
            $table->unsignedTinyInteger('hour');
            $table->integer('order_count')->default(0);
            $table->integer('void_count')->default(0);
            // Dropped before completion; never sales, counted for loss prevention.
            $table->integer('cancelled_count')->default(0);
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('vat_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'business_date', 'hour']);
        });

        // What each item sold per branch per business date, net of voids. Amounts are VAT-inclusive.
        Schema::create('item_sales_daily', function (Blueprint $table) {
            $table->id();
            $table->uuid('branch_id');
            $table->date('business_date');
            $table->uuid('item_id');
            $table->integer('quantity')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->bigInteger('vat_cents')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'business_date', 'item_id']);
            $table->index(['branch_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_sales_daily');
        Schema::dropIfExists('sales_hourly');

        Schema::create('daily_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('branch_id');
            $table->date('business_date');
            $table->integer('order_count')->default(0);
            $table->integer('void_count')->default(0);
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('vat_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'business_date']);
        });
    }
};
