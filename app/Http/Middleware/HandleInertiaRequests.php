<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
                'role' => $request->user()?->role,
                'isAdmin' => $request->user()?->isAdmin() ?? false,
                'isOwner' => $request->user()?->isOwner() ?? false,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'last_id' => fn () => $request->session()->get('last_id'),
            ],
            'settings' => fn () => \App\Models\Setting::current(),
            'logo_url' => fn () => route('settings.logo'),
            'modules' => fn () => \App\Support\Modules::enabledMap(),
        ]);
    }
}
