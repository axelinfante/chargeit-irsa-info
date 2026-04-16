<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HistoryReportController extends Controller
{
    public function index(Request $request): View
    {
        $records = collect();
        $cursorPagination = null;

        $vendingCodes = collect();
        $selectedVending = $request->query('vending');
        $selectedFrom = $request->query('desde');
        $selectedTo = $request->query('hasta');
        $error = null;
        $vendingStockTotal = null;
        $totalLabel = null;
        $totalAmount = null;
        $perPage = 10;

        try {
            [$accessToken, $baseUrl] = $this->resolveFirestoreConnection();
            $hasActiveFilters = $this->hasActiveFilters($selectedVending, $selectedFrom, $selectedTo);

            if (! $hasActiveFilters) {
                $cursor = $this->decodeCursorToken($request->query('cursor'));
                $stack = $this->decodeCursorStack($request->query('stack'));
                $historyPage = $this->fetchHistoryPage($accessToken, $baseUrl, $cursor, $perPage);

                $records = $this->mapDocuments($historyPage['documents']);
                $vendingCodes = $this->fetchVendingCodes($accessToken, $baseUrl);
                $cursorPagination = $this->buildCursorPagination(
                    $request,
                    $stack,
                    $cursor,
                    $historyPage['nextPageToken'],
                    $records->count(),
                    $perPage
                );
            } else {
                $allDocuments = $this->fetchAllHistoryDocuments($accessToken, $baseUrl);
                $allRecords = $this->mapDocuments($allDocuments);

                $vendingCodes = $this->extractDistinctVendingCodes($allRecords);
                $filtered = $this->filterRecordsToCollection($allRecords, $selectedVending, $selectedFrom, $selectedTo);
                $records = $this->paginateCollection($filtered, $perPage);
                $records->appends($request->except(['page', 'cursor', 'stack']));

                [$totalLabel, $totalAmount] = $this->computeTotalsForFilters($filtered, $selectedVending);
            }

            if (is_string($selectedVending) && trim($selectedVending) !== '') {
                try {
                    $vendingStockTotal = $this->sumEspiralesStock($accessToken, $baseUrl, $selectedVending);
                } catch (Throwable $stockException) {
                    Log::warning('No se pudo cargar el stock de espirales para el vending.', [
                        'vending' => $selectedVending,
                        'exception' => $stockException,
                    ]);
                    $vendingStockTotal = null;
                }
            }
        } catch (Throwable $exception) {
            Log::error('No se pudo cargar la coleccion history de Firestore.', [
                'exception' => $exception,
            ]);

            $error = 'No fue posible cargar el reporte en este momento. Revisa las credenciales de Firebase y los logs.';
        }

        return view('history-report', [
            'records' => $records,
            'cursorPagination' => $cursorPagination,
            'vendingCodes' => $vendingCodes,
            'selectedVending' => $selectedVending,
            'selectedFrom' => $selectedFrom,
            'selectedTo' => $selectedTo,
            'vendingStockTotal' => $vendingStockTotal,
            'totalLabel' => $totalLabel,
            'totalAmount' => $totalAmount,
            'error' => $error,
        ]);
    }

    public function export(Request $request)
    {
        $selectedVending = $request->query('vending');
        $selectedFrom = $request->query('desde');
        $selectedTo = $request->query('hasta');

        try {
            [$accessToken, $baseUrl] = $this->resolveFirestoreConnection();
            $allDocuments = $this->fetchAllHistoryDocuments($accessToken, $baseUrl);
            $allRecords = $this->mapDocuments($allDocuments);
            $filtered = $this->filterRecordsToCollection($allRecords, $selectedVending, $selectedFrom, $selectedTo);

            $directory = public_path('exports/history');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $fileName = 'history_export_'.now()->format('Ymd_His').'.csv';
            $fullPath = $directory.DIRECTORY_SEPARATOR.$fileName;

            $handle = fopen($fullPath, 'w');
            if ($handle === false) {
                throw new \RuntimeException('No se pudo crear el archivo de exportación.');
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Cantidad', 'Codigo', 'Fecha', 'Tipo', 'Vending Code'], ';');

            foreach ($filtered as $record) {
                fputcsv($handle, [
                    (string) ($record['id'] ?? ''),
                    (string) ($record['cantidad'] ?? ''),
                    (string) ($record['codigo'] ?? ''),
                    (string) ($record['fecha'] ?? ''),
                    (string) ($record['tipo'] ?? ''),
                    (string) ($record['vendingCode'] ?? ''),
                ], ';');
            }

            fclose($handle);

            return response()->download($fullPath, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        } catch (Throwable $exception) {
            Log::error('No se pudo exportar el reporte history.', [
                'exception' => $exception,
            ]);

            return response('No fue posible generar el archivo de exportación en este momento.', 500);
        }
    }

    private function resolveFirestoreConnection(): array
    {
        if (! class_exists(ServiceAccountCredentials::class)) {
            throw new \RuntimeException('Falta la dependencia "google/auth". Ejecuta composer update.');
        }

        $serviceAccount = [
            'type' => 'service_account',
            'project_id' => config('firebase.project_id'),
            'private_key' => str_replace('\n', PHP_EOL, (string) config('firebase.private_key')),
            'client_email' => config('firebase.client_email'),
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ];

        $credentials = new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/datastore'],
            $serviceAccount
        );

        $tokenData = $credentials->fetchAuthToken();
        $accessToken = $tokenData['access_token'] ?? null;

        if (! $accessToken) {
            throw new \RuntimeException('No se pudo obtener un access token para Firestore.');
        }

        $projectId = (string) config('firebase.project_id');
        $baseUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)";

        return [$accessToken, $baseUrl];
    }

    private function hasActiveFilters(?string $selectedVending, ?string $selectedFrom, ?string $selectedTo): bool
    {
        return (is_string($selectedVending) && trim($selectedVending) !== '')
            || (is_string($selectedFrom) && trim($selectedFrom) !== '')
            || (is_string($selectedTo) && trim($selectedTo) !== '');
    }

    private function fetchAllHistoryDocuments(string $accessToken, string $baseUrl): array
    {
        $allDocuments = [];
        $pageToken = null;
        $pageSize = 300;

        do {
            $queryParams = ['pageSize' => $pageSize];
            if ($pageToken !== null) {
                $queryParams['pageToken'] = $pageToken;
            }

            $response = Http::timeout(30)
                ->withToken($accessToken)
                ->get("{$baseUrl}/documents/history", $queryParams);

            if (! $response->successful()) {
                throw new \RuntimeException('Error Firestore REST (history): '.$response->status().' '.$response->body());
            }

            $data = $response->json();
            $documents = $data['documents'] ?? [];
            $allDocuments = array_merge($allDocuments, $documents);
            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return $allDocuments;
    }

    private function fetchHistoryPage(string $accessToken, string $baseUrl, ?string $cursor, int $perPage): array
    {
        $queryParams = ['pageSize' => $perPage];
        if ($cursor !== null && $cursor !== '') {
            $queryParams['pageToken'] = $cursor;
        }

        $response = Http::timeout(30)
            ->withToken($accessToken)
            ->get("{$baseUrl}/documents/history", $queryParams);

        if (! $response->successful()) {
            throw new \RuntimeException('Error Firestore REST (history page): '.$response->status().' '.$response->body());
        }

        return [
            'documents' => $response->json('documents', []),
            'nextPageToken' => $response->json('nextPageToken'),
        ];
    }

    private function fetchVendingCodes(string $accessToken, string $baseUrl): Collection
    {
        $codes = [];
        $pageToken = null;

        do {
            $queryParams = ['pageSize' => 200];
            if ($pageToken !== null) {
                $queryParams['pageToken'] = $pageToken;
            }

            $response = Http::timeout(20)
                ->withToken($accessToken)
                ->get("{$baseUrl}/documents/config", $queryParams);

            if (! $response->successful()) {
                Log::warning('No se pudieron cargar los vending codes desde config.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $documents = $response->json('documents', []);
            foreach ($documents as $document) {
                if (! is_array($document)) {
                    continue;
                }

                $name = (string) ($document['name'] ?? '');
                $code = trim((string) basename($name));
                if ($code !== '') {
                    $codes[] = $code;
                }
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return collect($codes)->unique()->sort()->values();
    }

    private function buildCursorPagination(
        Request $request,
        array $stack,
        ?string $currentCursor,
        ?string $nextCursor,
        int $currentCount,
        int $perPage
    ): array {
        $currentPage = count($stack) + 1;
        $hasPrevious = $stack !== [];
        $hasNext = is_string($nextCursor) && trim($nextCursor) !== '';
        $from = $currentCount > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = $currentCount > 0 ? $from + $currentCount - 1 : 0;

        $previousUrl = null;
        if ($hasPrevious) {
            $previousStack = $stack;
            $previousCursor = array_pop($previousStack);
            $previousCursor = $previousCursor === '' ? null : $previousCursor;
            $previousUrl = $this->buildCursorUrl($request, $previousCursor, $previousStack);
        }

        $nextUrl = null;
        if ($hasNext) {
            $nextStack = $stack;
            $nextStack[] = $currentCursor ?? '';
            $nextUrl = $this->buildCursorUrl($request, $nextCursor, $nextStack);
        }

        return [
            'currentPage' => $currentPage,
            'from' => $from,
            'to' => $to,
            'hasPrevious' => $hasPrevious,
            'hasNext' => $hasNext,
            'previousUrl' => $previousUrl,
            'nextUrl' => $nextUrl,
        ];
    }

    private function buildCursorUrl(Request $request, ?string $cursor, array $stack): string
    {
        $params = $request->except(['page', 'cursor', 'stack']);
        if ($cursor !== null && $cursor !== '') {
            $params['cursor'] = $this->encodeCursorToken($cursor);
        }
        if ($stack !== []) {
            $params['stack'] = $this->encodeCursorStack($stack);
        }

        return route('history.report', $params);
    }

    private function encodeCursorToken(string $token): string
    {
        return rtrim(strtr(base64_encode($token), '+/', '-_'), '=');
    }

    private function decodeCursorToken(mixed $token): ?string
    {
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        $normalized = strtr($token, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    private function encodeCursorStack(array $stack): string
    {
        return rtrim(strtr(base64_encode((string) json_encode(array_values($stack))), '+/', '-_'), '=');
    }

    private function decodeCursorStack(mixed $stack): array
    {
        if (! is_string($stack) || trim($stack) === '') {
            return [];
        }

        $normalized = strtr($stack, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if (! is_string($decoded) || trim($decoded) === '') {
            return [];
        }

        $parsed = json_decode($decoded, true);
        if (! is_array($parsed)) {
            return [];
        }

        return array_values(array_filter($parsed, fn ($value) => is_string($value)));
    }

    // ===================================================================
    // Los métodos privados se mantienen exactamente igual que antes
    // ===================================================================

    private function mapDocuments(iterable $documents): Collection
    {
        $rows = [];

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $name = (string) ($document['name'] ?? '');
            $id = $name !== '' ? basename($name) : null;
            $fields = $document['fields'] ?? [];

            $rows[] = [
                'id' => $id,
                'cantidad' => $this->readFirestoreValue($fields['cantidad'] ?? null),
                'codigo' => $this->readFirestoreValue($fields['codigo'] ?? null),
                'fecha' => $this->readFirestoreValue($fields['fecha'] ?? null),
                'tipo' => $this->readFirestoreValue($fields['tipo'] ?? null),
                'vendingCode' => $this->readFirestoreValue($fields['vendingCode'] ?? null),
            ];
        }

        return collect($rows);
    }

    private function readFirestoreValue(mixed $value): mixed
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        if (array_key_exists('stringValue', $value)) {
            return $value['stringValue'];
        }

        if (array_key_exists('integerValue', $value)) {
            return (int) $value['integerValue'];
        }

        if (array_key_exists('doubleValue', $value)) {
            return (float) $value['doubleValue'];
        }

        if (array_key_exists('booleanValue', $value)) {
            return (bool) $value['booleanValue'];
        }

       // ==================== CAMBIO AQUÍ ====================
    if (array_key_exists('timestampValue', $value)) {
        $timestampStr = $value['timestampValue']; // Ej: "2026-04-16T18:30:00Z" o con fracción

        try {
            // Creamos el DateTime en UTC
            $date = new \DateTime($timestampStr, new \DateTimeZone('UTC'));
            
            // Convertimos a hora de Argentina (UTC-3)
            $date->setTimezone(new \DateTimeZone('America/Argentina/Buenos_Aires'));
            
            // Devolvemos en formato legible (podés cambiar el formato)
            return $date->format('Y-m-d H:i:s');   // Ej: 2026-04-16 15:30:00
            // O si preferís solo fecha: return $date->format('Y-m-d');
            
        } catch (\Exception $e) {
            Log::warning('Error al convertir timestamp de Firestore', [
                'timestamp' => $timestampStr,
                'error' => $e->getMessage()
            ]);
            return $timestampStr; // fallback
        }
    }
    // ====================================================
        if (array_key_exists('nullValue', $value)) {
            return null;
        }

        if (array_key_exists('arrayValue', $value)) {
            $items = $value['arrayValue']['values'] ?? [];
            return collect($items)->map(fn ($item) => $this->readFirestoreValue($item))->all();
        }

        if (array_key_exists('mapValue', $value)) {
            $fields = $value['mapValue']['fields'] ?? [];
            $mapped = [];
            foreach ($fields as $key => $item) {
                $mapped[$key] = $this->readFirestoreValue($item);
            }
            return $mapped;
        }

        return null;
    }

    private function extractDistinctVendingCodes(Collection $records): Collection
    {
        return $records
            ->pluck('vendingCode')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->unique()
            ->sort()
            ->values();
    }

    private function computeTotalsForFilters(Collection $filtered, ?string $selectedVending): array
    {
        $specificVending = is_string($selectedVending) && trim($selectedVending) !== '';

        if (! $specificVending) {
            return ['Total general', $this->sumCantidad($filtered)];
        }

        $retiros = $filtered->filter(fn ($record) => $this->isTipoRetiro($record['tipo'] ?? null));

        return ['Total de retiro en vending', $this->sumCantidad($retiros)];
    }

    private function isTipoRetiro(mixed $tipo): bool
    {
        if (! is_string($tipo) || trim($tipo) === '') {
            return false;
        }

        return str_contains(mb_strtolower(trim($tipo), 'UTF-8'), 'retiro');
    }

    private function sumCantidad(Collection $records): float
    {
        return (float) $records->sum(function ($record) {
            return $this->cantidadToFloat($record['cantidad'] ?? null);
        });
    }

    private function cantidadToFloat(mixed $cantidad): float
    {
        if (is_int($cantidad) || is_float($cantidad)) {
            return (float) $cantidad;
        }

        if (is_string($cantidad) && is_numeric($cantidad)) {
            return (float) $cantidad;
        }

        return 0.0;
    }

    private function filterRecordsToCollection(
        Collection $records,
        ?string $selectedVending,
        ?string $selectedFrom,
        ?string $selectedTo
    ): Collection {
        $from = $this->normalizeDateInput($selectedFrom);
        $to = $this->normalizeDateInput($selectedTo);

        return $records
            ->filter(function ($record) use ($selectedVending, $from, $to) {
                if (is_string($selectedVending) && trim($selectedVending) !== '') {
                    if (($record['vendingCode'] ?? null) !== $selectedVending) {
                        return false;
                    }
                }

                if (! $from && ! $to) {
                    return true;
                }

                $recordDate = $this->extractComparableDate($record['fecha'] ?? null);
                if (! $recordDate) {
                    return false;
                }

                if ($from && $recordDate < $from) {
                    return false;
                }

                if ($to && $recordDate > $to) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    private function normalizeDateInput(?string $date): ?string
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    private function extractComparableDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $text = Str::lower(trim($value));
        $months = [
            'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
            'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
            'septiembre' => '09', 'setiembre' => '09', 'octubre' => '10',
            'noviembre' => '11', 'diciembre' => '12',
        ];

        foreach ($months as $name => $number) {
            $text = str_replace($name, $number, $text);
        }

        if (preg_match('/(\d{1,2})\s+de\s+(\d{2})\s+de\s+(\d{4})/', $text, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $text, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        $timestamp = strtotime($text);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function sumEspiralesStock(string $accessToken, string $baseUrl, string $vendingCode): int
    {
        $segment = rawurlencode($vendingCode);
        $url = "{$baseUrl}/documents/config/{$segment}/espirales";
        $total = 0;
        $pageToken = null;

        do {
            $query = ['pageSize' => 100];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::timeout(20)
                ->withToken($accessToken)
                ->get($url, $query);

            if (! $response->successful()) {
                throw new \RuntimeException('Error Firestore REST (espirales): '.$response->status().' '.$response->body());
            }

            $documents = $response->json('documents', []);

            foreach ($documents as $document) {
                if (! is_array($document)) {
                    continue;
                }
                $fields = $document['fields'] ?? [];
                $stock = $this->readFirestoreValue($fields['stock'] ?? null);
                if (is_int($stock)) {
                    $total += $stock;
                } elseif (is_float($stock)) {
                    $total += (int) round($stock);
                }
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return $total;
    }

    private function paginateCollection(Collection $items, int $perPage = 10): LengthAwarePaginator
    {
        $currentPage = Paginator::resolveCurrentPage('page');
        $total = $items->count();
        $currentItems = $items->forPage($currentPage, $perPage)->values();

        return new LengthAwarePaginator(
            $currentItems,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }
}
