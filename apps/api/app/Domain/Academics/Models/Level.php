<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Domain\Academics\Enums\Cycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'level_wording',
        'cycle',
        'requires_series',
    ];

    protected $casts = [
        'cycle' => Cycle::class,
        'requires_series' => 'boolean',
    ];
}
