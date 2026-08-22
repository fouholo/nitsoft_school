<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Domain\Establishments\Enums\Gender;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Establishment $establishment;

    public EstablishmentUserPivot $pivot;

    public bool $canEditProfessional = false;

    public string $gender = '';

    public string $birth_date = '';

    public string $birth_place = '';

    public string $nationality = '';

    public string $city = '';

    public ?TemporaryUploadedFile $photo = null;

    public string $existingPhotoPath = '';

    public string $matricule = '';

    public string $job_title = '';

    public string $hired_at = '';

    public string $education_level = '';

    public function mount(Establishment $establishment, EstablishmentUserPivot $pivot): void
    {
        $this->authorize('view', $pivot);

        $this->establishment = $establishment;
        $this->pivot = $pivot;
        $this->canEditProfessional = Gate::allows('update', $pivot);

        $user = $pivot->user;
        $this->gender = $user->gender !== null ? $user->gender->value : '';
        $this->birth_date = $user->birth_date?->format('Y-m-d') ?? '';
        $this->birth_place = (string) $user->birth_place;
        $this->nationality = (string) $user->nationality;
        $this->city = (string) $user->city;
        $this->existingPhotoPath = (string) $user->photo_path;

        $this->matricule = (string) $pivot->matricule;
        $this->job_title = (string) $pivot->job_title;
        $this->hired_at = $pivot->hired_at?->format('Y-m-d') ?? '';
        $this->education_level = (string) $pivot->education_level;
    }

    public function save(): void
    {
        $this->authorize('view', $this->pivot);

        $data = $this->validate([
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
        ]);

        foreach (['gender', 'birth_date', 'birth_place', 'nationality', 'city'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        unset($data['photo']);

        $user = $this->pivot->user;

        if ($this->photo) {
            if ($user->photo_path) {
                Storage::disk('public')->delete($user->photo_path);
            }

            $data['photo_path'] = $this->photo->store('staff-photos', 'public');
        }

        $user->fill($data);
        $user->save();
        $this->existingPhotoPath = (string) $user->photo_path;
        $this->photo = null;

        if ($this->canEditProfessional) {
            $proData = $this->validate([
                'matricule' => ['nullable', 'string', 'max:50'],
                'job_title' => ['nullable', 'string', 'max:255'],
                'hired_at' => ['nullable', 'date'],
                'education_level' => ['nullable', 'string', 'max:255'],
            ]);

            foreach ($proData as $field => $value) {
                $proData[$field] = $value !== '' ? $value : null;
            }

            $this->pivot->update($proData);
        }

        session()->flash('status', __('Fiche mise à jour.'));
    }

    public function render()
    {
        return view('livewire.staff.show')->title(__(':name — Fiche personnel', ['name' => $this->pivot->user->name]));
    }
}
