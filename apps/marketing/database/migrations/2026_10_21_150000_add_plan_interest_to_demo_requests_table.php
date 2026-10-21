<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            // The pricing card the visitor came from, if any. Interest only: sales confirms the plan on the call.
            $table->string('plan_interest', 16)->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->dropColumn('plan_interest');
        });
    }
};
