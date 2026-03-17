<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained();
            $table->foreignId('document_id')->nullable()->constrained();
            $table->string('direction');
            $table->string('event_type');
            $table->string('external_event_id')->nullable()->index();
            $table->json('payload');
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('attempt')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
