<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartController extends Controller
{
    public function index()
    {
        $parts = Part::orderBy('name')->get();

        return Inertia::render('Parts/Index', [
            'parts' => $parts,
        ]);
    }

    public function create()
    {
        return Inertia::render('Parts/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                => 'required|string|max:150',
            'sku'                 => 'required|string|max:60|unique:parts,sku',
            'cost_price'          => 'required|numeric|min:0',
            'sale_price'          => 'required|numeric|min:0',
            'stock'               => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
        ]);

        Part::create($request->only('name','sku','cost_price','sale_price','stock','low_stock_threshold'));

        return redirect()->route('parts.index')
            ->with('success', 'Refacción registrada correctamente.');
    }

    public function edit(Part $part)
    {
        return Inertia::render('Parts/Edit', ['part' => $part]);
    }

    public function update(Request $request, Part $part)
    {
        $request->validate([
            'name'                => 'required|string|max:150',
            'sku'                 => 'required|string|max:60|unique:parts,sku,' . $part->id,
            'cost_price'          => 'required|numeric|min:0',
            'sale_price'          => 'required|numeric|min:0',
            'stock'               => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
        ]);

        $part->update($request->only('name','sku','cost_price','sale_price','stock','low_stock_threshold'));

        return redirect()->route('parts.index')
            ->with('success', 'Refacción actualizada correctamente.');
    }

    public function destroy(Part $part)
    {
        $part->delete();

        return redirect()->route('parts.index')
            ->with('success', 'Refacción eliminada.');
    }
}
