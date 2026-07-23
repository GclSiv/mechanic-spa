<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Part;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $search     = $request->input('search');
        $categoryId = $request->input('category_id');

        $parts = Part::with('category')           // Eager loading — sin N+1
            ->when($search, fn ($q) =>
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku',  'LIKE', "%{$search}%")
            )
            ->when($categoryId, fn ($q) =>
                $q->where('category_id', $categoryId)
            )
            ->orderBy('name')
            ->get();

        return Inertia::render('Parts/Index', [
            'parts'      => $parts,
            'categories' => Category::orderBy('name')->get(),
            'filters'    => $request->only(['search', 'category_id']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Parts/Create', [
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id'         => 'nullable|exists:categories,id',
            'name'                => 'required|string|max:150',
            'sku'                 => 'required|string|max:60|unique:parts,sku',
            'cost_price'          => 'required|numeric|min:0',
            'sale_price'          => 'required|numeric|min:0',
            'stock'               => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
        ]);

        Part::create($request->only(
            'category_id', 'name', 'sku', 'cost_price', 'sale_price', 'stock', 'low_stock_threshold'
        ));

        return redirect()->route('parts.index')
            ->with('success', 'Refacción registrada correctamente.');
    }

    public function edit(Part $part)
    {
        return Inertia::render('Parts/Edit', [
            'part'       => $part->load('category'),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Part $part)
    {
        $request->validate([
            'category_id'         => 'nullable|exists:categories,id',
            'name'                => 'required|string|max:150',
            'sku'                 => 'required|string|max:60|unique:parts,sku,' . $part->id,
            'cost_price'          => 'required|numeric|min:0',
            'sale_price'          => 'required|numeric|min:0',
            'stock'               => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
        ]);

        $part->update($request->only(
            'category_id', 'name', 'sku', 'cost_price', 'sale_price', 'stock', 'low_stock_threshold'
        ));

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
