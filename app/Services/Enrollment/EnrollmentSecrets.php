<?php

namespace App\Services\Enrollment;

use App\Contracts\EnrollmentRandomSource;
use RuntimeException;

final class EnrollmentSecrets
{
    private const USER_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private EnrollmentRandomSource $random) {}

    public function token(): string
    {
        return rtrim(strtr(base64_encode($this->random->bytes(32)), '+/', '-_'), '=');
    }

    public function userCode(): string
    {
        $bytes = $this->random->bytes(8);
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::USER_ALPHABET[ord($bytes[$i]) % strlen(self::USER_ALPHABET)];
        }

        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    public function hash(string $value): string
    {
        $key = (string) config('centralcloud.enrollment.hash_key');
        if ($key === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('CENTRALCLOUD_ENROLLMENT_HASH_KEY is required.');
            }
            $key = (string) config('app.key');
        }

        return hash_hmac('sha256', trim($value), $key);
    }

    public function hashUserCode(string $value): string
    {
        return $this->hash(strtoupper(str_replace([' ', '-'], '', trim($value))));
    }

    public function equals(string $value, string $hash): bool
    {
        return hash_equals($hash, $this->hash($value));
    }
}
