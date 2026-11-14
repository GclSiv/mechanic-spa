<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Registro de módulos activables. Lee la configuración de config/modules.php
 * y el estado guardado en la tabla module_settings.
 */
class Modules
{
    public static function all(): array
    {
        return config('modules', []);
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function enabled(string $key): bool
    {
        if (! self::exists($key)) {
            return true; // núcleo: no está en la lista, siempre activo
        }

        $default = (bool) (self::all()[$key]['default'] ?? true);

        if (! Schema::hasTable('module_settings')) {
            return $default;
        }

        $row = \Illuminate\Support\Facades\DB::table('module_settings')->where('key', $key)->first();

        return $row ? (bool) $row->enabled : $default;
    }

    /** @return array<string, bool> */
    public static function enabledMap(): array
    {
        $map = [];
        foreach (array_keys(self::all()) as $key) {
            $map[$key] = self::enabled($key);
        }

        return $map;
    }

    public static function set(string $key, bool $enabled): void
    {
        \Illuminate\Support\Facades\DB::table('module_settings')->updateOrInsert(
            ['key' => $key],
            ['enabled' => $enabled, 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
