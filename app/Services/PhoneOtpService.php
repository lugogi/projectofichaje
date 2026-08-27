<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PhoneOtpService
{
    private const TTL_SECONDS = 600;

    private const VERIFIED_TTL_SECONDS = 1800;

    public function send(string $phone): array
    {
        $normalized = $this->normalizePhone($phone);

        if (! preg_match('/^\+?[0-9]{9,15}$/', $normalized)) {
            return ['ok' => false, 'message' => 'El teléfono no tiene un formato válido.'];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->cacheKey($normalized), $code, self::TTL_SECONDS);

        if (config('app.debug')) {
            Log::info('OTP de verificación (solo desarrollo)', [
                'phone' => $normalized,
                'code' => $code,
            ]);
        }

        // Integración SMS real: Twilio, Vonage, etc.
        // SMS::send($normalized, "Tu código FichaTime es: {$code}");

        return [
            'ok' => true,
            'message' => 'Código enviado por SMS.',
            'debug_code' => config('app.debug') ? $code : null,
        ];
    }

    public function verify(string $phone, string $code): array
    {
        $normalized = $this->normalizePhone($phone);
        $stored = Cache::get($this->cacheKey($normalized));

        if (! $stored || ! hash_equals((string) $stored, trim($code))) {
            return ['ok' => false, 'message' => 'Código incorrecto o expirado.'];
        }

        Cache::forget($this->cacheKey($normalized));
        Cache::put($this->verifiedKey($normalized), true, self::VERIFIED_TTL_SECONDS);

        return ['ok' => true, 'message' => 'Teléfono verificado correctamente.'];
    }

    public function isVerified(string $phone): bool
    {
        return (bool) Cache::get($this->verifiedKey($this->normalizePhone($phone)), false);
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '34') && strlen($digits) > 9) {
            return '+' . $digits;
        }

        if (strlen($digits) === 9) {
            return '+34' . $digits;
        }

        return $digits;
    }

    private function cacheKey(string $phone): string
    {
        return 'employee_app_otp:' . $phone;
    }

    private function verifiedKey(string $phone): string
    {
        return 'employee_app_otp_verified:' . $phone;
    }
}
