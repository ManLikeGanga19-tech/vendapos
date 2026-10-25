<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            // VendaPOS\Core\Enums\ItemType
            $table->string('type', 16);
            $table->string('name');
            $table->string('sku', 64)->nullable()->unique();
            $table->string('barcode', 64)->nullable()->index();
            // VAT-inclusive selling price in KES cents.
            $table->unsignedBigInteger('price_cents');
            // VendaPOS\Core\Enums\VatCategory
            $table->string('vat_category', 16);
            $table->boolean('is_active')->default(true);
            // Reserved for KRA eTIMS item codes (classification, units, etc.) once the
            // integration spec is confirmed; kept schemaless until then.
            $table->jsonb('etims')->nullable();
            $table->timestamps();

            // Terminals pull catalogue changes with ?updated_since=.
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
    }
};
