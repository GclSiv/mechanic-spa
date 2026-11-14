<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\ClientStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Importa clientes desde un CSV (separado por coma o punto y coma, como Excel en español).
 * Las filas inválidas o duplicadas se omiten y se reportan con su número de fila.
 */
class ImportClientsAction
{
    public const MAX_ROWS = 2000;

    public const REQUIRED = ['nombre', 'apellidos', 'telefono'];

    public function execute(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // BOM de Excel

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        $lines = array_values(array_filter(
            preg_split('/\r\n|\n|\r/', trim($raw)),
            fn ($l) => trim($l) !== ''
        ));

        if (count($lines) < 2) {
            throw ValidationException::withMessages(['file' => 'El archivo no tiene filas de datos.']);
        }

        if (count($lines) - 1 > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'El archivo excede ' . self::MAX_ROWS . ' filas.']);
        }

        $delimiter = str_contains($lines[0], ';') ? ';' : ',';
        $headers   = array_map(fn ($h) => $this->normalizeHeader($h), str_getcsv(array_shift($lines), $delimiter));

        foreach (self::REQUIRED as $column) {
            if (! in_array($column, $headers, true)) {
                throw ValidationException::withMessages(['file' => "Falta la columna obligatoria: {$column}."]);
            }
        }

        $status   = ClientStatus::firstOrCreate(['name' => 'Active'], ['color' => 'bg-green-500']);
        $valid    = [];
        $skipped  = [];
        $errors   = [];
        $seen     = [];

        foreach ($lines as $index => $line) {
            $fila  = $index + 2; // +1 por el encabezado, +1 porque las filas empiezan en 1
            $cells = str_getcsv($line, $delimiter);
            $row   = [];

            foreach ($headers as $i => $header) {
                $row[$header] = trim((string) ($cells[$i] ?? ''));
            }

            if (collect($row)->filter()->isEmpty()) {
                continue;
            }

            $rowErrors = [];

            foreach (self::REQUIRED as $column) {
                if (($row[$column] ?? '') === '') {
                    $rowErrors[] = "falta {$column}";
                }
            }

            $rfc = strtoupper($row['rfc'] ?? '');
            if ($rfc !== '' && ! in_array(strlen($rfc), [12, 13], true)) {
                $rowErrors[] = 'RFC con longitud inválida';
            }

            $email = $row['correo'] ?? '';
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'correo inválido';
            }

            if ($rowErrors) {
                $errors[] = "Fila {$fila}: " . implode(', ', $rowErrors);
                continue;
            }

            $phone = $row['telefono'];

            $duplicated = in_array('tel:' . $phone, $seen, true)
                || ($rfc !== '' && in_array('rfc:' . $rfc, $seen, true))
                || Client::where('phone', $phone)->exists()
                || ($rfc !== '' && Client::where('rfc', $rfc)->exists());

            if ($duplicated) {
                $skipped[] = "Fila {$fila}: ya existe un cliente con ese teléfono o RFC";
                continue;
            }

            $seen[] = 'tel:' . $phone;
            if ($rfc !== '') {
                $seen[] = 'rfc:' . $rfc;
            }

            $country = strtoupper($row['pais'] ?? '');

            $valid[] = [
                'first_name'        => Str::limit($row['nombre'], 255, ''),
                'last_name'         => Str::limit($row['apellidos'], 255, ''),
                'phone'             => Str::limit($phone, 20, ''),
                'address'           => ($row['direccion'] ?? '') !== '' ? Str::limit($row['direccion'], 255, '') : null,
                'rfc'               => $rfc !== '' ? $rfc : null,
                'email'             => $email !== '' ? $email : null,
                'country'           => in_array($country, ['MX', 'US'], true) ? $country : 'MX',
                'client_status_id'  => $status->id,
            ];
        }

        DB::transaction(function () use ($valid) {
            foreach (array_chunk($valid, 200) as $chunk) {
                foreach ($chunk as $data) {
                    Client::create($data);
                }
            }
        });

        return [
            'creados'  => count($valid),
            'omitidos' => $skipped,
            'errores'  => $errors,
        ];
    }

    private function normalizeHeader(string $header): string
    {
        $clean = Str::of(trim($header, " \t\"\xEF\xBB\xBF"))->ascii()->lower()->toString();

        return match ($clean) {
            'correo', 'email', 'correo electronico' => 'correo',
            'telefono', 'tel', 'celular'            => 'telefono',
            'direccion', 'domicilio'                => 'direccion',
            default                                 => $clean,
        };
    }
}
