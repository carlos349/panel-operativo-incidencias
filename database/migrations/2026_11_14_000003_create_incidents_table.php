<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description');
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            // Código corto (P1..P4); la etiqueta visible vive en App\Enums\Priority.
            $table->string('priority', 2);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requester_name', 100)->nullable();
            $table->string('assigned_to', 100)->nullable();
            $table->dateTime('resolution_date')->nullable();

            // Soporte del modelo de SLA (ver config/sla.php).
            $table->dateTime('first_response_due_at')->nullable();
            $table->dateTime('first_responded_at')->nullable();
            $table->dateTime('resolution_due_at')->nullable();

            $table->timestamps();

            $table->index(['status_id', 'priority']);
            $table->index('resolution_due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
