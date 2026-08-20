<?php

declare(strict_types=1);

namespace App\Livewire\GuardianLinkRequests;

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\GuardianStudentPivot;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Demandes de liaison')]
class Index extends Component
{
    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', GuardianStudentPivot::class);
    }

    public function approve(int $linkId): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        $link = GuardianStudentPivot::findOrFail($linkId);

        $this->authorize('update', $link);

        if ($link->guardian->user_id === null) {
            $this->errorMessage = "Impossible d'approuver : {$link->guardian->first_name} {$link->guardian->last_name} n'a pas de compte utilisateur. Contactez le support pour résoudre ce blocage.";

            return;
        }

        DB::transaction(function () use ($link): void {
            GuardianStudentPivot::query()
                ->where('student_id', $link->student_id)
                ->where('establishment_id', $link->establishment_id)
                ->where('relationship', $link->relationship)
                ->where('status', GuardianLinkStatus::Approved)
                ->where('id', '!=', $link->id)
                ->update(['status' => GuardianLinkStatus::Rejected]);

            $link->update(['status' => GuardianLinkStatus::Approved]);

            EstablishmentUserPivot::updateOrCreate(
                [
                    'establishment_id' => $link->establishment_id,
                    'user_id' => $link->guardian->user_id,
                    'role' => 'parent',
                ],
                ['is_active' => true],
            );
        });

        $this->successMessage = "Demande approuvée pour {$link->guardian->first_name} {$link->guardian->last_name} ({$link->student->first_name} {$link->student->last_name}).";
    }

    public function reject(int $linkId): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        $link = GuardianStudentPivot::findOrFail($linkId);

        $this->authorize('update', $link);

        $link->update(['status' => GuardianLinkStatus::Rejected]);

        $this->successMessage = "Demande rejetée pour {$link->guardian->first_name} {$link->guardian->last_name}.";
    }

    public function reconsider(int $linkId): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        $link = GuardianStudentPivot::findOrFail($linkId);

        $this->authorize('update', $link);

        $link->update(['status' => GuardianLinkStatus::Pending]);

        $this->successMessage = "Demande remise en attente pour {$link->guardian->first_name} {$link->guardian->last_name}.";
    }

    /**
     * @return array{name: ?string, phone: ?string}
     */
    protected function referenceFor(GuardianStudentPivot $link): array
    {
        return match ($link->relationship?->value) {
            'pere' => ['name' => $link->student->father_name, 'phone' => $link->student->father_phone],
            'mere' => ['name' => $link->student->mother_name, 'phone' => $link->student->mother_phone],
            'tuteur' => ['name' => $link->student->tutor_name, 'phone' => $link->student->tutor_phone],
            default => ['name' => null, 'phone' => null],
        };
    }

    /**
     * @param  array{name: ?string, phone: ?string}  $reference
     */
    protected function matchStatus(GuardianStudentPivot $link, array $reference): string
    {
        if (! $reference['name'] && ! $reference['phone']) {
            return 'missing';
        }

        $claimedPhone = preg_replace('/\D+/', '', (string) $link->guardian->phone) ?? '';
        $referencePhone = preg_replace('/\D+/', '', (string) $reference['phone']) ?? '';
        $phoneMatches = $claimedPhone !== '' && $claimedPhone === $referencePhone;

        $claimedName = mb_strtolower(trim("{$link->guardian->first_name} {$link->guardian->last_name}"));
        $refName = mb_strtolower(trim((string) $reference['name']));
        $nameMatches = $refName !== '' && ($claimedName === $refName || str_contains($claimedName, $refName) || str_contains($refName, $claimedName));

        return $phoneMatches || $nameMatches ? 'match' : 'mismatch';
    }

    public function render()
    {
        $pendingLinks = GuardianStudentPivot::query()
            ->where('establishment_id', app('currentEstablishmentId'))
            ->where('status', GuardianLinkStatus::Pending)
            ->with(['guardian', 'student'])
            ->latest()
            ->get();

        $references = $pendingLinks->mapWithKeys(function (GuardianStudentPivot $link): array {
            $reference = $this->referenceFor($link);

            return [$link->id => [
                'name' => $reference['name'],
                'phone' => $reference['phone'],
                'match' => $this->matchStatus($link, $reference),
            ]];
        });

        $roleAlreadyFilled = GuardianStudentPivot::query()
            ->where('establishment_id', app('currentEstablishmentId'))
            ->where('status', GuardianLinkStatus::Approved)
            ->get()
            ->groupBy(fn (GuardianStudentPivot $link) => "{$link->student_id}:{$link->relationship?->value}")
            ->keys();

        $recentlyRejected = GuardianStudentPivot::query()
            ->where('establishment_id', app('currentEstablishmentId'))
            ->where('status', GuardianLinkStatus::Rejected)
            ->with(['guardian', 'student'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return view('livewire.guardian-link-requests.index', [
            'pendingLinks' => $pendingLinks,
            'references' => $references,
            'roleAlreadyFilled' => $roleAlreadyFilled,
            'recentlyRejected' => $recentlyRejected,
        ]);
    }
}
