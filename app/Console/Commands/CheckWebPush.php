<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Console\Command;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\VAPID;
use Throwable;

class CheckWebPush extends Command
{
    protected $signature = 'webpush:check';

    protected $description = 'Verifica que las notificaciones push están correctamente configuradas';

    public function handle(WebPushService $webPush): int
    {
        $ok = true;

        $ok = $this->check('Extensión openssl', extension_loaded('openssl')) && $ok;
        $ok = $this->check('Extensión mbstring', extension_loaded('mbstring')) && $ok;
        $ok = $this->check('Push habilitado (WEBPUSH_ENABLED)', (bool) config('webpush.enabled')) && $ok;
        $ok = $this->check('Clave pública VAPID', filled(config('webpush.public_key'))) && $ok;
        $ok = $this->check('Clave privada VAPID', filled(config('webpush.private_key'))) && $ok;

        $subject = (string) config('webpush.subject');
        $validSubject = str_starts_with($subject, 'mailto:') || str_starts_with($subject, 'https://');
        $ok = $this->check(
            "Subject válido para Apple ({$subject})",
            $validSubject,
            'Debe ser un mailto: o una URL https, o APNs rechazará los envíos.',
        ) && $ok;

        if (! $webPush->isConfigured()) {
            $this->newLine();
            $this->error('Falta configuración. Ejecuta: php artisan webpush:vapid');

            return self::FAILURE;
        }

        try {
            $vapid = VAPID::validate([
                'subject' => $subject,
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ]);

            VAPID::getVapidHeaders(
                'https://fcm.googleapis.com',
                $vapid['subject'],
                $vapid['publicKey'],
                $vapid['privateKey'],
                ContentEncoding::aes128gcm,
            );

            $this->check('Firma criptográfica de la cabecera VAPID', true);
        } catch (Throwable $e) {
            $this->check('Firma criptográfica de la cabecera VAPID', false, $e->getMessage());
            $ok = false;
        }

        $this->newLine();
        $this->line('Dispositivos suscritos: ' . PushSubscription::count());

        if (! $ok) {
            $this->newLine();
            $this->warn('Hay problemas de configuración. Revisa los puntos marcados arriba.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Todo correcto. Las notificaciones push están listas.');

        return self::SUCCESS;
    }

    private function check(string $label, bool $passed, ?string $hint = null): bool
    {
        $this->line(sprintf(
            '  %s  %s',
            $passed ? '<fg=green>OK  </>' : '<fg=red>FALLO</>',
            $label,
        ));

        if (! $passed && $hint) {
            $this->line("        <fg=gray>{$hint}</>");
        }

        return $passed;
    }
}
