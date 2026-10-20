<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Classes arabes') }}</h1>

        @can('create', \App\Domain\Arabic\Models\ArabicClassroom::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvelle classe arabe') }}
            </button>
        @endcan
    </div>

    @error('delete')
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label for="arabic_level_id" class="block text-sm font-medium text-stone-700">{{ __('Niveau arabe') }}</label>
                <select id="arabic_level_id" wire:model.live="arabic_level_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($arabicLevels as $arabicLevel)
                        <option value="{{ $arabicLevel->id }}">{{ $arabicLevel->wording }}{{ $arabicLevel->wording_fr ? ' — '.$arabicLevel->wording_fr : '' }}</option>
                    @endforeach
                </select>
                @error('arabic_level_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($this->selectedLevelRequiresSeries())
                <div>
                    <label for="arabic_serie_id" class="block text-sm font-medium text-stone-700">{{ __('Série arabe') }}</label>
                    <select id="arabic_serie_id" wire:model="arabic_serie_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        <option value="">—</option>
                        @foreach ($arabicSeries as $arabicSerie)
                            <option value="{{ $arabicSerie->id }}">{{ $arabicSerie->serie }} — {{ $arabicSerie->serie_wording }}</option>
                        @endforeach
                    </select>
                    @error('arabic_serie_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="numero" class="block text-sm font-medium text-stone-700">{{ __('Numéro') }}</label>
                <select id="numero" wire:model="numero" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($this->numeroOptions() as $numeroOption)
                        <option value="{{ $numeroOption }}">{{ $numeroOption }}</option>
                    @endforeach
                </select>
                @error('numero') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="capacity" class="block text-sm font-medium text-stone-700">{{ __('Capacité') }}</label>
                <input id="capacity" type="number" wire:model="capacity" min="1" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('capacity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="school_year_id" class="block text-sm font-medium text-stone-700">{{ __('Année scolaire') }}</label>
                <select id="school_year_id" wire:model="school_year_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Niveau arabe') }}</th>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Élèves') }}</th>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Capacité') }}</th>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Année scolaire') }}</th>
                    <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($arabicClassrooms as $arabicClassroom)
                    <tr wire:key="arabic-classroom-{{ $arabicClassroom->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $arabicClassroom->name }}</td>
                        <td class="px-4 py-2 text-stone-600">
                            <span dir="rtl">{{ $arabicClassroom->arabicLevel?->wording }}</span>
                            @if ($arabicClassroom->arabicSerie)
                                — <span dir="rtl">{{ $arabicClassroom->arabicSerie->serie_wording }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 tabular-nums text-stone-600">{{ $arabicClassroom->enrollments_count }}</td>
                        <td class="px-4 py-2 tabular-nums text-stone-600">{{ $arabicClassroom->capacity }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $arabicClassroom->schoolYear?->label }}</td>
                        <td class="px-4 py-2 text-end">
                            @can('update', $arabicClassroom)
                                <button type="button" wire:click="edit({{ $arabicClassroom->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $arabicClassroom)
                                <button
                                    type="button"
                                    wire:click="delete({{ $arabicClassroom->id }})"
                                    wire:confirm="{{ __('Supprimer cette classe ?') }}"
                                    class="ms-3 text-red-600 hover:text-red-800"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune classe arabe.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
