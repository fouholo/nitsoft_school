<?php

declare(strict_types=1);

if (! function_exists('money')) {
    function money(float|int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' F CFA';
    }
}
