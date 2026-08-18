<?php

declare(strict_types=1);

namespace App\Livewire\Billing\PaymentTracking;

use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Suivi des paiements')]
class Index extends Component
{
    public ?int $school_year_id = null;

    public ?int $levelFilter = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Payment::class);

        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        $ownerId = RolePermissions::can($user->currentRole(), 'finance.scope_own_only') ? $user->id : null;

        $balances = $this->school_year_id
            ? app(PaymentTrackingService::class)->balances($this->school_year_id, $ownerId)
            : collect();

        if ($this->levelFilter) {
            $studentIdsInLevel = Enrollment::where('school_year_id', $this->school_year_id)
                ->where('status', 'active')
                ->whereHas('classroom', fn ($query) => $query->where('level_id', $this->levelFilter))
                ->pluck('student_id');

            $balances = $balances->whereIn('student_id', $studentIdsInLevel);
        }

        $students = Student::whereIn('id', $balances->pluck('student_id'))
            ->with(['enrollments' => fn ($query) => $query
                ->where('school_year_id', $this->school_year_id)
                ->where('status', 'active')
                ->with('classroom')])
            ->get()
            ->keyBy('id');

        $rows = $balances
            ->map(function (array $balance) use ($students) {
                $student = $students->get($balance['student_id']);

                return [
                    ...$balance,
                    'student' => $student,
                    'classroom' => $student?->enrollments->first()?->classroom,
                ];
            })
            ->filter(fn (array $row) => $row['student'] !== null)
            ->sortByDesc('balance')
            ->values();

        return view('livewire.billing.payment-tracking.index', [
            'rows' => $rows,
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'levels' => Level::orderBy('level_wording')->get(),
        ]);
    }
}
