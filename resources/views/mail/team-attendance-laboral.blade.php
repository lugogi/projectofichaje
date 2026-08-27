<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registros de plantilla</title>
</head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.5;">
    <p>Hola{{ $destinatario ? ' '.$destinatario : '' }},</p>

    <p>
        Adjuntamos el informe de registros de plantilla correspondiente a
        <strong>{{ $context['mes_label'] ?? '' }}</strong>.
    </p>

    <p>
        Ámbito: {{ $context['filtros'] ?? 'Toda la plantilla' }}.<br>
        Enviado por {{ $remitente }} desde FichaTime.
    </p>

    <p>Un saludo.</p>
</body>
</html>
