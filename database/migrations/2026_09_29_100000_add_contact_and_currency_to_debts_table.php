<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->string('person_contact')->nullable()->after('person_name');
            $table->string('currency_code', 3)->nullable()->after('remaining_amount');
        });

        DB::table('debts')
            ->whereNull('currency_code')
            ->whereNotNull('account_id')
            ->update([
                'currency_code' => DB::raw(
                    '(SELECT a.currency_code FROM accounts a WHERE a.id = debts.account_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn(['person_contact', 'currency_code']);
        });
    }
};
