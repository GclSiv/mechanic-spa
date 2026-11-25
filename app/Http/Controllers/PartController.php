<?php

namespace App\Http\Controllers;

use App\Actions\Parts\RegistrarMovimientoStockAction;
use App\Models\Category;
use App\Models\Part;
use App\Models\RepairOrderItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PartController extends Controller
{
    public function index(Request $request)
    {
        // Búsqueda y filtro en servidor. La paginación conserva ambos en el query string.
        $search     = mb_substr(trim((string) $request->query('search', '')), 0, 100);
        $categoryId = (string) $request->query('category_id', '');
        $like       = '%' . addcslashes($search, '%_\\') . '%';

        $parts = Part::query()
            ->with('category')   // solo la categoría: es lo único relacionado que muestra el listado
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'LIKE', $like)
                ->orWhere('sku', 'LIKE', $like)
                ->orWhere('barcode', 'LIKE', $like)))
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

    /**
     * Escaneo: primero código de barras exacto, después SKU exacto (fallback).
     */
    public function scan(Request $request)
    {
        $codigo = mb_substr(trim((string) $request->query('codigo', '')), 0, 64);

        if ($codigo === '') {
            return back()->with('error', 'Escribe o escanea un código.');
        }

        $parte = Part::where('barcode', $codigo)->first() ?? Part::where('sku', $codigo)->first();

        if (! $parte) {
            return back()->with('error', 'No se encontró ninguna refacción con ese código.');
        }

        return redirect()->route('parts.kardex', $parte);
    }

    public function create()
    {
        return Inertia::render('Parts/Create', [
            'categories' => Category::where('type', Category::TYPE_PART)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, RegistrarMovimientoStockAction $stock)
    {
        $this->normalizar($request);
        $request->validate($this->reglas());

        DB::transaction(function () use ($request, $stock) {
            // El stock inicial no se escribe directo: nace como movimiento auditable.
            $parte = Part::create([
                ...$request->only('category_id', 'name', 'sku', 'barcode', 'cost_price', 'sale_price', 'low_stock_threshold'),
                'stock' => 0,
            ]);

            if ((int) $request->input('stock') > 0) {
                $stock->ejecutar(
                    partId: $parte->id,
                    tipo: StockMovement::TIPO_INICIAL,
                    cantidad: (int) $request->input('stock'),
                    userId: $request->user()->id,
                    motivo: 'Alta de refacción',
                );
            }
        });

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

    /** El stock no se edita aquí: cambia solo por movimientos del Kardex u órdenes. */
    public function update(Request $request, Part $part)
    {
        $this->normalizar($request);
        $request->validate($this->reglas($part));

        $part->update($request->only(
            'category_id', 'name', 'sku', 'barcode', 'cost_price', 'sale_price', 'low_stock_threshold'
        ));

        return redirect()->route('parts.index')
            ->with('success', 'Refacción actualizada correctamente.');
    }

    public function destroy(Part $part)
    {
        // Historial protegido: una refacción con órdenes o movimientos no se borra.
        if (RepairOrderItem::where('part_id', $part->id)->exists()) {
            return redirect()->route('parts.index')
                ->with('error', 'Esta refacción tiene uso en órdenes y no puede eliminarse. Su historial se conserva.');
        }

        if ($part->movements()->exists()) {
            return redirect()->route('parts.index')
                ->with('error', 'Esta refacción tiene movimientos de inventario y no puede eliminarse. Su historial se conserva.');
        }

        $part->delete();

        return redirect()->route('parts.index')
            ->with('success', 'Refacción eliminada.');
    }

    /** Kardex: stock actual y movimientos en orden cronológico inverso. */
    public function kardex(Part $part)
    {
        $movimientos = $part->movements()
            ->with('user:id,name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (StockMovement $m) => [
                'id'            => $m->id,
                'type'          => $m->type,
                'quantity'      => $m->quantity,
                'stock_before'  => $m->stock_before,
                'stock_after'   => $m->stock_after,
                'reason'        => $m->reason,
                'user'          => $m->user?->name,
                'created_at'    => $m->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Parts/Kardex', [
            'part'        => $part->load('category:id,name'),
            'movimientos' => $movimientos,
        ]);
    }

    /** Movimiento manual. Solo admin/owner (ruta con role:admin). El mecánico solo genera movimientos vía órdenes. */
    public function movimiento(Request $request, Part $part, RegistrarMovimientoStockAction $stock)
    {
        $datos = $request->validate([
            'type'     => ['required', Rule::in(StockMovement::TIPOS_MANUALES)],
            'quantity' => ['required', 'integer', 'between:-100000,100000'],
            'reason'   => ['nullable', 'string', 'max:255'],
        ]);

        $tipo = $datos['type'];
        $cantidad = (int) $datos['quantity'];

        if ($tipo === StockMovement::TIPO_AJUSTE) {
            // Ajuste: delta firmado, no puede ser cero y siempre lleva motivo.
            $request->validate(['quantity' => ['not_in:0'], 'reason' => ['required', 'string', 'max:255']]);
        } else {
            // Entrada y salida: cantidad positiva; la salida descuenta.
            $request->validate(['quantity' => ['min:1'], 'reason' => ($tipo === StockMovement::TIPO_SALIDA ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'])]);
            $cantidad = $tipo === StockMovement::TIPO_SALIDA ? -abs($cantidad) : abs($cantidad);
        }

        $stock->ejecutar(
            partId: $part->id,
            tipo: $tipo,
            cantidad: $cantidad,
            userId: $request->user()->id,
            motivo: $datos['reason'] ?? null,
        );

        return redirect()->route('parts.kardex', $part)
            ->with('success', 'Movimiento registrado.');
    }

    /** Normaliza el código de barras: vacío = null (nullable + unique). */
    private function normalizar(Request $request): void
    {
        $barcode = trim((string) $request->input('barcode', ''));
        $request->merge(['barcode' => $barcode === '' ? null : $barcode]);
    }

    /** Reglas compartidas por alta y edición. SKU y código de barras únicos ignoran el propio registro al editar. */
    private function reglas(?Part $part = null): array
    {
        $reglas = [
            'category_id'         => ['nullable', Rule::exists('categories', 'id')->where('type', Category::TYPE_PART)],
            'name'                => 'required|string|max:150',
            'sku'                 => 'required|string|max:60|unique:parts,sku' . ($part ? ',' . $part->id : ''),
            'barcode'             => ['nullable', 'string', 'max:64', Rule::unique('parts', 'barcode')->ignore($part?->id)],
            'cost_price'          => 'required|numeric|min:0',
            'sale_price'          => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
        ];

        // Stock inicial solo en el alta; después cambia únicamente por movimientos.
        if ($part === null) {
            $reglas['stock'] = 'required|integer|min:0';
        }

        return $reglas;
    }
}
