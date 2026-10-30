<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Relevés de notes') }}</h1>

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-lg border border-stone-200 bg-white p-4">
        <div>
            <label for="arabic_composition_id" class="block text-sm font-medium text-stone-700">{{ __('Composition') }}</label>
            <select id="arabic_composition_id" wire:model.live="arabic_composition_id" class="mt-1 block w-48 rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($compositions as $compositionOption)
                    <option value="{{ $compositionOption->id }}">{{ $compositionOption->month->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="arabic_classroom_id" class="block text-sm font-medium text-stone-700">{{ __('Classe arabe') }}</label>
            <select id="arabic_classroom_id" wire:model.live="arabic_classroom_id" class="mt-1 block w-48 rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($classrooms as $classroomOption)
                    <option value="{{ $classroomOption->id }}">{{ $classroomOption->name }}</option>
                @endforeach
            </select>
        </div>

        @if ($selectedClassroom)
            @can('generate', \App\Domain\Arabic\Models\ArabicComposition::class)
                <button
                    type="button"
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800 disabled:opacity-60"
                >{{ $results->isNotEmpty() ? __('Régénérer les relevés') : __('Générer les relevés') }}</button>
            @endcan

            @if ($results->isNotEmpty())
                <a
                    href="{{ route('reports.arabic-composition-class-pdf', ['arabicComposition' => $selectedComposition, 'arabicClassroom' => $selectedClassroom]) }}"
                    target="_blank"
                    class="rounded-lg border border-orange-700 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-50"
                >{{ __('Tous les relevés (PDF)') }}</a>
            @endif
        @endif
    </div>

    @error('arabic_classroom_id') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

    @if ($hasStaleResults)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            {{ __('Des notes ont changé depuis la dernière génération : régénérez les relevés pour mettre à jour les moyennes et les rangs.') }}
        </div>
    @endif

    @if ($selectedClassroom)
        <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Rang') }}</th>
                        <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Élève') }}</th>
                        <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Moyenne / :scale', ['scale' => 10]) }}</th>
                        <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Mention') }}</th>
                        <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($results as $result)
                        <tr wire:key="result-{{ $result->id }}">
                            <td class="px-4 py-2 tabular-nums text-stone-900">{{ $result->rank ?? __('Non classé') }}</td>
                            <td class="px-4 py-2 text-stone-900">{{ $result->enrollment?->student?->last_name }} {{ $result->enrollment?->student?->first_name }}</td>
                            <td class="px-4 py-2 tabular-nums text-stone-600">{{ $result->average ?? '—' }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $result->mention ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-end">
                                <a href="{{ route('reports.arabic-composition-result-pdf', $result) }}" target="_blank" class="text-stone-500 hover:text-stone-900">{{ __('Voir le PDF') }}</a>
                                <a href="{{ route('reports.arabic-composition-result-pdf', ['arabicCompositionResult' => $result, 'download' => 1]) }}" class="ms-3 text-stone-500 hover:text-stone-900">{{ __('Télécharger') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Relevés non encore générés pour cette classe.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    @endif
</div>
