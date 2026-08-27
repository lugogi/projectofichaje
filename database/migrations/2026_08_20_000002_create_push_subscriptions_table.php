<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
