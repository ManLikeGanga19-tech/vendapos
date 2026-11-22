<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table service pack (architecture §3.4, §7.5): optional, used only by branches
        // that serve at tables. The core sale path never reads these.
        Schema::create('floor_areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('floor_area_id')->constrained('floor_areas')->restrictOnDelete();
            // What staff call it: "4", "T12", "Terrace 2".
            $table->string('name', 20);
            $table->unsignedSmallInteger('seats')->default(4);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // Names are unique per branch, so "Table 4" means one table on every screen.
            $table->unique(['branch_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('floor_areas');
    }
};
