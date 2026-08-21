<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Term;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        if (! app()->bound('currentEstablishmentId')) {
            return view('livewire.dashboard', ['noEstablishment' => true]);
        }

        /** @var User $user */
        $user = Auth::user();
        $role = $user->currentRole();

        $currentEstablishment = Establishment::find((int) app('currentEstablishmentId'));
        $currentSchoolYear = SchoolYear::where('is_current', true)->first();
        $schoolYearId = $currentSchoolYear?->id;

        $currentTerm = null;

        if ($schoolYearId && $currentEstablishment?->isSecondaire()) {
            $currentTerm = Term::where('school_year_id', $schoolYearId)
                ->where('starts_on', '<=', now())
                ->where('ends_on', '>=', now())
                ->orderBy('sequence')
                ->first();

            $currentTerm ??= Term::where('school_year_id', $schoolYearId)->orderByDesc('sequence')->first();
        }

        $pendingBalances = $schoolYearId
            ? app(PaymentTrackingService::class)->balances($schoolYearId)->where('balance', '>', 0)
            : collect();

        $groupEstablishmentsCount = $user->founderGroupEstablishments()->count();

        return view('livewire.dashboard', [
            'noEstablishment' => false,
            'role' => $role,
            'roleLabel' => $user->currentRoleLabel(),
            'currentEstablishment' => $currentEstablishment,
            'currentSchoolYear' => $currentSchoolYear,
            'currentTerm' => $currentTerm,
            'studentsCount' => Student::where('is_active', true)->count(),
            'classroomsCount' => Classroom::whereHas('schoolYear', fn ($query) => $query->where('is_current', true))->count(),
            'pendingInvoicesCount' => $pendingBalances->count(),
            'pendingInvoicesBalance' => (float) $pendingBalances->sum('balance'),
            'staffCount' => EstablishmentUserPivot::query()
                ->where('establishment_id', app('currentEstablishmentId'))
                ->whereIn('role', ['directeur', 'gestionnaire', 'enseignant', 'caissier', 'educateur'])
                ->where('is_active', true)
                ->count(),
            'isMultiSchoolFounder' => $groupEstablishmentsCount >= 2,
            'groupEstablishmentsCount' => $groupEstablishmentsCount,
        ]);
    }
}
