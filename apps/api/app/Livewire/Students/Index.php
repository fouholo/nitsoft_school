<?php

declare(strict_types=1);

namespace App\Livewire\Students;

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\GuardianRelationship;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Nationalite;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Élèves')]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    /**
     * Champs du formulaire de création/modification, regroupés par étape du
     * flux progressif (voir critique /impeccable — un formulaire à plat de
     * 17 champs générait une charge cognitive trop élevée).
     *
     * @var array<int, array<int, string>>
     */
    private const STEP_FIELDS = [
        1 => ['last_name', 'first_name', 'birth_date', 'birth_place', 'gender', 'student_number', 'photo'],
        2 => ['nationalite_code', 'birth_certificate_number', 'birth_certificate_date', 'birth_certificate_place', 'residence'],
        3 => ['father_name', 'father_phone', 'mother_name', 'mother_phone', 'tutor_name', 'tutor_phone'],
    ];

    public bool $showForm = false;

    public ?int $editingId = null;

    public int $currentStep = 1;

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

    /**
     * Relation ('father'|'mother'|'tutor') désignée comme contact principal
     * — un seul choix possible parmi les 3 lignes de contacts familiaux.
     */
    public string $primaryContact = '';

    public bool $createAccountForFather = false;

    public bool $createAccountForMother = false;

    public bool $createAccountForTutor = false;

    public string $fatherEmail = '';

    public string $motherEmail = '';

    public string $tutorEmail = '';

    /**
     * Comptes portail créés lors du dernier enregistrement — affichés une
     * fois pour que l'administrateur puisse copier les mots de passe
     * générés avant qu'ils ne disparaissent.
     *
     * @var list<array{name: string, email: string, password: string}>
     */
    public array $generatedAccounts = [];

    public bool $passwordsAcknowledged = false;

    public string $birth_place = '';

    public string $nationalite_code = '';

    public string $birth_certificate_number = '';

    public string $birth_certificate_date = '';

    public string $birth_certificate_place = '';

    public string $residence = '';

    public ?TemporaryUploadedFile $photo = null;

    public string $existingPhotoPath = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Student::class);

        if ($stored = session('student_guardian_generated_accounts')) {
            $this->generatedAccounts = $stored;
        }
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
        $this->currentStep = 1;
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
        $this->existingPhotoPath = (string) $student->photo_path;
        $this->showForm = true;
    }

    public function nextStep(): void
    {
        $fields = self::STEP_FIELDS[$this->currentStep] ?? [];

        $this->validate(array_intersect_key($this->rules(), array_flip($fields)), [], $this->validationAttributes());

        if ($this->currentStep < count(self::STEP_FIELDS)) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function save(): void
    {
        $data = $this->validate($this->rules(), [], $this->validationAttributes());

        // Les champs optionnels vides arrivent comme '' (pas null) depuis les
        // inputs HTML ; il faut normaliser avant insertion (date/enum SQL stricts).
        $data['birth_date'] = $data['birth_date'] !== '' ? $data['birth_date'] : null;
        $data['gender'] = $data['gender'] !== '' ? $data['gender'] : null;
        $data['birth_certificate_date'] = $data['birth_certificate_date'] !== '' ? $data['birth_certificate_date'] : null;
        foreach (['father_name', 'father_phone', 'mother_name', 'mother_phone', 'tutor_name', 'tutor_phone', 'birth_place', 'nationalite_code', 'birth_certificate_number', 'birth_certificate_place', 'residence'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        unset($data['photo']);

        if ($this->editingId) {
            $student = Student::findOrFail($this->editingId);
            $this->authorize('update', $student);
        } else {
            $this->authorize('create', Student::class);
            $student = new Student;
        }

        if ($this->photo) {
            if ($student->photo_path) {
                Storage::disk('public')->delete($student->photo_path);
            }

            $data['photo_path'] = $this->photo->store('students-photos', 'public');
        }

        $student->fill($data);
        $student->save();

        $accounts = [];

        foreach ([
            'father' => ['create' => $this->createAccountForFather, 'name' => $data['father_name'], 'phone' => $data['father_phone'], 'email' => $this->fatherEmail, 'relationship' => GuardianRelationship::Pere],
            'mother' => ['create' => $this->createAccountForMother, 'name' => $data['mother_name'], 'phone' => $data['mother_phone'], 'email' => $this->motherEmail, 'relationship' => GuardianRelationship::Mere],
            'tutor' => ['create' => $this->createAccountForTutor, 'name' => $data['tutor_name'], 'phone' => $data['tutor_phone'], 'email' => $this->tutorEmail, 'relationship' => GuardianRelationship::Tuteur],
        ] as $key => $row) {
            if (! $row['create']) {
                continue;
            }

            $account = $this->linkGuardianAccount(
                $student,
                (string) $row['name'],
                $row['phone'],
                $row['email'],
                $row['relationship'],
                $this->primaryContact === $key,
            );

            if ($account !== null) {
                $accounts[] = $account;
            }
        }

        if ($accounts !== []) {
            $this->generatedAccounts = $accounts;
            $this->passwordsAcknowledged = false;
            session(['student_guardian_generated_accounts' => $accounts]);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    /**
     * Trouve ou crée le Guardian correspondant à cet e-mail, crée son compte
     * portail s'il n'en a pas encore, le lie à l'élève (tuteur approuvé,
     * rôle et contact principal éventuel), et le rattache à l'établissement
     * courant avec le rôle "parent". Retourne les identifiants générés
     * uniquement quand un nouveau compte a été créé (pour affichage unique).
     *
     * @return array{name: string, email: string, password: string}|null
     */
    protected function linkGuardianAccount(
        Student $student,
        string $name,
        ?string $phone,
        string $email,
        GuardianRelationship $relationship,
        bool $isPrimary,
    ): ?array {
        $guardian = Guardian::where('email', $email)->first();
        $generated = null;

        if ($guardian === null) {
            $parts = preg_split('/\s+/', trim($name), 2) ?: [''];
            $firstName = $parts[0] !== '' ? $parts[0] : $name;
            $lastName = $parts[1] ?? $firstName;

            $guardian = Guardian::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'email' => $email,
            ]);
        }

        if (! $guardian->user_id) {
            $password = Str::password(12);
            $fullName = trim("{$guardian->first_name} {$guardian->last_name}");

            $user = User::create([
                'name' => $fullName,
                'email' => $guardian->email,
                'phone' => $guardian->phone,
                'password' => $password,
            ]);

            $guardian->update(['user_id' => $user->id]);

            $generated = ['name' => $fullName, 'email' => $guardian->email, 'password' => $password];
        }

        /** @var Establishment $establishment */
        $establishment = Establishment::findOrFail((int) app('currentEstablishmentId'));

        if (! $establishment->users()->where('users.id', $guardian->user_id)->exists()) {
            $establishment->users()->attach($guardian->user_id, ['role' => 'parent', 'is_active' => true]);
        }

        if ($isPrimary) {
            DB::table('guardian_student')->where('student_id', $student->id)->update(['is_primary_contact' => false]);
        }

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'establishment_id' => (int) app('currentEstablishmentId'),
                'status' => GuardianLinkStatus::Approved,
                'relationship' => $relationship,
                'is_primary_contact' => $isPrimary,
            ],
        ]);

        return $generated;
    }

    public function dismissGeneratedAccounts(): void
    {
        $this->generatedAccounts = [];
        $this->passwordsAcknowledged = false;

        session()->forget('student_guardian_generated_accounts');
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

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $establishmentId = (int) app('currentEstablishmentId');

        $uniqueStudentNumber = Rule::unique('students', 'student_number')
            ->where('establishment_id', $establishmentId)
            ->ignore($this->editingId);

        return [
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:m,f'],
            'student_number' => ['required', 'string', 'max:255', $uniqueStudentNumber],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:100'],
            'nationalite_code' => ['nullable', 'string', 'exists:nationalites,code'],
            'birth_certificate_number' => ['nullable', 'string', 'max:255'],
            'birth_certificate_date' => ['nullable', 'date'],
            'birth_certificate_place' => ['nullable', 'string', 'max:255'],
            'residence' => ['nullable', 'string', 'max:255'],
            'father_name' => ['required_if:createAccountForFather,true', 'nullable', 'string', 'max:255'],
            'father_phone' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['required_if:createAccountForMother,true', 'nullable', 'string', 'max:255'],
            'mother_phone' => ['nullable', 'string', 'max:255'],
            'tutor_name' => ['required_if:createAccountForTutor,true', 'nullable', 'string', 'max:255'],
            'tutor_phone' => ['nullable', 'string', 'max:255'],
            'primaryContact' => ['nullable', Rule::in(['father', 'mother', 'tutor'])],
            'fatherEmail' => ['nullable', 'email', 'max:255', 'required_if:createAccountForFather,true'],
            'motherEmail' => ['nullable', 'email', 'max:255', 'required_if:createAccountForMother,true'],
            'tutorEmail' => ['nullable', 'email', 'max:255', 'required_if:createAccountForTutor,true'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'last_name' => 'nom',
            'first_name' => 'prénom',
            'birth_date' => 'date de naissance',
            'birth_place' => 'lieu de naissance',
            'gender' => 'genre',
            'student_number' => 'matricule',
            'photo' => 'photo',
            'nationalite_code' => 'nationalité',
            'birth_certificate_number' => 'numéro d\'acte de naissance',
            'birth_certificate_date' => 'date de l\'acte de naissance',
            'birth_certificate_place' => 'lieu de l\'acte de naissance',
            'residence' => 'résidence',
            'father_name' => 'nom du père',
            'father_phone' => 'téléphone du père',
            'mother_name' => 'nom de la mère',
            'mother_phone' => 'téléphone de la mère',
            'tutor_name' => 'nom du tuteur',
            'tutor_phone' => 'téléphone du tuteur',
            'fatherEmail' => 'e-mail du père',
            'motherEmail' => 'e-mail de la mère',
            'tutorEmail' => 'e-mail du tuteur',
        ];
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'currentStep', 'first_name', 'last_name', 'birth_date', 'gender', 'student_number',
            'father_name', 'father_phone', 'mother_name', 'mother_phone', 'tutor_name', 'tutor_phone',
            'primaryContact', 'createAccountForFather', 'createAccountForMother', 'createAccountForTutor',
            'fatherEmail', 'motherEmail', 'tutorEmail',
            'birth_place', 'nationalite_code', 'birth_certificate_number', 'birth_certificate_date',
            'birth_certificate_place', 'residence', 'photo', 'existingPhotoPath',
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
