<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("expenses", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->foreignUuid("account_id")->constrained("accounts")->cascadeOnDelete();
            $table->foreignUuid("caisse_id")->nullable()->constrained("caisses")->nullOnDelete();
            $table->foreignUuid("category_id")->constrained("categories");
            $table->foreignUuid("sub_category_id")->nullable()->constrained("sub_categories")->nullOnDelete();
            $table->string("title");
            $table->text("description")->nullable();
            $table->decimal("amount", 12, 2);
            $table->string("currency_code", 3)->default("EUR");
            $table->date("date");
            $table->time("time")->nullable();
            $table->string("payment_method")->nullable();
            $table->boolean("is_recurring")->default(false);
            $table->string("recurrence_rule")->nullable();
            $table->string("status")->default("completed");
            $table->text("notes")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("expenses");
    }
};
