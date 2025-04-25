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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('upload_id')->constrained();
            $table->string('phone')->index();
            $table->string('geo')->nullable(); // определяем через phonenumbers
            $table->timestamp('session_created_at')->nullable();
            $table->timestamp('last_connect_at')->nullable();
            $table->string('spamblock')->nullable();
            $table->boolean('has_profile_pic')->default(false);
            $table->integer('stats_spam_count')->default(0);
            $table->integer('stats_invites_count')->default(0);
            $table->boolean('is_premium')->default(false);
            $table->decimal('price', 8, 2)->nullable();
            $table->string('type')->nullable(); // со спамом / без
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
