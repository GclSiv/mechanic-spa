<?php

namespace App\Http\Controllers;

use App\Models\Mechanic;
use App\Models\Gender;
use App\Models\MechanicType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class MechanicController extends Controller
{
    public function index()
    {
        $mechanics = Mechanic::with(['gender', 'mechanicType', 'user'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Mechanics/Index', [
            'mechanics' => $mechanics,
        ]);
    }

    public function create()
    {
        return Inertia::render('Mechanics/Create', [
            'genders'       => Gender::all(['id', 'name']),
            'mechanicTypes' => MechanicType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:100',
            'email'            => 'required|email|unique:users,email|unique:mechanics,email',
            'gender_id'        => 'required|exists:genders,id',
            'mechanic_type_id' => 'required|exists:mechanic_types,id',
            'phone'            => 'nullable|string|max:20',
        ]);

        DB::transaction(function () use ($request) {
            // 1. Crear cuenta de usuario
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make('password123'),
                'role'     => 'mechanic',
            ]);

            // 2. Crear perfil de mecánico vinculado
            Mechanic::create([
                'name'             => $request->name,
                'email'            => $request->email,
                'user_id'          => $user->id,
                'gender_id'        => $request->gender_id,
                'mechanic_type_id' => $request->mechanic_type_id,
                'phone'            => $request->phone,
            ]);
        });

        return redirect()->route('mechanics.index')
            ->with('success', 'Mecánico registrado. Credenciales: ' . $request->email . ' / password123');
    }

    public function edit(Mechanic $mechanic)
    {
        return Inertia::render('Mechanics/Edit', [
            'mechanic'      => $mechanic,
            'genders'       => Gender::all(['id', 'name']),
            'mechanicTypes' => MechanicType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Mechanic $mechanic)
    {
        $request->validate([
            'name'             => 'required|string|max:100',
            'email'            => 'required|email|unique:mechanics,email,' . $mechanic->id . '|unique:users,email,' . $mechanic->user_id,
            'gender_id'        => 'required|exists:genders,id',
            'mechanic_type_id' => 'required|exists:mechanic_types,id',
            'phone'            => 'nullable|string|max:20',
        ]);

        DB::transaction(function () use ($request, $mechanic) {
            // Actualizar perfil
            $mechanic->update([
                'name'             => $request->name,
                'email'            => $request->email,
                'gender_id'        => $request->gender_id,
                'mechanic_type_id' => $request->mechanic_type_id,
                'phone'            => $request->phone,
            ]);

            // Sincronizar nombre y email en la cuenta de usuario
            if ($mechanic->user_id) {
                $mechanic->user()->update([
                    'name'  => $request->name,
                    'email' => $request->email,
                ]);
            }
        });

        return redirect()->route('mechanics.index')
            ->with('success', 'Mecánico actualizado correctamente.');
    }

    public function destroy(Mechanic $mechanic)
    {
        DB::transaction(function () use ($mechanic) {
            $userId = $mechanic->user_id;
            $mechanic->delete();
            if ($userId) {
                User::destroy($userId);
            }
        });

        return redirect()->route('mechanics.index')
            ->with('success', 'Mecánico y cuenta de acceso eliminados.');
    }
}
