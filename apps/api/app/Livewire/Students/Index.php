<?php

declare(strict_types=1);

namespace App\Livewire\Students;

use App\Domain\Enrollment\Models\Nationalite;
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

    public string $father_name = '';

    public string $father_phone = '';

    public string $mother_name = '';

    public string $mother_phone = '';

    public string $tutor_name = '';

    public string $tutor_phone = '';

    public string $birth_place = '';

    public string $nationalite_code = '';

    public string $birth_certificate_number = '';

    public string $birth_certificate_date = '';

    public string $birth_certificate_place = '';

    public string $residence = '';

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
        $this->father_name = (string) $student->father_name;
        $this->father_phone = (string) $student->father_phone;
        $this->mother_name = (string) $student->mother_name;
        $this->mother_phone = (string) $student->mother_phone;
        $this->tutor_name = (string) $student->tutor_name;
        $this->tutor_phone = (string) $student->tutor_phone;
        $this->birth_place = (string) $student->birth_place;
        $this->nationalite_code = (string) $student->nationalite_code;
        $this->birth_certificate_number = (string) $student->birth_certificate_number;
        $this->birth_certificate_date = $student->birth_certificate_date?->toDateString() ?? '';
        $this->birth_certificate_place = (string) $student->birth_certificate_place;
        $this->residence = (string) $student->residence;
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
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_phone' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_phone' => ['nullable', 'string', 'max:255'],
            'tutor_name' => ['nullable', 'string', 'max:255'],
            'tutor_phone' => ['nullable', 'string', 'max:255'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'nationalite_code' => ['nullable', 'string', 'exists:nationalites,code'],
            'birth_certificate_number' => ['nullable', 'string', 'max:255'],
            'birth_certificate_date' => ['nullable', 'date'],
            'birth_certificate_place' => ['nullable', 'string', 'max:255'],
            'residence' => ['nullable', 'string', 'max:255'],
        ]);

        // Les champs optionnels vides arrivent comme '' (pas null) depuis les
        // inputs HTML ; il faut normaliser avant insertion (date/enum SQL stricts).
        $data['birth_date'] = $data['birth_date'] !== '' ? $data['birth_date'] : null;
        $data['gender'] = $data['gender'] !== '' ? $data['gender'] : null;
        $data['birth_certificate_date'] = $data['birth_certificate_date'] !== '' ? $data['birth_certificate_date'] : null;
        foreach (['father_name', 'father_phone', 'mother_name', 'mother_phone', 'tutor_name', 'tutor_phone', 'birth_place', 'nationalite_code', 'birth_certificate_number', 'birth_certificate_place', 'residence'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

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
        $this->reset([
            'editingId', 'first_name', 'last_name', 'birth_date', 'gender', 'student_number',
            'father_name', 'father_phone', 'mother_name', 'mother_phone', 'tutor_name', 'tutor_phone',
            'birth_place', 'nationalite_code', 'birth_certificate_number', 'birth_certificate_date',
            'birth_certificate_place', 'residence',
        ]);
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
            'nationalites' => Nationalite::orderBy('libelle')->get(),
        ]);
    }
}
