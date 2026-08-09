<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Enrollment\Models\Student;
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
        /** @var User $user */
        $user = Auth::user();
        $role = $user->currentRole();

        return view('livewire.dashboard', [
            'role' => $role,
            'roleLabel' => $user->currentRoleLabel(),
            'studentsCount' => Student::where('is_active', true)->count(),
            'classroomsCount' => Classroom::whereHas('schoolYear', fn ($query) => $query->where('is_current', true))->count(),
            'pendingInvoicesCount' => Invoice::whereIn('status', ['pending', 'partially_paid'])->count(),
            'pendingInvoicesBalance' => (float) (Invoice::whereIn('status', ['pending', 'partially_paid'])
                ->selectRaw('SUM(amount_due - amount_paid) as balance')
                ->value('balance') ?? 0),
            'staffCount' => EstablishmentUserPivot::query()
                ->where('establishment_id', app('currentEstablishmentId'))
                ->whereIn('role', ['directeur', 'gestionnaire', 'enseignant', 'comptable'])
                ->where('is_active', true)
                ->count(),
        ]);
    }
}
