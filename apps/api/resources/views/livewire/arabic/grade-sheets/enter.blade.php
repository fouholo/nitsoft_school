<div>
    <a href="{{ route('arabic.grade-sheets.index') }}" class="text-sm text-stone-500 hover:text-stone-900">&larr; {{ __('Retour aux évaluations') }}</a>

    <div class="mt-2">
        <h1 class="text-2xl font-semibold text-stone-900">{{ $gradeSheet->title }}</h1>
        <p class="text-sm text-stone-500">
            <span dir="rtl">{{ $gradeSheet->arabicLevel?->wording }}</span>
            — <span dir="rtl">{{ $gradeSheet->arabicSubject?->name }}</span>
            — {{ __('Barème :max', ['max' => $gradeSheet->max_score]) }} — {{ $gradeSheet->arabicTerm?->label }}
            @if ($totalCount > 0)
                — {{ __(':scored/:total notées', ['scored' => $scoredCount, 'total' => $totalCount]) }}
            @endif
        </p>
    </div>

    @if ($justSaved)
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
            {{ __('Notes enregistrées.') }}
        </div>
    @endif

    <form
        wire:submit="save"
        novalidate
        x-data="{ dirty: false }"
        x-on:input="dirty = true"
        x-on:submit="dirty = false"
        x-on:beforeunload.window="dirty && ($event.preventDefault(), $event.returnValue = '')"
        class="mt-6"
    >
        <div class="overflow-hidden rounded-lg border border-stone-200 bg-white">
            <div class="max-h-[70vh] overflow-y-auto overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="sticky top-0 z-10 bg-stone-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Élève') }}</th>
                            <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Note / :max', ['max' => $gradeSheet->max_score]) }}</th>
                            <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Commentaire') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($enrollments as $enrollment)
                            <tr wire:key="arabic-grade-row-{{ $enrollment->id }}" class="odd:bg-white even:bg-stone-50/60">
                                <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $enrollment->student?->last_name }} {{ $enrollment->student?->first_name }}</td>
                                <td class="whitespace-nowrap px-4 py-2" x-data="{ invalid: false }">
                                    <input
                                        type="number"
                                        step="0.25"
                                        min="0"
                                        max="{{ $gradeSheet->max_score }}"
                                        wire:model="scores.{{ $enrollment->id }}"
                                        data-score-input
                                        x-on:input="invalid = $el.value !== '' && (Number($el.value) < 0 || Number($el.value) > {{ $gradeSheet->max_score }})"
                                        x-on:keydown.enter.prevent="const next = $el.closest('tr').nextElementSibling?.querySelector('[data-score-input]'); if (next) next.focus();"
                                        :class="invalid ? 'border-red-400' : 'border-stone-300'"
                                        class="block w-24 rounded-lg text-sm"
                                    >
                                    <p x-show="invalid" x-cloak class="mt-1 text-sm text-red-600">{{ __('Doit être compris entre 0 et :max.', ['max' => $gradeSheet->max_score]) }}</p>
                                    @error('scores.'.$enrollment->id) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <input
                                        type="text"
                                        wire:model="comments.{{ $enrollment->id }}"
                                        class="block w-full rounded-lg border-stone-300 text-sm"
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun élève inscrit dans ce groupe.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <button type="submit" class="mt-4 rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
            {{ __('Enregistrer les notes') }}
        </button>
    </form>
</div>
