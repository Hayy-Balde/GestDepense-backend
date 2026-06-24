<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("budgets", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->integer("month");
            $table->integer("year");
            $table->decimal("total_budget", 12, 2);
            $table->text("notes")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("budgets");
    }
};
