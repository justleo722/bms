<?php

namespace App\Settings;

class RegistrationCode
{
    /**
     * Generate a random, readable, single-use entry code.
     */
    public static function generate(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'BMS-'.$code;
    }
}
