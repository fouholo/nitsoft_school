<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use App\Domain\Establishments\Enums\EstablishmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Demande d'inscription en attente d'un fondateur qui crée son école —
 * voir App\Domain\Establishments\Services\SchoolRegistrationApprover.
 *
 * @property int $id
 * @property string $name
 * @property string $first_name
 * @property string $email
 * @property string $pseudo
 * @property string $password
 * @property string $establishment_name
 * @property EstablishmentType $establishment_type
 * @property int|null $inspection_id
 * @property int|null $direction_id
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $foundation_name
 * @property Carbon $created_at
 */
class SchoolRegistration extends Model
{
    protected $fillable = [
        'name',
        'first_name',
        'email',
        'pseudo',
        'password',
        'establishment_name',
        'establishment_type',
        'inspection_id',
        'direction_id',
        'phone',
        'address',
        'foundation_name',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'establishment_type' => EstablishmentType::class,
    ];

    /**
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * @return BelongsTo<Direction, $this>
     */
    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function isForFoundation(): bool
    {
        return $this->foundation_name !== null;
    }
}
