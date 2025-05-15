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
        Schema::create('invite_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invite_vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upload_id')->constrained();
            $table->string('phone')->index();
            $table->string('geo')->nullable();
            $table->timestamp('session_created_at')->nullable();
            $table->timestamp('last_connect_at')->nullable();
            $table->string('spamblock')->nullable();
            $table->integer('stats_invites_count')->default(0);
            $table->decimal('price', 8, 2)->nullable();
            $table->string('type')->nullable();
            $table->boolean('del_user')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invite_accounts');
    }
};
