<?php

declare(strict_types=1);

namespace App\Domain\Billing\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Une valeur de réduction de type « pourcentage » ne dépasse pas 100.
 */
class MaxPercentage implements ValidationRule
{
    public function __construct(private readonly string $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->type === 'percentage' && (float) $value > 100) {
            $fail(__('Le pourcentage ne peut pas dépasser 100.'));
        }
    }
}
