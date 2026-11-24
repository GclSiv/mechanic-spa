<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// UI-9: evidencia fotográfica/video de herramientas. Archivos en disco privado; nunca en público.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->string('path', 255)->unique();
            $table->string('media_type', 10);
            $table->string('original_name', 255);
            $table->string('mime_type', 60);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tool_id', 'media_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_media');
    }
};
