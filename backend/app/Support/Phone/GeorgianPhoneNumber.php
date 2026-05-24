<?php

declare(strict_types=1);

namespace App\Support\Phone;

use InvalidArgumentException;

final class GeorgianPhoneNumber
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '+995'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '9955')) {
            return '+'.$digits;
        }

        throw new InvalidArgumentException('Invalid Georgian mobile number.');
    }

    public static function normalizeOrOriginal(string $phone): string
    {
        try {
            return self::normalize($phone);
        } catch (InvalidArgumentException) {
            return trim($phone);
        }
    }

    public static function mask(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '***';
        }

        if (strlen($digits) <= 6) {
            return substr($digits, 0, 2).str_repeat('*', max(0, strlen($digits) - 2));
        }

        return substr($digits, 0, 5).str_repeat('*', max(0, strlen($digits) - 8)).substr($digits, -3);
    }
}
