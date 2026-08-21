<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Coefficients par matière') }}</h1>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-stone-700">{{ __('Niveau') }}</label>
            <select wire:model.live="level_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}">{{ $level->level_wording }}</option>
                @endforeach
            </select>
        </div>

        @if ($this->selectedLevelRequiresSeries())
            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Série') }}</label>
                <select wire:model.live="serie_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($series as $serie)
                        <option value="{{ $serie->id }}">{{ $serie->serie_wording }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    @if ($level_id && (! $this->selectedLevelRequiresSeries() || $serie_id))
        <form wire:submit="save" class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Matière') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Coefficient') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($subjects as $subject)
                        <tr wire:key="subject-coefficient-{{ $subject->id }}">
                            <td class="px-4 py-2 text-stone-900">{{ $subject->name }}</td>
                            <td class="px-4 py-2">
                                <input type="number" step="0.5" wire:model="coefficients.{{ $subject->id }}" class="block w-24 rounded-lg border-stone-300 text-sm">
                                @error("coefficients.{$subject->id}") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune matière.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="flex gap-2 border-t border-stone-200 p-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
            </div>
        </form>
    @else
        <p class="mt-6 text-sm text-stone-500">
            {{ $level_id && $this->selectedLevelRequiresSeries() ? __('Sélectionnez un niveau puis une série pour configurer les coefficients.') : __('Sélectionnez un niveau pour configurer les coefficients.') }}
        </p>
    @endif
</div>
