<?php

declare(strict_types=1);

namespace App\Livewire\Billing\Discounts;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $school_year_id = null;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $student_id = null;

    public string $studentSearch = '';

    public string $editingStudentLabel = '';

    public string $type = 'percentage';

    public ?float $value = null;

    public string $registration_type = 'percentage';

    public ?float $registration_value = null;

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Discount::class);

        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
    }

    public function create(): void
    {
        $this->authorize('create', Discount::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $discountId): void
    {
        $discount = Discount::findOrFail($discountId);

        $this->authorize('update', $discount);

        $this->editingId = $discount->id;
        $this->student_id = $discount->student_id;
        $this->editingStudentLabel = trim($discount->student->last_name.' '.$discount->student->first_name);
        $this->type = $discount->type ?? 'percentage';
        $this->value = $discount->hasTuitionDiscount() ? (float) $discount->value : null;
        $this->registration_type = $discount->registration_type ?? 'percentage';
        $this->registration_value = $discount->hasRegistrationDiscount() ? (float) $discount->registration_value : null;
        $this->reason = (string) $discount->reason;
        $this->showForm = true;
    }

    public function save(DiscountService $discountService): void
    {
        $data = $this->validate([
            'student_id' => ['required', 'exists:students,id'],
            'type' => ['required', 'in:percentage,fixed_amount'],
            'value' => [
                'required_without:registration_value',
                'nullable',
                'numeric',
                'min:0',
                $this->maxPercentageRule($this->type),
            ],
            'registration_type' => ['required', 'in:percentage,fixed_amount'],
            'registration_value' => [
                'nullable',
                'numeric',
                'min:0',
                $this->maxPercentageRule($this->registration_type),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'value.required_without' => __("Renseignez une réduction sur la scolarité ou sur les frais d'inscription."),
        ]);

        $existing = Discount::where('school_year_id', $this->school_year_id)
            ->where('student_id', $data['student_id'])
            ->first();

        $this->authorize($existing ? 'update' : 'create', $existing ?? Discount::class);

        $hadTuitionDiscount = (bool) $existing?->hasTuitionDiscount();
        $hadRegistrationDiscount = (bool) $existing?->hasRegistrationDiscount();

        // Une réduction supprimée en douceur occupe encore la contrainte
        // unique (élève, année) : on la réactive au lieu d'en créer une autre.
        if (! $existing) {
            Discount::onlyTrashed()
                ->where('school_year_id', $this->school_year_id)
                ->where('student_id', $data['student_id'])
                ->first()
                ?->restore();
        }

        $discount = Discount::updateOrCreate(
            ['school_year_id' => $this->school_year_id, 'student_id' => $data['student_id']],
            [
                'type' => $data['value'] !== null ? $data['type'] : null,
                'value' => $data['value'],
                'registration_type' => $data['registration_value'] !== null ? $data['registration_type'] : null,
                'registration_value' => $data['registration_value'],
                'reason' => $data['reason'] !== '' ? $data['reason'] : null,
                'created_by' => $existing->created_by ?? Auth::id(),
            ],
        );

        $enrollment = Enrollment::where('school_year_id', $this->school_year_id)
            ->where('student_id', $data['student_id'])
            ->where('status', 'active')
            ->first();

        if ($enrollment) {
            $discountService->applyToEnrollment($enrollment, $discount, $hadTuitionDiscount, $hadRegistrationDiscount);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $discountId, DiscountService $discountService): void
    {
        $discount = Discount::findOrFail($discountId);

        $this->authorize('delete', $discount);

        $enrollment = Enrollment::where('school_year_id', $discount->school_year_id)
            ->where('student_id', $discount->student_id)
            ->where('status', 'active')
            ->first();

        $discount->delete();

        if ($enrollment) {
            $discountService->removeFromEnrollment($enrollment, $discount);
        }
    }

    private function maxPercentageRule(string $type): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($type): void {
            if ($type === 'percentage' && (float) $value > 100) {
                $fail(__('Le pourcentage ne peut pas dépasser 100.'));
            }
        };
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'student_id', 'studentSearch', 'editingStudentLabel', 'value', 'registration_value', 'reason']);
        $this->type = 'percentage';
        $this->registration_type = 'percentage';
    }

    /**
     * @return Collection<int, Student>
     */
    protected function studentOptions(): Collection
    {
        return Student::query()
            ->when($this->studentSearch !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('first_name', 'like', "%{$this->studentSearch}%")
                        ->orWhere('last_name', 'like', "%{$this->studentSearch}%");
                });
            })
            ->with(['enrollments' => fn ($query) => $query
                ->where('school_year_id', $this->school_year_id)
                ->where('status', 'active')
                ->with(['classroom', 'arabicClassroom'])])
            ->orderBy('last_name')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        $discounts = Discount::where('school_year_id', $this->school_year_id)
            ->with(['student', 'createdBy'])
            ->when($this->search !== '', function ($query): void {
                $query->whereHas('student', function ($query): void {
                    $query->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%");
                });
            })
            ->get();

        return view('livewire.billing.discounts.index', [
            'discounts' => $discounts,
            'students' => $this->studentOptions(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
        ])->title(__('Réductions'));
    }
}
