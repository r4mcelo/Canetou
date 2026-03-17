<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('external_id')->index();
            $table->string('external_provider')->default('autentique');
            $table->string('name');
            $table->string('status')->default('pending');
            $table->json('signers')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
