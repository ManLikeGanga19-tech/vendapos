<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This branch's floor plan, copied from the server (table service). Replaced in full each sync.
        Schema::create('floor_areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('floor_area_id')->index();
            $table->string('name', 20);
            $table->unsignedSmallInteger('seats');
            $table->unsignedSmallInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('floor_areas');
    }
};
