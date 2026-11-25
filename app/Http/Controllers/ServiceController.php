<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Catálogo de servicios con comisión fija (solo admin/owner; las rutas llevan role:admin).
 * Modificar commission_amount o el nombre NO altera los conceptos existentes: cada uno guarda su snapshot.
 */
class ServiceController extends Controller
{
    public function index()
    {
        $servicios = Service::withExists('items')
            ->orderBy('name')
            ->get()
            ->map(fn (Service $s) => $this->paraVista($s));

        return Inertia::render('Services/Index', ['services' => $servicios]);
    }

    public function create()
    {
        return Inertia::render('Services/Create');
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        Service::create($datos);

        return redirect()->route('services.index')->with('success', 'Servicio registrado.');
    }

    public function edit(Service $service)
    {
        return Inertia::render('Services/Edit', [
            'service' => $this->paraVista($service->loadExists('items')),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $datos = $this->validar($request, $service);

        // Solo cambia el catálogo. Los conceptos conservan su commission_amount_snapshot.
        $service->update($datos);

        return redirect()->route('services.index')->with('success', 'Servicio actualizado.');
    }

    public function destroy(Service $service)
    {
        if ($service->items()->exists()) {
            return back()->withErrors([
                'service' => 'No se puede eliminar: el servicio tiene conceptos registrados. Puedes desactivarlo.',
            ]);
        }

        $service->delete();

        return redirect()->route('services.index')->with('success', 'Servicio eliminado.');
    }

    private function validar(Request $request, ?Service $service = null): array
    {
        $datos = $request->validate([
            'name'              => ['required', 'string', 'max:120', Rule::unique('services', 'name')->ignore($service?->id)],
            'commission_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active'         => ['sometimes', 'boolean'],
        ]);

        $datos['is_active'] = $request->boolean('is_active', true);

        return $datos;
    }

    private function paraVista(Service $service): array
    {
        return [
            'id'                => $service->id,
            'name'              => $service->name,
            'commission_amount' => number_format((float) $service->commission_amount, 2, '.', ''),
            'is_active'         => (bool) $service->is_active,
            'en_uso'            => (bool) ($service->items_exists ?? $service->items()->exists()),
        ];
    }
}
