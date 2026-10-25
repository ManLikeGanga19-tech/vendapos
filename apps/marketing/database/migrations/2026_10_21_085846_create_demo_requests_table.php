<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            // Generated when the form is rendered, so a double submit or a
            // refresh after a network blip can never create a second lead.
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('phone', 16);
            $table->string('business_name', 160);
            $table->string('business_type', 32);
            $table->string('outlets', 16);
            $table->string('contact_window', 16)->nullable();
            $table->text('message')->nullable();
            $table->string('locale', 5);
            $table->string('status', 16)->default('new')->index();
            $table->timestamp('consented_at');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
