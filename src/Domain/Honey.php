<?php

declare(strict_types=1);

namespace BeeBank\Domain;

final class Honey
{
    public static function format(int $grams): string
    {
        if (abs($grams) < 1000) {
            return $grams.' г';
        }

        return rtrim(rtrim(number_format($grams / 1000, 3, '.', ' '), '0'), '.').' кг';
    }
}
