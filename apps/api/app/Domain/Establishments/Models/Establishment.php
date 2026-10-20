<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Sync\Concerns\Syncable;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Establishment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;

    protected $fillable = [
        'foundation_id',
        'name',
        'slug',
        'type',
        'address',
        'phone',
        'is_active',
        'inspection_id',
        'direction_id',
        'opening_code',
        'dsps_code',
        'latitude',
        'longitude',
        'email',
        'is_arabe',
        'arabic_timetable_days',
        'logo_path',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'type' => EstablishmentType::class,
        'is_arabe' => 'boolean',
        'arabic_timetable_days' => 'array',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '211';
    }

    /**
     * @return BelongsTo<Foundation, $this>
     */
    public function foundation(): BelongsTo
    {
        return $this->belongsTo(Foundation::class);
    }

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

    /**
     * @return BelongsToMany<User, $this, EstablishmentUserPivot>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'establishment_user')
            ->using(EstablishmentUserPivot::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function director(): ?User
    {
        return $this->users()
            ->wherePivot('role', 'directeur')
            ->wherePivot('is_active', true)
            ->first();
    }

    public function isPrescolairePrimaire(): bool
    {
        return $this->type === EstablishmentType::PrescolairePrimaire;
    }

    public function isSecondaire(): bool
    {
        return $this->type === EstablishmentType::Secondaire;
    }

    /**
     * Jours de cours de la filière arabe, dans l'ordre de la semaine ; du
     * lundi au vendredi tant que l'école ne les a pas choisis — voir
     * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
     *
     * @return list<DayOfWeek>
     */
    public function arabicTimetableDays(): array
    {
        $chosen = array_map(
            fn (mixed $value) => is_string($value) ? DayOfWeek::tryFrom($value) : null,
            (array) ($this->arabic_timetable_days ?? []),
        );

        $days = array_values(array_filter(DayOfWeek::cases(), fn (DayOfWeek $day) => in_array($day, $chosen, true)));

        return $days !== [] ? $days : DayOfWeek::weekdays();
    }

    /**
     * @return array<int, Cycle>
     */
    public function allowedCycles(): array
    {
        return $this->isPrescolairePrimaire()
            ? [Cycle::Prescolaire, Cycle::Primaire]
            : [Cycle::Secondaire];
    }
}
