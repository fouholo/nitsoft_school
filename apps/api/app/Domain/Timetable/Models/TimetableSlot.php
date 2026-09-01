<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pas de SoftDeletes : table de configuration (comme TeacherAssignment),
 * pas un enregistrement historique. Combiné à l'index unique
 * (establishment_id, sequence), un soft delete laisserait une ligne
 * "supprimée" bloquer indéfiniment la réutilisation du même numéro de
 * créneau — piège rencontré en production, voir mémoire projet.
 */
class TimetableSlot extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'label',
        'start_time',
        'end_time',
        'sequence',
        'is_break',
    ];

    protected $casts = [
        'is_break' => 'boolean',
    ];

    /**
     * @return HasMany<TimetableSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TimetableSession::class, 'timetable_slot_id');
    }
}
