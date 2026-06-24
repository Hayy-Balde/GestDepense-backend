<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("auditlogs", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->nullable()->constrained("users")->nullOnDelete();
            $table->string("action");
            $table->uuidMorphs("auditable");
            $table->json("old_values")->nullable();
            $table->json("new_values")->nullable();
            $table->string("ip_address", 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("auditlogs");
    }
};
