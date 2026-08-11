<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fiche compagnon du User (rôle enseignant sur establishment_user), sur le
 * même principe que Guardian — ne remplace aucune référence existante
 * (teacher_classroom_subject.user_id continue de pointer users.id, RBAC
 * inchangé). Sert uniquement de support d'identité/uid pour l'usage
 * offline et code-barres. Voir
 * docs/superpowers/specs/2026-08-10-uid-local-serveur-prefixe-design.md.
 */
class Teacher extends Model
{
    use HasFactory;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'user_id',
        'name',
        'phone',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '222';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
