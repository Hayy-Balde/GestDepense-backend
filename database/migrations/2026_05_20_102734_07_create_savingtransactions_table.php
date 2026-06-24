<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("savingtransactions", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("saving_id")->constrained("savings")->cascadeOnDelete();
            $table->enum("type", ["deposit", "withdrawal"]);
            $table->decimal("amount", 12, 2);
            $table->date("date");
            $table->text("note")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("savingtransactions");
    }
};
