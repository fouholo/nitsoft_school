<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'libelle',
    ];
}
