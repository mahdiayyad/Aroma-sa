<?php

declare(strict_types=1);

namespace App\Support;

/** Email helpers for email + password sign-in. */
final class Email
{
    /** One canonical spelling for lookups, limiter keys and stored codes. */
    public static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /** "j***@gmail.com" — enough for the customer to recognise it, nothing more. */
    public static function mask(?string $email): string
    {
        $email = (string) $email;
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return '•••';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }
}
