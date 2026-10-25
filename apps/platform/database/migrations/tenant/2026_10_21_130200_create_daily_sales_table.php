<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sales per branch per business day (Africa/Nairobi), net of voids.
        // Signed, because a void's job may run before the sale's job.
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

    public function down(): void
    {
        Schema::dropIfExists('daily_sales');
    }
};
