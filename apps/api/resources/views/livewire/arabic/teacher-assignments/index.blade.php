<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Affectations enseignants arabes') }}</h1>

        @can('create', \App\Domain\Arabic\Models\ArabicTeacherAssignment::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvelle affectation') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-5">
            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Enseignant') }}</label>
                <select wire:model="user_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                    @endforeach
                </select>
                @error('user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Niveau arabe') }}</label>
                <select wire:model.live="arabic_level_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm" dir="rtl">
                    <option value="">—</option>
                    @foreach ($arabicLevels as $arabicLevel)
                        <option value="{{ $arabicLevel->id }}">{{ $arabicLevel->wording }}</option>
                    @endforeach
                </select>
                @error('arabic_level_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($this->selectedLevelRequiresSeries())
                <div>
                    <label class="block text-sm font-medium text-stone-700">{{ __('Série arabe') }}</label>
                    <select wire:model="arabic_serie_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm" dir="rtl">
                        <option value="">—</option>
                        @foreach ($arabicSeries as $arabicSerie)
                            <option value="{{ $arabicSerie->id }}">{{ $arabicSerie->serie_wording }}</option>
                        @endforeach
                    </select>
                    @error('arabic_serie_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Matière arabe') }}</label>
                <select wire:model="arabic_subject_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm" dir="rtl">
                    <option value="">—</option>
                    @foreach ($arabicSubjects as $arabicSubject)
                        <option value="{{ $arabicSubject->id }}">{{ $arabicSubject->name }}</option>
                    @endforeach
                </select>
                @error('arabic_subject_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Année scolaire') }}</label>
                <select wire:model="school_year_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-5">
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
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Enseignant') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Niveau') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Série') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Matière') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Année scolaire') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($assignments as $assignment)
                    <tr wire:key="arabic-assignment-{{ $assignment->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $assignment->teacher?->name }}</td>
                        <td class="px-4 py-2 text-stone-600" dir="rtl">{{ $assignment->arabicLevel?->wording }}</td>
                        <td class="px-4 py-2 text-stone-600" dir="rtl">{{ $assignment->arabicSerie?->serie_wording ?? '—' }}</td>
                        <td class="px-4 py-2 text-stone-600" dir="rtl">{{ $assignment->arabicSubject?->name }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $assignment->schoolYear?->label }}</td>
                        <td class="px-4 py-2 text-end">
                            @can('delete', $assignment)
                                <button
                                    wire:click="delete({{ $assignment->id }})"
                                    wire:confirm="{{ __('Retirer cette affectation ?') }}"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    {{ __('Retirer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune affectation.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
