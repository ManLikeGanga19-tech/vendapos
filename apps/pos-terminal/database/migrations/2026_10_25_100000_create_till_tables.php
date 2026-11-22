<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row: who this till is, as told by the server when it was paired.
        Schema::create('till_identity', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->uuid('device_id');
            $table->string('device_name');
            $table->string('business_name');
            $table->uuid('branch_id');
            $table->string('branch_name');
            $table->string('business_type', 32);
            $table->string('terminal_mode', 32);
            // Encrypted by the OS keyring (System::encrypt), never stored in plain text.
            $table->text('token_ciphertext');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 16);
            $table->timestamp('paired_at');
            $table->timestamp('removed_at')->nullable();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->nullable()->index();
            $table->string('type', 16);
            $table->string('name');
            $table->string('sku', 64)->nullable();
            $table->string('barcode', 64)->nullable()->index();
            $table->unsignedBigInteger('price_cents');
            $table->string('vat_category', 16);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('role', 32);
            $table->json('permissions');
            $table->string('pin_hash');
            $table->unsignedTinyInteger('pin_length');
        });

        Schema::create('sync_state', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_state');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('till_identity');
    }
};
