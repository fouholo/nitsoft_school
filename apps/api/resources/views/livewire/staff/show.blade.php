<div>
    <a href="{{ route('staff.index', $establishment) }}" wire:navigate class="text-sm text-stone-500 hover:text-stone-900">&larr; {{ __('Retour à la liste du personnel') }}</a>

    <div class="mt-2 flex items-center gap-4">
        @if ($existingPhotoPath)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingPhotoPath) }}" alt="{{ $pivot->user->name }}" class="h-16 w-16 rounded-full object-cover">
        @else
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-stone-200 text-lg font-semibold text-stone-500">
                {{ mb_strtoupper(mb_substr($pivot->user->name, 0, 1)) }}
            </div>
        @endif
        <div>
            <h1 class="text-2xl font-semibold text-stone-900">{{ $pivot->user->name }}</h1>
            <p class="text-sm text-stone-500">
                {{ \App\Models\User::roleLabel($pivot->role) }}
                <span class="mx-1">&middot;</span>
                <span class="{{ $pivot->is_active ? 'text-green-700' : 'text-stone-500' }}">
                    {{ $pivot->is_active ? __('Actif') : __('En attente / Inactif') }}
                </span>
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="mt-6 max-w-2xl space-y-6">
        <div class="rounded-lg border border-stone-200 bg-white p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Identité civile') }}</h2>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __('Genre') }}</label>
                    <select wire:model="gender" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        <option value="">{{ __('Non renseigné') }}</option>
                        @foreach (\App\Domain\Establishments\Enums\Gender::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('gender') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __('Date de naissance') }}</label>
                    <input type="date" wire:model="birth_date" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @error('birth_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __('Lieu de naissance') }}</label>
                    <input type="text" wire:model="birth_place" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @error('birth_place') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __('Nationalité') }}</label>
                    <input type="text" wire:model="nationality" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @error('nationality') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-stone-200 bg-white p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Coordonnées') }}</h2>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __('Ville / commune') }}</label>
                    <input type="text" wire:model="city" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __('Photo') }}</label>
                    <input type="file" wire:model="photo" class="mt-1 block w-full text-sm">
                    @error('photo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-stone-200 bg-white p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Données professionnelles') }}</h2>
            @unless ($canEditProfessional)
                <p class="mt-1 text-xs text-stone-400">{{ __('Réservé à un administrateur de l\'établissement.') }}</p>
            @endunless

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __('Matricule') }}</label>
                    <input type="text" wire:model="matricule" @disabled(! $canEditProfessional) class="mt-1 block w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100 disabled:text-stone-500">
                    @error('matricule') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __('Fonction / poste occupé') }}</label>
                    <input type="text" wire:model="job_title" @disabled(! $canEditProfessional) class="mt-1 block w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100 disabled:text-stone-500">
                    @error('job_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __("Date d'embauche") }}</label>
                    <input type="date" wire:model="hired_at" @disabled(! $canEditProfessional) class="mt-1 block w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100 disabled:text-stone-500">
                    @error('hired_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-700">{{ __("Diplôme / niveau d'étude") }}</label>
                    <input type="text" wire:model="education_level" @disabled(! $canEditProfessional) class="mt-1 block w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100 disabled:text-stone-500">
                    @error('education_level') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
            {{ __('Enregistrer') }}
        </button>
    </form>
</div>
