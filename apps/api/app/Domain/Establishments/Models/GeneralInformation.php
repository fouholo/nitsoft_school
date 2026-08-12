<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralInformation extends Model
{
    protected $fillable = [
        'nom_ministere',
        'annee_scolaire_courante',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
