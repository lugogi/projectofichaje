<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid {--force : Sobrescribe las claves existentes en .env}';

    protected $description = 'Genera el par de claves VAPID necesarias para las notificaciones push';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('No se encontró el archivo .env');

            return self::FAILURE;
        }

        $env = file_get_contents($envPath);
        $alreadySet = preg_match('/^VAPID_PUBLIC_KEY=.+$/m', $env) === 1;

        if ($alreadySet && ! $this->option('force')) {
            $this->warn('Ya existen claves VAPID. Usa --force para regenerarlas.');
            $this->line('Atención: regenerarlas invalida todas las suscripciones actuales.');

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();

        $env = $this->setEnvValue($env, 'VAPID_PUBLIC_KEY', $keys['publicKey']);
        $env = $this->setEnvValue($env, 'VAPID_PRIVATE_KEY', $keys['privateKey']);

        file_put_contents($envPath, $env);

        $this->info('Claves VAPID generadas y guardadas en .env');
        $this->line('Clave pública: ' . $keys['publicKey']);

        return self::SUCCESS;
    }

    private function setEnvValue(string $env, string $key, string $value): string
    {
        $line = "{$key}=\"{$value}\"";

        if (preg_match("/^{$key}=.*$/m", $env)) {
            return preg_replace("/^{$key}=.*$/m", $line, $env);
        }

        return rtrim($env, "\n") . "\n{$line}\n";
    }
}
