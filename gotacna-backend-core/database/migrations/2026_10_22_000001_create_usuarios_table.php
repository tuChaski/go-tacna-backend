<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 20)->unique();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('correo')->unique();
            $table->string('telefono', 30);
            $table->string('password_hash');
            $table->enum('rol', ['pasajero', 'conductor', 'admin_general', 'admin_empresa'])
                ->default('pasajero');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['rol', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
