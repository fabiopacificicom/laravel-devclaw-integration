<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->enum('type', ['post', 'course', 'workshop', 'video', 'linkedin', 'other']);
            $table->enum('status', ['draft', 'scheduled', 'published', 'cancelled'])->default('draft');
            $table->dateTime('scheduled_at')->index();
            $table->string('color', 7)->default('#3b82f6');
            $table->nullableMorphs('linkable');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_entries');
    }
};
