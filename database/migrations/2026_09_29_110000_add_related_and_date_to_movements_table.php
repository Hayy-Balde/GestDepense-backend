<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->string('related_type')->nullable()->after('type');
            $table->uuid('related_id')->nullable()->after('related_type');
            $table->date('date')->nullable()->after('currency_code');

            $table->index(['user_id', 'date'], 'movements_user_date_index');
        });

        DB::table('movements')->whereNull('date')->update([
            'date' => DB::raw('created_at::date'),
        ]);
    }

    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->dropIndex('movements_user_date_index');
            $table->dropColumn(['related_type', 'related_id', 'date']);
        });
    }
};
