<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registros de plantilla {{ $context['mes_label'] ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 12px; margin-top: 18px; margin-bottom: 6px; }
        .meta { color: #64748b; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
        th { background: #f1f5f9; }
        td.num, th.num { text-align: right; }
        .note { margin-top: 12px; font-size: 9px; color: #64748b; }
        .total { font-weight: bold; background: #f8fafc; }
    </style>
</head>
<body>
    <h1>Registros de plantilla</h1>
    <p class="meta">
        Periodo: {{ $context['mes_label'] ?? '' }}<br>
        Ámbito: {{ $context['filtros'] ?? 'Toda la plantilla' }}<br>
        Generado: {{ now()->format('d/m/Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Trabajador</th>
                <th>Puesto</th>
                <th class="num">Horas contrato</th>
                <th class="num">Horas trabajadas</th>
                <th class="num">Horas nómina</th>
                <th class="num">Horas extra</th>
                <th class="num">€/h extra</th>
                <th class="num">Importe extra</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reports as $report)
                <tr>
                    <td>{{ $report['empleado']['codigo'] ?? '—' }}</td>
                    <td>{{ $report['empleado']['nombre'] }}</td>
                    <td>{{ $report['empleado']['puesto'] ?? '—' }}</td>
                    <td class="num">{{ $report['contrato']['decimal_esperado_mes'] }}</td>
                    <td class="num">{{ $report['perfil']['decimal_fichado_real'] }}</td>
                    <td class="num">{{ $report['exportacion']['decimal_incluido'] }}</td>
                    <td class="num">{{ $report['horas_extra']['decimal'] ?? 0 }}</td>
                    <td class="num">{{ $report['horas_extra']['tarifa_formato'] ?? 'Sin tarifa' }}</td>
                    <td class="num">{{ $report['horas_extra']['importe_formato'] ?? '—' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">TOTAL ({{ $totales['trabajadores'] }} trabajador(es))</td>
                <td class="num">{{ $totales['horas_contrato'] }}</td>
                <td class="num">{{ $totales['horas_trabajadas'] }}</td>
                <td class="num">{{ $totales['horas_nomina'] }}</td>
                <td class="num">{{ $totales['horas_extra'] }}</td>
                <td></td>
                <td class="num">{{ number_format($totales['importe_horas_extra'], 2, ',', '.') }} €</td>
            </tr>
        </tbody>
    </table>

    <p class="note">
        Horas nómina = tope contractual del mes. Horas extra = fichadas de más, valoradas a la tarifa de cada trabajador.
        El detalle de fichajes por persona se incluye en el Excel y en el JSON.
    </p>

    <h2>Vacaciones y bajas laborales</h2>
    @if (empty($ausencias))
        <p class="note">Ninguna vacación ni baja laboral aprobada en este mes.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Nombre y apellidos</th>
                    <th>Periodo</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ausencias as $ausencia)
                    <tr>
                        <td>{{ $ausencia['nombre'] }}</td>
                        <td>{{ $ausencia['periodo'] }}</td>
                        <td>{{ $ausencia['tipo_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
