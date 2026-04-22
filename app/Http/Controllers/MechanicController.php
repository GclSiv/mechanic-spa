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
            'role'             => 'required|in:admin,mechanic',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make('password123'),
                'role'     => $request->role,
            ]);

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
            ->with('success', 'Personal registrado. Credenciales: ' . $request->email . ' / password123');
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
            // Desvincular follow_ups (poner mechanic_id en null para no perder historial)
            \App\Models\FollowUp::where('mechanic_id', $mechanic->id)
                ->update(['mechanic_id' => null]);

            // Desvincular repair_orders activas
            \App\Models\RepairOrder::where('mechanic_id', $mechanic->id)
                ->update(['mechanic_id' => null]);

            // Eliminar cuenta de usuario asociada si existe (null-safe para legacy)
            $mechanic->user?->delete();
            $mechanic->delete();
        });

        return redirect()->route('mechanics.index')
            ->with('success', 'Mecánico eliminado correctamente.');
    }
}
