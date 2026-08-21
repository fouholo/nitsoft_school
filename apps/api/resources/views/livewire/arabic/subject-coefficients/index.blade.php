<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Coefficients par matière arabe</h1>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-stone-700">Niveau arabe</label>
            <select wire:model.live="arabic_level_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($arabicLevels as $arabicLevel)
                    <option value="{{ $arabicLevel->id }}" dir="rtl">{{ $arabicLevel->wording }}</option>
                @endforeach
            </select>
        </div>

        @if ($this->selectedLevelRequiresSeries())
            <div>
                <label class="block text-sm font-medium text-stone-700">Série arabe</label>
                <select wire:model.live="arabic_serie_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($arabicSeries as $arabicSerie)
                        <option value="{{ $arabicSerie->id }}" dir="rtl">{{ $arabicSerie->serie_wording }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    @if ($arabic_level_id && (! $this->selectedLevelRequiresSeries() || $arabic_serie_id))
        <form wire:submit="save" class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-stone-500">Matière</th>
                        <th class="px-4 py-2 text-left font-medium text-stone-500">Coefficient</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($arabicSubjects as $arabicSubject)
                        <tr wire:key="arabic-subject-coefficient-{{ $arabicSubject->id }}">
                            <td class="px-4 py-2 text-stone-900" dir="rtl">{{ $arabicSubject->name }}</td>
                            <td class="px-4 py-2">
                                <input type="number" step="0.5" wire:model="coefficients.{{ $arabicSubject->id }}" class="block w-24 rounded-lg border-stone-300 text-sm">
                                @error("coefficients.{$arabicSubject->id}") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-stone-500">Aucune matière arabe.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="flex gap-2 border-t border-stone-200 p-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    Enregistrer
                </button>
            </div>
        </form>
    @else
        <p class="mt-6 text-sm text-stone-500">Sélectionnez un niveau{{ $arabic_level_id && $this->selectedLevelRequiresSeries() ? ' puis une série' : '' }} pour configurer les coefficients.</p>
    @endif
</div>
