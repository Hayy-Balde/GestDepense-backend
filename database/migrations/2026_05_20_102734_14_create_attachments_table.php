<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("attachments", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->uuidMorphs("attachable");
            $table->string("file_path");
            $table->string("file_name");
            $table->integer("file_size");
            $table->string("mime_type");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("attachments");
    }
};
