<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Enums\Track;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Enrollment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'student_id',
        'classroom_id',
        'arabic_classroom_id',
        'main_track',
        'school_year_id',
        'enrolled_on',
        'status',
        'is_repeating',
        'is_arabic_repeating',
        'is_scholarship',
        'is_boarding',
        'is_assigned',
        'registration_amount',
        'installment_1_amount',
        'installment_2_amount',
        'installment_3_amount',
        'installment_4_amount',
        'installment_5_amount',
        'installment_6_amount',
        'installment_7_amount',
        'total_paid',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'enrolled_on' => 'date',
        'main_track' => Track::class,
        'is_repeating' => 'boolean',
        'is_arabic_repeating' => 'boolean',
        'is_scholarship' => 'boolean',
        'is_boarding' => 'boolean',
        'is_assigned' => 'boolean',
        'registration_amount' => 'decimal:2',
        'installment_1_amount' => 'decimal:2',
        'installment_2_amount' => 'decimal:2',
        'installment_3_amount' => 'decimal:2',
        'installment_4_amount' => 'decimal:2',
        'installment_5_amount' => 'decimal:2',
        'installment_6_amount' => 'decimal:2',
        'installment_7_amount' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '230';
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<ArabicClassroom, $this>
     */
    public function arabicClassroom(): BelongsTo
    {
        return $this->belongsTo(ArabicClassroom::class);
    }

    /**
     * Classe de la filière principale (française ou arabe) : celle qui
     * détermine les frais et qui est affichée comme classe de l'élève —
     * voir docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
     * Toujours passer par ici plutôt que par $enrollment->classroom pour
     * afficher la classe d'un élève : la classe française peut être absente.
     */
    public function mainClassroom(): Classroom|ArabicClassroom|null
    {
        return $this->classroomFor($this->mainTrack());
    }

    /**
     * Classe de l'autre filière (école franco-arabe) ; null tant qu'elle
     * n'est pas renseignée.
     */
    public function secondaryClassroom(): Classroom|ArabicClassroom|null
    {
        return $this->classroomFor($this->mainTrack()->other());
    }

    public function mainClassroomName(): string
    {
        return $this->mainClassroom()->name ?? '—';
    }

    public function mainTrack(): Track
    {
        return $this->main_track ?? Track::Francais;
    }

    private function classroomFor(Track $track): Classroom|ArabicClassroom|null
    {
        return $track === Track::Arabe ? $this->arabicClassroom : $this->classroom;
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Montants de tranche (positions 1 à 7) non nuls, indexés par position.
     *
     * @return array<int, float>
     */
    public function installmentAmountsByPosition(): array
    {
        $amounts = [];

        foreach (range(1, 7) as $position) {
            $amount = $this->{"installment_{$position}_amount"};

            if ($amount !== null) {
                $amounts[$position] = (float) $amount;
            }
        }

        return $amounts;
    }

    /**
     * Tranches de scolarité de cette inscription (montant + échéance),
     * triées par échéance croissante — combine les 7 colonnes
     * installment_N_amount avec les Installment (échéance) configurés pour
     * l'année scolaire de l'inscription.
     *
     * @return Collection<int, array{position: int<0, max>, amount: float, due_date: Carbon}>
     */
    public function tuitionInstallments(): Collection
    {
        $amountsByPosition = $this->installmentAmountsByPosition();

        if ($amountsByPosition === []) {
            return collect();
        }

        // Scopage explicite par establishment_id (plutôt que le global
        // scope implicite de TenantScoped) : nécessaire pour rester correct
        // quand cette inscription est chargée hors du tenant courant, comme
        // le fait DashboardAlertsService pour un fondateur multi-écoles.
        return Installment::withoutTenant()
            ->where('establishment_id', $this->establishment_id)
            ->where('school_year_id', $this->school_year_id)
            ->whereIn('position', array_keys($amountsByPosition))
            ->orderBy('due_date')
            ->get()
            ->map(fn (Installment $installment): array => [
                'position' => $installment->position,
                'amount' => $amountsByPosition[$installment->position],
                'due_date' => $installment->due_date,
            ]);
    }

    /**
     * @return Collection<int, array{position: int<0, max>, amount: float, due_date: Carbon}>
     */
    public function tuitionInstallmentsDue(): Collection
    {
        return $this->tuitionInstallments()->filter(fn (array $installment): bool => $installment['due_date']->lte(now()));
    }

    /**
     * Tranches de scolarité avec leur statut de couverture et le montant
     * versé, déduits par imputation cumulative des versements (d'abord
     * l'inscription, puis les tranches dans l'ordre des échéances — même
     * convention que PaymentService::tuitionSnapshotFor()) :
     * - `paid` : entièrement couverte par les versements.
     * - `partial_late`/`partial_upcoming` : partiellement couverte, échéance
     *   passée ou à venir.
     * - `late`/`due` : pas couverte du tout, échéance passée ou à venir.
     *
     * @return Collection<int, array{position: int, amount: float, due_date: Carbon, status: string, paid: float}>
     */
    public function tuitionInstallmentsWithStatus(): Collection
    {
        $paidTowardTuition = max(0.0, (float) $this->total_paid - (float) ($this->registration_amount ?? 0));
        $cumulativeDue = 0.0;

        return $this->tuitionInstallments()->map(function (array $installment) use (&$cumulativeDue, $paidTowardTuition): array {
            $dueBefore = $cumulativeDue;
            $cumulativeDue += $installment['amount'];
            $isPast = $installment['due_date']->lte(now());
            $paid = min($installment['amount'], max(0.0, $paidTowardTuition - $dueBefore));

            $status = match (true) {
                $paidTowardTuition >= $cumulativeDue => 'paid',
                $paidTowardTuition > $dueBefore => $isPast ? 'partial_late' : 'partial_upcoming',
                $isPast => 'late',
                default => 'due',
            };

            return [...$installment, 'status' => $status, 'paid' => $paid];
        });
    }

    /**
     * Montant versé imputé aux frais d'inscription (couverts en premier,
     * avant toute tranche — voir tuitionInstallmentsWithStatus()).
     */
    public function registrationAmountPaid(): float
    {
        return min((float) ($this->registration_amount ?? 0), (float) $this->total_paid);
    }
}
