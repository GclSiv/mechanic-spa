<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Part;
use App\Models\RepairOrderItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PartController extends Controller
{
    public function index(Request $request)
    {
        // Búsqueda y filtro en servidor. La paginación conserva ambos en el query string.
        $search     = mb_substr(trim((string) $request->query('search', '')), 0, 100);
        $categoryId = (string) $request->query('category_id', '');

        $parts = Part::query()
            ->with('category')   // solo la categoría: es lo único relacionado que muestra el listado
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'LIKE', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('sku', 'LIKE', '%' . addcslashes($search, '%_\\') . '%')))
            ->when(ctype_digit($categoryId), fn ($q) => $q->where('category_id', (int) $categoryId))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Parts/Index', [
            'parts'      => $parts,
            'categories' => Category::where('type', Category::TYPE_PART)->orderBy('name')->get(),
            'filters'    => ['search' => $search, 'category_id' => $categoryId],
        ]);
    }

    public function create()
    {
        return Inertia::render('Parts/Create', [
            'categories' => Category::where('type', Category::TYPE_PART)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate($this->reglas());

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
            'categories' => Category::where('type', Category::TYPE_PART)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Part $part)
    {
        $request->validate($this->reglas($part));

        $part->update($request->only(
            'category_id', 'name', 'sku', 'cost_price', 'sale_price', 'stock', 'low_stock_threshold'
        ));

        return redirect()->route('parts.index')
            ->with('success', 'Refacción actualizada correctamente.');
    }

    public function destroy(Part $part)
    {
        // Historial protegido: una refacción usada en una orden no se borra.
        // Se valida antes del delete para no llegar a la FK de repair_order_items.
        if (RepairOrderItem::where('part_id', $part->id)->exists()) {
            return redirect()->route('parts.index')
                ->with('error', 'Esta refacción tiene uso en órdenes y no puede eliminarse. Su historial se conserva.');
        }

        $part->delete();

        return redirect()->route('parts.index')
            ->with('success', 'Refacción eliminada.');
    }

    /** Reglas compartidas por alta y edición. SKU único ignora el propio registro al editar. */
    private function reglas(?Part $part = null): array
    {
        return [
            'category_id'         => ['nullable', Rule::exists('categories', 'id')->where('type', Category::TYPE_PART)],
            'name'                => 'required|string|max:150',
            'sku'                 => 'required|string|max:60|unique:parts,sku' . ($part ? ',' . $part->id : ''),
            'cost_price'          => 'required|numeric|min:0',
            'sale_price'          => 'required|numeric|min:0',
            'stock'               => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
        ];
    }
}
