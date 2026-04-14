<?php

namespace App\Http\Controllers;

use App\Models\Mechanic;
use App\Models\Gender;
use App\Models\MechanicType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MechanicController extends Controller
{
    public function index()
    {
        $mechanics = Mechanic::with(['gender', 'mechanicType'])
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
            'mechanicTypes' => MechanicType::all(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:100',
            'gender_id'        => 'required|exists:genders,id',
            'mechanic_type_id' => 'required|exists:mechanic_types,id',
            'phone'            => 'nullable|string|max:20',
        ]);

        Mechanic::create($request->only('name', 'gender_id', 'mechanic_type_id', 'phone'));

        return redirect()->route('mechanics.index')
            ->with('success', 'Mecánico registrado correctamente.');
    }

    public function edit(Mechanic $mechanic)
    {
        return Inertia::render('Mechanics/Edit', [
            'mechanic'      => $mechanic,
            'genders'       => Gender::all(['id', 'name']),
            'mechanicTypes' => MechanicType::all(['id', 'name']),
        ]);
    }

    public function update(Request $request, Mechanic $mechanic)
    {
        $request->validate([
            'name'             => 'required|string|max:100',
            'gender_id'        => 'required|exists:genders,id',
            'mechanic_type_id' => 'required|exists:mechanic_types,id',
            'phone'            => 'nullable|string|max:20',
        ]);

        $mechanic->update($request->only('name', 'gender_id', 'mechanic_type_id', 'phone'));

        return redirect()->route('mechanics.index')
            ->with('success', 'Mecánico actualizado correctamente.');
    }

    public function destroy(Mechanic $mechanic)
    {
        $mechanic->delete();

        return redirect()->route('mechanics.index')
            ->with('success', 'Mecánico eliminado.');
    }
}
