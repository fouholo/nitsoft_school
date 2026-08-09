<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'wording',
    ];
}
