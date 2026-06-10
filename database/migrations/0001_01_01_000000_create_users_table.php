<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabla principal de usuarios ('employees') y el resto del esquema de negocio
     * se crean importando el .sql final (fichaje_smd). Laravel solo necesita su propia
     * tabla para los tokens de recuperación de contraseña.
     *
     * Las sesiones de Laravel van por fichero (SESSION_DRIVER=file), así no chocan con
     * la tabla de negocio 'sessions' que define el esquema.
     */
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
