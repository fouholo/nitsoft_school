<?php

declare(strict_types=1);

namespace App\Livewire\Establishments;

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Exceptions\SchoolRegistrationConflictException;
use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\Inspection;
use App\Domain\Establishments\Models\SchoolRegistration;
use App\Domain\Establishments\Services\SchoolRegistrationApprover;
use App\Domain\Establishments\Support\UniqueSlug;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $foundation_id = null;

    public string $type = '';

    public string $address = '';

    public string $phone = '';

    public bool $is_active = true;

    public string $inspection_id = '';

    public string $direction_id = '';

    public string $opening_code = '';

    public string $dsps_code = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $email = '';

    public bool $is_arabe = false;

    public ?TemporaryUploadedFile $logo = null;

    public string $existingLogoPath = '';

    /** @var array<int, string> Erreur de validation par demande (id => message). */
    public array $registrationErrors = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Establishment::class);
    }

    public function create(): void
    {
        $this->authorize('create', Establishment::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $establishmentId): void
    {
        $establishment = Establishment::findOrFail($establishmentId);

        $this->authorize('update', $establishment);

        $this->editingId = $establishment->id;
        $this->name = $establishment->name;
        $this->foundation_id = $establishment->foundation_id;
        $this->type = $establishment->type instanceof EstablishmentType ? $establishment->type->value : '';
        $this->address = (string) $establishment->address;
        $this->phone = (string) $establishment->phone;
        $this->is_active = $establishment->is_active;
        $this->inspection_id = (string) $establishment->inspection_id;
        $this->direction_id = (string) $establishment->direction_id;
        $this->opening_code = (string) $establishment->opening_code;
        $this->dsps_code = (string) $establishment->dsps_code;
        $this->latitude = (string) $establishment->latitude;
        $this->longitude = (string) $establishment->longitude;
        $this->email = (string) $establishment->email;
        $this->is_arabe = $establishment->is_arabe;
        $this->existingLogoPath = (string) $establishment->logo_path;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'foundation_id' => ['nullable', 'integer', 'exists:foundations,id'],
            'type' => ['required', Rule::enum(EstablishmentType::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'inspection_id' => [
                Rule::requiredIf($this->type === EstablishmentType::PrescolairePrimaire->value),
                'nullable', 'integer', 'exists:inspections,id',
            ],
            'direction_id' => [
                Rule::requiredIf($this->type === EstablishmentType::Secondaire->value),
                'nullable', 'integer', 'exists:directions,id',
            ],
            'opening_code' => ['nullable', 'string', 'max:100'],
            'dsps_code' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_arabe' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
        ]);

        foreach (['inspection_id', 'direction_id', 'opening_code', 'dsps_code', 'latitude', 'longitude', 'email'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        // Inspection (primaire) et direction (secondaire) sont exclusives —
        // un changement de type efface le lien devenu non pertinent, même si
        // l'ancienne valeur était encore présente dans le formulaire.
        if ($data['type'] === EstablishmentType::PrescolairePrimaire->value) {
            $data['direction_id'] = null;
        } else {
            $data['inspection_id'] = null;
        }

        unset($data['logo']);

        if ($this->editingId) {
            $establishment = Establishment::findOrFail($this->editingId);
            $this->authorize('update', $establishment);
        } else {
            $this->authorize('create', Establishment::class);
            $establishment = new Establishment;
            $data['slug'] = UniqueSlug::for(Establishment::class, $data['name']);
        }

        if ($this->logo) {
            if ($establishment->logo_path) {
                Storage::disk('public')->delete($establishment->logo_path);
            }

            $data['logo_path'] = $this->logo->store('establishments-logos', 'public');
        }

        $establishment->fill($data);
        $establishment->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function approveRegistration(int $registrationId, SchoolRegistrationApprover $approver): void
    {
        $registration = SchoolRegistration::findOrFail($registrationId);

        $this->authorize('approve', $registration);

        unset($this->registrationErrors[$registrationId]);

        try {
            $approver->approve($registration, Auth::user());
        } catch (SchoolRegistrationConflictException $e) {
            $this->registrationErrors[$registrationId] = $e->getMessage();
        }
    }

    public function rejectRegistration(int $registrationId, SchoolRegistrationApprover $approver): void
    {
        $registration = SchoolRegistration::findOrFail($registrationId);

        $this->authorize('reject', $registration);

        unset($this->registrationErrors[$registrationId]);

        $approver->reject($registration, Auth::user());
    }

    public function delete(int $establishmentId): void
    {
        $establishment = Establishment::findOrFail($establishmentId);

        $this->authorize('delete', $establishment);

        $establishment->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'foundation_id', 'type', 'address', 'phone', 'is_active',
            'inspection_id', 'direction_id', 'opening_code', 'dsps_code', 'latitude', 'longitude', 'email',
            'is_arabe', 'logo', 'existingLogoPath',
        ]);
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.establishments.index', [
            'establishments' => Establishment::with('foundation')->orderBy('name')->get(),
            'pendingRegistrations' => Auth::user()?->can('viewAny', SchoolRegistration::class)
                ? SchoolRegistration::with(['inspection', 'direction'])->oldest()->get()
                : collect(),
            'foundations' => Foundation::orderBy('name')->get(),
            'types' => EstablishmentType::cases(),
            'inspections' => Inspection::orderBy('inspection_name')->get(),
            'directions' => Direction::orderBy('direction_name')->get(),
        ])->title(__('Établissements'));
    }
}
