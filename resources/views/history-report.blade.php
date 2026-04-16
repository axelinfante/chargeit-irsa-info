<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte History</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #1f2937;
            background: #f3f4f6;
            margin: 0;
        }
        .page {
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 32px 16px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            width: min(1120px, 100%);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }
        h1 {
            margin: 0;
            font-size: 24px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .filters {
            display: flex;
            align-items: end;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        label {
            font-size: 13px;
            color: #475569;
            font-weight: 600;
        }
        select,
        input[type="date"] {
            min-width: 260px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            border-radius: 8px;
            height: 38px;
            padding: 0 10px;
            font-size: 14px;
            color: #0f172a;
        }
        .btn {
            border: 0;
            height: 38px;
            border-radius: 8px;
            padding: 0 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }
        .btn-light {
            background: #e2e8f0;
            color: #1e293b;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .error {
            margin-bottom: 16px;
            padding: 10px 12px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fef2f2;
            color: #991b1b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            border: 1px solid #e5e7eb;
            text-align: left;
            padding: 8px;
            vertical-align: top;
            font-size: 12px;
        }
        th {
            background: #f3f4f6;
            font-weight: 600;
            font-size: 11px;
            letter-spacing: .2px;
        }
        .muted {
            color: #6b7280;
            margin-top: 10px;
            font-size: 13px;
        }
        .table-wrap {
            overflow-x: auto;
        }
        .pagination {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pagination-info {
            color: #475569;
            font-size: 13px;
        }
        .pagination-links {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .page-link {
            border: 1px solid #cbd5e1;
            color: #1e293b;
            background: #fff;
            border-radius: 8px;
            height: 34px;
            min-width: 34px;
            padding: 0 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 13px;
        }
        .page-link.active {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
            font-weight: 700;
        }
        .page-link.disabled {
            opacity: .5;
            pointer-events: none;
        }
        .stock-banner {
            display: none;
            position: relative;
            margin-bottom: 20px;
            padding: 20px 22px;
            border-radius: 12px;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #bfdbfe;
            text-align: center;
        }
        .stock-banner.is-visible {
            display: block;
        }
        .stock-banner .stock-label {
            font-size: 14px;
            color: #1e40af;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .stock-banner .stock-value {
            font-size: 42px;
            font-weight: 800;
            color: #1d4ed8;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        .stock-banner .stock-hint {
            margin-top: 8px;
            font-size: 12px;
            color: #64748b;
        }
        .data-region {
            position: relative;
            min-height: 120px;
        }
        .async-loading {
            position: absolute;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            z-index: 5;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(2px);
        }
        .async-loading.is-active {
            display: flex;
        }
        .stock-banner .async-loading {
            border-radius: 12px;
            background: rgba(239, 246, 255, 0.92);
        }
        .loading-text {
            font-size: 13px;
            color: #475569;
            font-weight: 600;
        }
        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid #e2e8f0;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        body.is-loading {
            cursor: wait;
        }
        .totals-bar {
            margin-top: 14px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f8fafc;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .totals-bar .totals-label {
            font-size: 13px;
            color: #475569;
            font-weight: 600;
        }
        .totals-bar .totals-value {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="header">
                <h1>Reporte de movimientos (history)</h1>
            </div>

            <div class="stock-banner @if($selectedVending) is-visible @endif">
                @if ($selectedVending)
                    <div class="stock-label">Stock en vending {{ $selectedVending }}</div>
                    <div class="stock-value">
                        @if ($vendingStockTotal !== null)
                            {{ $vendingStockTotal }}
                        @else
                            —
                        @endif
                    </div>
                    @if ($vendingStockTotal === null && ! $error)
                        <div class="stock-hint">No se pudo leer el stock en config → espirales.</div>
                    @endif
                @endif
                <div class="async-loading" id="loading-stock" aria-hidden="true" aria-busy="false">
                    <div class="spinner" role="status" aria-label="Cargando stock"></div>
                    <span class="loading-text">Actualizando stock…</span>
                </div>
            </div>

            <form id="history-filters" method="GET" action="{{ route('history.report') }}" class="filters">
                <div class="filter-group">
                    <label for="vending">Vending</label>
                    <select id="vending" name="vending">
                        <option value="">Todos</option>
                        @foreach ($vendingCodes as $vendingCode)
                            <option value="{{ $vendingCode }}" @selected($selectedVending === $vendingCode)>
                                {{ $vendingCode }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label for="desde">Desde</label>
                    <input type="date" id="desde" name="desde" value="{{ $selectedFrom }}">
                </div>

                <div class="filter-group">
                    <label for="hasta">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="{{ $selectedTo }}">
                </div>

                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ route('history.report') }}" class="btn btn-light" id="btn-limpiar">Limpiar</a>
            </form>

            @if ($error)
                <div class="error">{{ $error }}</div>
            @endif

            <div class="data-region">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cantidad</th>
                            <th>Codigo</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Vending Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td>{{ $record['id'] }}</td>
                                <td>{{ $record['cantidad'] }}</td>
                                <td>{{ $record['codigo'] }}</td>
                                <td>{{ $record['fecha'] }}</td>
                                <td>{{ $record['tipo'] }}</td>
                                <td>{{ $record['vendingCode'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">Sin registros disponibles.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($totalLabel !== null && $totalAmount !== null)
                <div class="totals-bar" role="group" aria-label="Totales del filtro">
                    <span class="totals-label">{{ $totalLabel }}</span>
                    <span class="totals-value">{{ number_format($totalAmount, 0, ',', '.') }}</span>
                </div>
            @endif

            <div class="pagination">
                <div class="pagination-info">
                    Mostrando {{ $records->firstItem() ?? 0 }} - {{ $records->lastItem() ?? 0 }} de {{ $records->total() }} registros
                </div>
                <div class="pagination-links">
                    @if ($records->onFirstPage())
                        <span class="page-link disabled">Anterior</span>
                    @else
                        <a class="page-link" href="{{ $records->previousPageUrl() }}">Anterior</a>
                    @endif

                    @php
                        $currentPage = $records->currentPage();
                        $lastPage = $records->lastPage();
                        $startPage = max(1, $currentPage - 1);
                        $endPage = min($lastPage, $currentPage + 1);
                    @endphp

                    @foreach ($records->getUrlRange($startPage, $endPage) as $page => $url)
                        @if ($page === $records->currentPage())
                            <span class="page-link active">{{ $page }}</span>
                        @else
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($records->hasMorePages())
                        <a class="page-link" href="{{ $records->nextPageUrl() }}">Siguiente</a>
                    @else
                        <span class="page-link disabled">Siguiente</span>
                    @endif
                </div>
            </div>
                <div class="async-loading" id="loading-data" aria-hidden="true" aria-busy="false">
                    <div class="spinner" role="status" aria-label="Cargando datos"></div>
                    <span class="loading-text">Actualizando datos…</span>
                </div>
            </div>

        </div>
    </div>
    <script>
        (function () {
            var form = document.getElementById('history-filters');
            var loadingData = document.getElementById('loading-data');
            var loadingStock = document.getElementById('loading-stock');
            var stockBanner = document.querySelector('.stock-banner');

            function showLoading() {
                document.body.classList.add('is-loading');
                loadingData.classList.add('is-active');
                loadingData.setAttribute('aria-busy', 'true');
                loadingData.setAttribute('aria-hidden', 'false');
                if (stockBanner && stockBanner.classList.contains('is-visible')) {
                    loadingStock.classList.add('is-active');
                    loadingStock.setAttribute('aria-busy', 'true');
                    loadingStock.setAttribute('aria-hidden', 'false');
                }
            }

            document.getElementById('vending').addEventListener('change', function () {
                showLoading();
                form.submit();
            });

            form.addEventListener('submit', function () {
                showLoading();
            });

            var limpiar = document.getElementById('btn-limpiar');
            if (limpiar) {
                limpiar.addEventListener('click', function () {
                    showLoading();
                });
            }

            document.querySelectorAll('.pagination a.page-link').forEach(function (a) {
                a.addEventListener('click', function () {
                    showLoading();
                });
            });
        })();
    </script>
</body>
</html>
