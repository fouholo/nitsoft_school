<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use Illuminate\Database\Eloquent\Model;

class Nationalite extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'libelle',
    ];
}
