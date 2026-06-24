<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("caisses", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->string("name");
            $table->decimal("budget_amount", 12, 2);
            $table->string("icon")->nullable();
            $table->string("color")->nullable();
            $table->text("description")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("caisses");
    }
};
