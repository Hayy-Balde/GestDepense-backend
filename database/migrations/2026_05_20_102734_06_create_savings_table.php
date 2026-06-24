<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("savings", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->string("name");
            $table->decimal("target_amount", 12, 2);
            $table->decimal("current_amount", 12, 2)->default(0);
            $table->date("deadline")->nullable();
            $table->string("icon")->nullable();
            $table->string("color")->nullable();
            $table->decimal("auto_save_amount", 12, 2)->nullable();
            $table->string("auto_save_frequency")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("savings");
    }
};
