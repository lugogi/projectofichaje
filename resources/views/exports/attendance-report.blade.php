<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro horario {{ $report['periodo']['mes_label'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 20px; margin-bottom: 8px; }
        .meta { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #f1f5f9; }
        .note { margin-top: 14px; font-size: 10px; color: #64748b; }
        .summary td:first-child { font-weight: bold; width: 45%; }
    </style>
</head>
<body>
    <h1>Registro horario</h1>
    <p class="meta">
        {{ $report['empleado']['nombre'] }} · {{ $report['empleado']['email'] }}<br>
        Periodo: {{ $report['periodo']['mes_label'] }}
    </p>

    <table class="summary">
        <tr><td>Horas según contrato (mes)</td><td>{{ $report['contrato']['formato_esperado_mes'] }}</td></tr>
        <tr><td>Horas exportadas</td><td>{{ $report['exportacion']['formato_incluido'] }}</td></tr>
        <tr><td>Horas omitidas (extra)</td><td>{{ $report['exportacion']['formato_omitido'] }}</td></tr>
        <tr><td>Días laborables del mes</td><td>{{ $report['contrato']['dias_laborables'] }}</td></tr>
    </table>

    <h2>Jornadas incluidas</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Duración</th>
                <th>Zona</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['exportacion']['jornadas'] as $jornada)
                <tr>
                    <td>{{ $jornada['fecha_label'] }}</td>
                    <td>{{ $jornada['entrada'] }}</td>
                    <td>{{ $jornada['salida'] }}@if($jornada['parcial']) *@endif</td>
                    <td>{{ $jornada['duracion'] }}</td>
                    <td>{{ $jornada['zona'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin jornadas en este periodo.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detalle de fichajes</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Hora</th>
                <th>Zona</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['exportacion']['fichajes'] as $fichaje)
                <tr>
                    <td>{{ $fichaje['fecha'] }}</td>
                    <td>{{ $fichaje['label'] }}</td>
                    <td>{{ $fichaje['hora'] }}</td>
                    <td>{{ $fichaje['zona'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin fichajes exportables.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="note">
        * Salida ajustada al tope contractual mensual.<br>
        {{ $report['exportacion']['nota_legal'] }}
    </p>
</body>
</html>
