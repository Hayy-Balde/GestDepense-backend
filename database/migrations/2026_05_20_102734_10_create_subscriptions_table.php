<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("subscriptions", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->foreignUuid("account_id")->nullable()->constrained("accounts")->nullOnDelete();
            $table->string("name");
            $table->decimal("amount", 12, 2);
            $table->string("currency_code", 3)->default("EUR");
            $table->string("billing_cycle");
            $table->date("next_billing_date");
            $table->string("icon")->nullable();
            $table->string("color")->nullable();
            $table->boolean("is_active")->default(true);
            $table->integer("reminder_days_before")->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("subscriptions");
    }
};
