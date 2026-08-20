<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Enums\SaasAdminType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Domain\Establishments\Models\SaasAdmin;
use App\Domain\Sync\Concerns\Syncable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, Syncable;

    /**
     * Mot de passe attribué par défaut à tout compte créé par un tiers
     * (staff, tuteur, admin...) plutôt qu'un mot de passe aléatoire affiché
     * une seule fois — la personne le change ensuite elle-même depuis son
     * profil (voir App\Livewire\Account\ChangePassword).
     */
    public const DEFAULT_PASSWORD = 'azerty';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'client_updated_at' => 'datetime',
        ];
    }

    protected static function uidPrefix(): string
    {
        return '220';
    }

    public function establishments(): BelongsToMany
    {
        return $this->belongsToMany(Establishment::class, 'establishment_user')
            ->using(EstablishmentUserPivot::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function belongsToEstablishment(int $establishmentId): bool
    {
        return $this->establishments()
            ->wherePivot('is_active', true)
            ->where('establishments.id', $establishmentId)
            ->exists();
    }

    /**
     * @return BelongsToMany<Foundation, $this, FoundationUserPivot, 'pivot'>
     */
    public function foundations(): BelongsToMany
    {
        return $this->belongsToMany(Foundation::class, 'foundation_user')
            ->using(FoundationUserPivot::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function isFounderOf(int $foundationId): bool
    {
        return $this->foundations()
            ->wherePivot('is_active', true)
            ->where('foundations.id', $foundationId)
            ->exists();
    }

    public function isFounderOfEstablishment(int $establishmentId): bool
    {
        $foundationId = Establishment::whereKey($establishmentId)->value('foundation_id');

        return $foundationId !== null && $this->isFounderOf((int) $foundationId);
    }

    /**
     * Accès direct (établissement_user) OU via le groupe (fondateur) — voir
     * Foundation pour le raisonnement : le fondateur n'a pas besoin d'une
     * ligne establishment_user par école de son groupe.
     */
    public function hasAccessTo(int $establishmentId): bool
    {
        return $this->belongsToEstablishment($establishmentId) || $this->isFounderOfEstablishment($establishmentId);
    }

    public function hasAdminRightsOn(int $establishmentId): bool
    {
        return in_array($this->roleFor($establishmentId), ['directeur', 'gestionnaire'], true)
            || $this->isFounderOfEstablishment($establishmentId);
    }

    public function hasAdminRightsOnCurrentEstablishment(): bool
    {
        if (! app()->bound('currentEstablishmentId')) {
            return false;
        }

        return $this->hasAdminRightsOn((int) app('currentEstablishmentId'));
    }

    /**
     * Tous les établissements accessibles pour le switcher : ceux où
     * l'utilisateur a une ligne establishment_user active, plus ceux de
     * chaque foundation dont il est fondateur actif.
     *
     * @return Collection<int, Establishment>
     */
    public function accessibleEstablishments(): Collection
    {
        $direct = $this->establishments()->wherePivot('is_active', true)->get();

        $foundationIds = $this->foundations()->wherePivot('is_active', true)->pluck('foundations.id');

        $viaFoundations = $foundationIds->isEmpty()
            ? collect()
            : Establishment::whereIn('foundation_id', $foundationIds)->get();

        return $direct->concat($viaFoundations)->unique('id')->values();
    }

    public function roleFor(int $establishmentId): ?string
    {
        $directRole = EstablishmentUserPivot::query()
            ->where('user_id', $this->id)
            ->where('establishment_id', $establishmentId)
            ->where('is_active', true)
            ->value('role');

        if ($directRole !== null) {
            return $directRole;
        }

        return $this->isFounderOfEstablishment($establishmentId) ? 'fondateur' : null;
    }

    public function currentRole(): ?string
    {
        if (! app()->bound('currentEstablishmentId')) {
            return null;
        }

        return $this->roleFor((int) app('currentEstablishmentId'));
    }

    public function currentRoleLabel(): string
    {
        return match ($this->currentRole()) {
            'directeur' => 'Directeur',
            'gestionnaire' => 'Gestionnaire',
            'enseignant' => 'Enseignant',
            'caissier' => 'Caissier',
            'educateur' => 'Éducateur',
            'parent' => 'Parent',
            'fondateur' => 'Fondateur',
            default => 'Aucun rôle',
        };
    }

    /**
     * GENERAL_ADMIN : un seul titulaire par fondation, ou par établissement
     * indépendant s'il n'appartient à aucune fondation — jamais les deux à
     * la fois pour un même établissement (voir migration
     * add_admin_power_flags_to_pivots, contraintes uniques composites).
     */
    public function isGeneralAdminOf(Foundation|Establishment $org): bool
    {
        if ($org instanceof Establishment && $org->foundation_id !== null) {
            return $this->isGeneralAdminOf($org->foundation()->firstOrFail());
        }

        $pivot = $org instanceof Foundation
            ? FoundationUserPivot::where('foundation_id', $org->id)
            : EstablishmentUserPivot::where('establishment_id', $org->id);

        return $pivot->where('user_id', $this->id)->where('is_general_admin', true)->exists();
    }

    /**
     * Utilisé pour décider si le lien nav "Mon organisation" doit être
     * affiché — un fondateur peut y accéder même sans pouvoir actuel, pour
     * réclamer le GENERAL_ADMIN (voir Staff\ManageOrganization::mount()).
     */
    public function isFondateurSomewhere(): bool
    {
        return FoundationUserPivot::where('user_id', $this->id)
            ->where('role', 'fondateur')
            ->where('is_active', true)
            ->exists()
            || EstablishmentUserPivot::where('user_id', $this->id)
                ->where('role', 'fondateur')
                ->where('is_active', true)
                ->exists();
    }

    /**
     * LOCAL_ADMIN : un seul titulaire par établissement. Un GENERAL_ADMIN
     * qui délègue son is_local_admin à quelqu'un d'autre ne doit jamais
     * perdre l'accès aux actions LOCAL_ADMIN de son propre périmètre — d'où
     * l'inclusion de isGeneralAdminOf() ici plutôt que de recomposer les
     * deux vérifications à chaque point d'appel (Policies, nav, mount()).
     */
    public function isLocalAdminOf(Establishment $establishment): bool
    {
        return EstablishmentUserPivot::where('establishment_id', $establishment->id)
            ->where('user_id', $this->id)
            ->where('is_local_admin', true)
            ->exists()
            || $this->isGeneralAdminOf($establishment);
    }

    public function isLocalAdminOfCurrentEstablishment(): bool
    {
        if (! app()->bound('currentEstablishmentId')) {
            return false;
        }

        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return $establishment !== null && $this->isLocalAdminOf($establishment);
    }

    public function saasAdmin(): HasOne
    {
        return $this->hasOne(SaasAdmin::class);
    }

    public function isSaasAdmin(): bool
    {
        return $this->saasAdmin()->where('is_active', true)->exists();
    }

    public function isMainSaasAdmin(): bool
    {
        return $this->saasAdmin()->where('is_active', true)->where('type', SaasAdminType::Main)->exists();
    }

    /**
     * Vérifie que l'enseignant est bien affecté à cette classe (et,
     * optionnellement, à cette matière précise) — cf. plan d'architecture,
     * section 2.2 : une Policy ne doit pas vérifier que le rôle "teacher",
     * mais aussi l'appartenance métier réelle (classe/matière assignée).
     */
    public function isAssignedToClassroom(int $classroomId, ?int $subjectId = null): bool
    {
        return TeacherAssignment::query()
            ->where('user_id', $this->id)
            ->where('classroom_id', $classroomId)
            ->when($subjectId !== null, fn ($query) => $query->where('subject_id', $subjectId))
            ->exists();
    }

    /**
     * @return HasOne<Guardian, $this>
     */
    public function guardianProfile(): HasOne
    {
        return $this->hasOne(Guardian::class, 'user_id');
    }
}
