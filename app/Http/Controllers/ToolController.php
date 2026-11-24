<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Location;
use App\Models\Tool;
use App\Models\ToolMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Herramientas y activos del taller. Solo admin/owner (ruta con role:admin).
 * Dar de baja es lógico: el registro se conserva con estado "baja".
 */
class ToolController extends Controller
{
    public function index(Request $request)
    {
        $query = Tool::with(['category:id,name', 'location:id,name'])->orderBy('internal_code');

        if (! $request->boolean('mostrar_bajas')) {
            $query->activas();
        }

        if ($buscar = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($buscar) {
                $q->where('name', 'like', "%{$buscar}%")
                  ->orWhere('brand', 'like', "%{$buscar}%")
                  ->orWhere('model', 'like', "%{$buscar}%")
                  ->orWhere('serial_number', 'like', "%{$buscar}%")
                  ->orWhere('internal_code', 'like', "%{$buscar}%");
            });
        }

        if (in_array($request->input('status'), Tool::ESTADOS, true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }

        return Inertia::render('Tools/Index', [
            'tools'      => $query->paginate(25)->withQueryString(),
            'filters'    => $request->only(['search', 'status', 'category_id', 'location_id', 'mostrar_bajas']),
            'categories' => $this->categorias(),
            'locations'  => Location::orderBy('name')->get(['id', 'name', 'active']),
            'estados'    => Tool::ESTADOS,
        ]);
    }

    public function create()
    {
        return Inertia::render('Tools/Create', $this->catalogos());
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $evidencia = Arr::pull($datos, 'evidencia');

        $herramienta = Tool::create($datos);

        if ($evidencia) {
            ToolMedia::guardar($herramienta, $evidencia, $request->user()?->id);
        }

        return redirect()->route('tools.index')->with('success', 'Herramienta registrada.');
    }

    public function show(Tool $tool)
    {
        $tool->load(['category:id,name', 'location:id,name', 'media']);
        $evidencia = $tool->media->map(fn (ToolMedia $m) => [
                'id'            => $m->id,
                'media_type'    => $m->media_type,
                'original_name' => $m->original_name,
                'size'          => $m->size,
                'url'           => route('tools.media.show', [$tool, $m]),
                'created_at'    => $m->created_at?->toDateString(),
            ])->values();
        $tool->unsetRelation('media');

        return Inertia::render('Tools/Show', ['tool' => $tool, 'evidencia' => $evidencia]);
    }

    public function edit(Tool $tool)
    {
        $tool->load(['category:id,name', 'location:id,name']);

        return Inertia::render('Tools/Edit', array_merge(['tool' => $tool], $this->catalogos()));
    }

    /** Editar nunca borra evidencia: solo puede agregar un archivo nuevo. */
    public function update(Request $request, Tool $tool)
    {
        $datos = $this->validar($request, $tool);
        $evidencia = Arr::pull($datos, 'evidencia');

        $tool->update($datos);

        if ($evidencia) {
            ToolMedia::guardar($tool, $evidencia, $request->user()?->id);
        }

        return redirect()->route('tools.index')->with('success', 'Herramienta actualizada.');
    }

    /** Baja lógica: conserva el registro y lo deja inactivo. */
    public function destroy(Tool $tool)
    {
        $tool->update(['status' => Tool::ESTADO_BAJA]);

        return redirect()->route('tools.index')
            ->with('success', 'Herramienta dada de baja. El registro se conserva en el historial.');
    }

    private function validar(Request $request, ?Tool $tool = null): array
    {
        $request->merge([
            'internal_code' => strtoupper(trim((string) $request->input('internal_code'))),
        ]);

        return $request->validate([
            'category_id'   => ['nullable', Rule::exists('categories', 'id')->where('type', Category::TYPE_TOOL)],
            'location_id'   => ['nullable', Rule::exists('locations', 'id')],
            'name'          => ['required', 'string', 'max:255'],
            'brand'         => ['nullable', 'string', 'max:120'],
            'model'         => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120', Rule::unique('tools', 'serial_number')->ignore($tool?->id)],
            'internal_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/', Rule::unique('tools', 'internal_code')->ignore($tool?->id)],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'status'        => ['required', Rule::in(Tool::ESTADOS)],
            'condition'     => ['nullable', Rule::in(Tool::CONDICIONES)],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'evidencia'     => array_merge(['nullable'], ToolMedia::reglaEvidencia()),
        ], [
            'internal_code.regex' => 'El código interno solo puede tener letras mayúsculas, números y guiones (ej. HER-0001).',
            'evidencia.mimetypes' => 'Formato no permitido. Usa JPEG, PNG, WEBP, MP4 o WebM.',
            'evidencia.max'       => 'El archivo supera el tamaño máximo permitido.',
            'evidencia.uploaded'  => 'El archivo no se pudo subir. Puede superar el límite del servidor.',
        ]);
    }

    private function categorias()
    {
        return Category::where('type', Category::TYPE_TOOL)->orderBy('name')->get(['id', 'name']);
    }

    private function catalogos(): array
    {
        return [
            'categories' => $this->categorias(),
            'locations'  => Location::where('active', true)->orderBy('name')->get(['id', 'name']),
            'estados'    => Tool::ESTADOS,
            'condiciones' => Tool::CONDICIONES,
        ];
    }
}
