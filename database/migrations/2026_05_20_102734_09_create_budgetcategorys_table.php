<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("budgetcategorys", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("budget_id")->constrained("budgets")->cascadeOnDelete();
            $table->foreignUuid("category_id")->constrained("categories")->cascadeOnDelete();
            $table->decimal("allocated_amount", 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("budgetcategorys");
    }
};
