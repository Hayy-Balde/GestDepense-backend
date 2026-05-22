<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("accounts", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->string("name");
            $table->string("type");
            $table->decimal("balance", 12, 2)->default(0);
            $table->string("currency_code", 3)->default("EUR");
            $table->string("color")->nullable();
            $table->string("icon")->nullable();
            $table->boolean("is_active")->default(true);
            $table->decimal("credit_limit", 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("accounts");
    }
};
