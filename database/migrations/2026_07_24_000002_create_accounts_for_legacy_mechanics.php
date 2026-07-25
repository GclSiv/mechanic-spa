<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // Buscar mecánicos sin cuenta de usuario vinculada
        $mechanics = DB::table('mechanics')
            ->whereNull('user_id')
            ->get();

        foreach ($mechanics as $mechanic) {
            // Verificar que el email no exista ya en users
            $existingUser = DB::table('users')
                ->where('email', $mechanic->email)
                ->first();

            if ($existingUser) {
                // Vincular cuenta existente
                DB::table('mechanics')
                    ->where('id', $mechanic->id)
                    ->update(['user_id' => $existingUser->id]);
            } else {
                // Crear nueva cuenta con contraseña temporal
                $userId = DB::table('users')->insertGetId([
                    'name'              => $mechanic->name,
                    'email'             => $mechanic->email,
                    'password'          => Hash::make('password123'),
                    'role'              => 'mechanic',
                    'email_verified_at' => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                DB::table('mechanics')
                    ->where('id', $mechanic->id)
                    ->update(['user_id' => $userId]);
            }
        }
    }

    public function down(): void
    {
        // No reversible — no sabemos qué cuentas existían antes
    }
};
