<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // VendaPOS\Core\Enums\BusinessType: selects the branch's vertical pack.
            $table->string('business_type', 32);
            // KRA eTIMS branch ID (bhfId). Empty until eTIMS integration goes live.
            $table->string('etims_branch_id', 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
