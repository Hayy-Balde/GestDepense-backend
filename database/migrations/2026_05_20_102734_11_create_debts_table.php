<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("debts", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->enum("type", ["lent", "borrowed"]);
            $table->string("person_name");
            $table->decimal("amount", 12, 2);
            $table->decimal("remaining_amount", 12, 2);
            $table->date("due_date")->nullable();
            $table->text("description")->nullable();
            $table->string("status")->default("pending");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("debts");
    }
};
