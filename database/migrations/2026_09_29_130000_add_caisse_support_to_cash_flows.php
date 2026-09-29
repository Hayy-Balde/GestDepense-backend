<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une caisse n'avait pas de devise : elle héritait de son compte source et
        // retombait sur 'EUR' dès que ce compte était supprimé. On la rend explicite.
        Schema::table('caisses', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('budget_amount');
        });

        DB::table('caisses')->orderBy('id')->each(function ($caisse) {
            $code = DB::table('accounts')->where('id', $caisse->source_account_id)->value('currency_code');

            if (! $code) {
                $code = DB::table('users')->where('id', $caisse->user_id)->value('currency_code');
            }

            if ($code) {
                DB::table('caisses')->where('id', $caisse->id)->update(['currency_code' => $code]);
            }
        });

        // Dettes, factures et abonnements ne pouvaient être adossés qu'à un compte.
        foreach (['debts', 'debtpayments', 'invoices', 'invoice_payments', 'subscriptions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignUuid('caisse_id')
                    ->nullable()
                    ->after('account_id')
                    ->constrained('caisses')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['subscriptions', 'invoice_payments', 'invoices', 'debtpayments', 'debts'] as $tableName) {
            Schema::table($tableName, function ($table) {
                $table->dropConstrainedForeignId('caisse_id');
            });
        }

        Schema::table('caisses', function ($table) {
            $table->dropColumn('currency_code');
        });
    }
};
