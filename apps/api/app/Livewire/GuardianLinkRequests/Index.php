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
    public function mount(): void
    {
        $this->authorize('viewAny', GuardianStudentPivot::class);
    }

    public function approve(int $linkId): void
    {
        $link = GuardianStudentPivot::findOrFail($linkId);

        $this->authorize('update', $link);

        abort_if($link->guardian->user_id === null, 422, "Ce tuteur n'a pas de compte utilisateur.");

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
    }

    public function reject(int $linkId): void
    {
        $link = GuardianStudentPivot::findOrFail($linkId);

        $this->authorize('update', $link);

        $link->update(['status' => GuardianLinkStatus::Rejected]);
    }

    public function render()
    {
        $pendingLinks = GuardianStudentPivot::query()
            ->where('establishment_id', app('currentEstablishmentId'))
            ->where('status', GuardianLinkStatus::Pending)
            ->with(['guardian', 'student'])
            ->latest()
            ->get();

        $roleAlreadyFilled = GuardianStudentPivot::query()
            ->where('establishment_id', app('currentEstablishmentId'))
            ->where('status', GuardianLinkStatus::Approved)
            ->get()
            ->groupBy(fn (GuardianStudentPivot $link) => "{$link->student_id}:{$link->relationship?->value}")
            ->keys();

        return view('livewire.guardian-link-requests.index', [
            'pendingLinks' => $pendingLinks,
            'roleAlreadyFilled' => $roleAlreadyFilled,
        ]);
    }
}
