<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('temp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temp_vendor_id')->constrained('temp_vendors')->cascadeOnDelete();
            $table->string('phone')->nullable();
            $table->string('geo')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('spamblock')->nullable();
            $table->string('type')->nullable();
            $table->timestamp('session_created_date')->nullable();
            $table->timestamp('last_connect_date')->nullable();
            $table->integer('stats_invites_count')->nullable();
            $table->foreignId('upload_id')->constrained('uploads')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_accounts');
    }
};
