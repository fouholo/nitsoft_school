<div>
    <a href="{{ route('arabic.compositions.students', ['arabicComposition' => $arabicComposition, 'classe' => $arabicClassroom->id]) }}" class="text-sm text-stone-500 hover:text-stone-900">{!! app()->getLocale() === 'ar' ? '&rarr;' : '&larr;' !!} {{ __('Retour aux élèves') }}</a>

    <h1 class="mt-2 text-2xl font-semibold text-stone-900">{{ $enrollment->student?->last_name }} {{ $enrollment->student?->first_name }}</h1>
    <p class="text-sm text-stone-500">{{ $arabicClassroom->name }} — {{ __('Composition de :month', ['month' => $arabicComposition->month->label()]) }}</p>

    @if ($justSaved)
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ __('Notes enregistrées.') }}</div>
    @endif

    @if ($subjects->isEmpty())
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            {{ __('Aucune matière n’est choisie pour le niveau de cette classe.') }}
            <a href="{{ route('arabic.compositions.subjects') }}" class="font-medium underline">{{ __('Matières du primaire') }}</a>
        </div>
    @else
        <form wire:submit="save" class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-12">
            <div class="overflow-hidden rounded-lg border border-stone-200 bg-white md:col-span-8">
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Matière') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Note / 10') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Absence') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($subjects as $levelSubject)
                            @php $subjectId = $levelSubject->arabic_subject_id; @endphp
                            <tr wire:key="subject-{{ $subjectId }}">
                                <td class="px-4 py-2 text-stone-900">
                                    <span dir="auto">{{ $levelSubject->arabicSubject?->name }}</span>
                                    @if ($levelSubject->arabicSubject?->name_fr)
                                        <span class="text-stone-500">— {{ $levelSubject->arabicSubject->name_fr }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        type="number"
                                        step="0.25"
                                        min="0"
                                        max="10"
                                        aria-label="{{ __('Note / 10') }} — {{ $levelSubject->arabicSubject?->name }}"
                                        wire:model.live.blur="scores.{{ $subjectId }}"
                                        @disabled($absences[$subjectId] ?? false)
                                        class="block w-24 rounded-lg border-stone-300 text-sm disabled:bg-stone-100 disabled:text-stone-400"
                                    >
                                    @error("scores.{$subjectId}") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        type="checkbox"
                                        aria-label="{{ __('Absence') }} — {{ $levelSubject->arabicSubject?->name }}"
                                        wire:model.live="absences.{{ $subjectId }}"
                                        class="rounded border-stone-300 text-orange-700"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                <div class="border-t border-stone-200 p-4">
                    <label class="flex items-center gap-2 text-sm font-medium text-stone-900">
                        <input type="checkbox" wire:model.live="absentGenerale" class="rounded border-stone-300">
                        {{ __('Absent à la composition') }}
                    </label>
                </div>
            </div>

            <div class="space-y-4 md:col-span-4">
                <div class="rounded-lg border border-stone-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-stone-900">{{ __('Résultats') }}</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-stone-500">{{ __('Total') }}</dt><dd class="tabular-nums text-stone-900">{{ $preview['total'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-stone-500">{{ __('Matières notées') }}</dt><dd class="tabular-nums text-stone-900">{{ $preview['count'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-stone-500">{{ __('Moyenne / :scale', ['scale' => 10]) }}</dt><dd class="font-semibold tabular-nums text-stone-900">{{ $preview['average'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-stone-500">{{ __('Mention') }}</dt><dd class="text-stone-900">{{ $preview['mention'] ?? '—' }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-lg border border-stone-200 bg-white p-4">
                    <label for="appreciation" class="block text-sm font-medium text-stone-700">{{ __('Appréciation du maître') }}</label>
                    <textarea id="appreciation" wire:model="appreciation" rows="3" maxlength="200" dir="auto" class="mt-1 block w-full rounded-lg border-stone-300 text-sm"></textarea>
                    @error('appreciation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">{{ __('Enregistrer') }}</button>
            </div>
        </form>
    @endif
</div>
