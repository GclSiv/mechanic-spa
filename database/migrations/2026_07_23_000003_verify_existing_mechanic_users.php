<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar todos los usuarios existentes que aún no tienen email verificado
        // Esto permite que mecánicos creados antes de este fix puedan iniciar sesión
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // No revertible — no sabemos cuáles eran nulos antes
    }
};
