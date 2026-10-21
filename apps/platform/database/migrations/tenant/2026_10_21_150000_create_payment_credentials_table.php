<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each business takes payments into its own paybill or till (approved by Daniel, M1),
        // so provider keys live per tenant, encrypted with the app key.
        Schema::create('payment_credentials', function (Blueprint $table) {
            $table->id();
            // VendaPOS\Core\Enums\MobileMoneyProvider
            $table->string('provider', 16)->unique();
            // sandbox or production
            $table->string('environment', 16);
            // Encrypted JSON; the fields depend on the provider's gateway.
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_credentials');
    }
};
