<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("movements", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->string("type"); // apport | regulation | transfer | expense | income | caisse_fund | caisse_refund | caisse_close | out
            $table->string("from_type")->nullable(); // account | caisse | external
            $table->uuid("from_id")->nullable();
            $table->string("to_type")->nullable(); // account | caisse | external
            $table->uuid("to_id")->nullable();
            $table->decimal("amount", 12, 2);
            $table->string("currency_code", 3)->default("EUR");
            $table->string("label")->nullable();
            $table->timestamps();

            $table->index(["user_id", "created_at"]);
        });

        Schema::table("caisses", function (Blueprint $table) {
            $table->foreignUuid("source_account_id")
                ->nullable()
                ->after("budget_amount")
                ->constrained("accounts")
                ->nullOnDelete();
            $table->string("status")->default("active")->after("description"); // active | closed
        });
    }

    public function down(): void
    {
        Schema::table("caisses", function (Blueprint $table) {
            $table->dropConstrainedForeignId("source_account_id");
            $table->dropColumn("status");
        });

        Schema::dropIfExists("movements");
    }
};