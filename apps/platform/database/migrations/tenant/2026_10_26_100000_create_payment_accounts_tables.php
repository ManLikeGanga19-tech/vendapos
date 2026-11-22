<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A business may run several paybills or tills (approved by Daniel, T3b):
        // each is an account, a branch may point at one, the rest use the default.
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            // VendaPOS\Core\Enums\MobileMoneyProvider
            $table->string('provider', 16);
            // sandbox or production; one per business (SavePaymentAccount)
            $table->string('environment', 16);
            // Encrypted JSON; the fields depend on the provider's gateway.
            $table->text('credentials');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX payment_accounts_one_default ON payment_accounts (provider) WHERE is_default');

        // A branch without a row here uses the provider's default account.
        Schema::create('payment_account_branches', function (Blueprint $table) {
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('provider', 16);
            $table->foreignId('payment_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['branch_id', 'provider']);
        });

        // Who changed which account, never with key values.
        Schema::create('payment_account_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->uuid('changed_by')->nullable();
            $table->string('action', 32);
            $table->json('details')->nullable();
            $table->timestampTz('created_at');
        });

        // The keys a payment was pushed with; its status query and callback use the same ones.
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_account_id')->nullable()->after('device_id')
                ->constrained('payment_accounts')->restrictOnDelete();
        });

        // Existing keys become each provider's default account. The encrypted
        // value is copied as is: same app key, same cast.
        foreach (DB::table('payment_credentials')->orderBy('id')->get() as $old) {
            $accountId = DB::table('payment_accounts')->insertGetId([
                'name' => 'Default',
                'provider' => $old->provider,
                'environment' => $old->environment,
                'credentials' => $old->credentials,
                'is_default' => (bool) $old->is_active,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);

            DB::table('payments')->where('provider', $old->provider)->update(['payment_account_id' => $accountId]);
        }

        Schema::drop('payment_credentials');
    }

    public function down(): void
    {
        Schema::create('payment_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16)->unique();
            $table->string('environment', 16);
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (DB::table('payment_accounts')->where('is_default', true)->get() as $account) {
            DB::table('payment_credentials')->insert([
                'provider' => $account->provider,
                'environment' => $account->environment,
                'credentials' => $account->credentials,
                'is_active' => true,
                'created_at' => $account->created_at,
                'updated_at' => $account->updated_at,
            ]);
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_account_id');
        });

        Schema::drop('payment_account_changes');
        Schema::drop('payment_account_branches');
        Schema::drop('payment_accounts');
    }
};
