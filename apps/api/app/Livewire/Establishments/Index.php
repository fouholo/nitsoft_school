<?php

declare(strict_types=1);

namespace App\Livewire\Establishments;

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\Inspection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public string $opening_code = '';

    public string $dsps_code = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $email = '';

    public bool $is_arabe = false;

    public ?TemporaryUploadedFile $logo = null;

    public string $existingLogoPath = '';

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
            'inspection_id' => ['nullable', 'integer', 'exists:inspections,id'],
            'opening_code' => ['nullable', 'string', 'max:100'],
            'dsps_code' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_arabe' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
        ]);

        foreach (['inspection_id', 'opening_code', 'dsps_code', 'latitude', 'longitude', 'email'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        unset($data['logo']);

        if ($this->editingId) {
            $establishment = Establishment::findOrFail($this->editingId);
            $this->authorize('update', $establishment);
        } else {
            $this->authorize('create', Establishment::class);
            $establishment = new Establishment;
            $data['slug'] = $this->uniqueSlugFor($data['name']);
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

    private function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Establishment::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'foundation_id', 'type', 'address', 'phone', 'is_active',
            'inspection_id', 'opening_code', 'dsps_code', 'latitude', 'longitude', 'email',
            'is_arabe', 'logo', 'existingLogoPath',
        ]);
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.establishments.index', [
            'establishments' => Establishment::with('foundation')->orderBy('name')->get(),
            'foundations' => Foundation::orderBy('name')->get(),
            'types' => EstablishmentType::cases(),
            'inspections' => Inspection::orderBy('inspection_name')->get(),
        ])->title(__('Établissements'));
    }
}
