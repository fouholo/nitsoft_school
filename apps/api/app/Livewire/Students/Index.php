<?php

declare(strict_types=1);

namespace App\Livewire\Students;

use App\Domain\Enrollment\Models\Student;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Élèves')]
class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $birth_date = '';

    public string $gender = '';

    public string $student_number = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Student::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Student::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $studentId): void
    {
        $student = Student::findOrFail($studentId);

        $this->authorize('update', $student);

        $this->editingId = $student->id;
        $this->first_name = $student->first_name;
        $this->last_name = $student->last_name;
        $this->birth_date = $student->birth_date?->toDateString() ?? '';
        $this->gender = (string) $student->gender;
        $this->student_number = $student->student_number;
        $this->showForm = true;
    }

    public function save(): void
    {
        $establishmentId = (int) app('currentEstablishmentId');

        $uniqueStudentNumber = Rule::unique('students', 'student_number')
            ->where('establishment_id', $establishmentId)
            ->ignore($this->editingId);

        $data = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:m,f'],
            'student_number' => ['required', 'string', 'max:255', $uniqueStudentNumber],
        ]);

        // Les champs optionnels vides arrivent comme '' (pas null) depuis les
        // inputs HTML ; il faut normaliser avant insertion (date/enum SQL stricts).
        $data['birth_date'] = $data['birth_date'] !== '' ? $data['birth_date'] : null;
        $data['gender'] = $data['gender'] !== '' ? $data['gender'] : null;

        if ($this->editingId) {
            $student = Student::findOrFail($this->editingId);
            $this->authorize('update', $student);
        } else {
            $this->authorize('create', Student::class);
            $student = new Student;
        }

        $student->fill($data);
        $student->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $studentId): void
    {
        $student = Student::findOrFail($studentId);

        $this->authorize('delete', $student);

        $student->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'first_name', 'last_name', 'birth_date', 'gender', 'student_number']);
    }

    public function render()
    {
        $students = Student::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('student_number', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('last_name')
            ->paginate(15);

        return view('livewire.students.index', [
            'students' => $students,
        ]);
    }
}
