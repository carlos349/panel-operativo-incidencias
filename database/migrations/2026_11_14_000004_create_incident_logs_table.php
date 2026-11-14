<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('user_name', 100);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
            $table->index(['incident_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_logs');
    }
};
