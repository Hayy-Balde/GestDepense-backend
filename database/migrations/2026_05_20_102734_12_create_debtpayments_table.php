<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("debtpayments", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("debt_id")->constrained("debts")->cascadeOnDelete();
            $table->decimal("amount", 12, 2);
            $table->date("date");
            $table->text("note")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("debtpayments");
    }
};
