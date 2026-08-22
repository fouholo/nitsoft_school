<div>
    <a href="{{ route('grading.grade-sheets.primaire-students', $gradeSheet) }}" class="text-sm text-stone-500 hover:text-stone-900">{!! app()->getLocale() === 'ar' ? '&rarr;' : '&larr;' !!} {{ __('Retour aux élèves') }}</a>

    <div class="mt-2">
        <h1 class="text-2xl font-semibold text-stone-900">{{ $student->last_name }} {{ $student->first_name }}</h1>
        <p class="text-sm text-stone-500">
            {{ $classroom->name }} — {{ $gradeSheet->title }} ({{ __('Composition :number', ['number' => $gradeSheet->composition_number]) }})
        </p>
    </div>

    @if ($justSaved)
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
            {{ __('Notes enregistrées.') }}
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-300 bg-white">
        <div class="border-b border-stone-300 bg-emerald-100 py-2 text-center text-sm font-semibold text-emerald-900">
            {{ __("Informations sur l'élève") }}
        </div>

        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-emerald-700">{{ __('Nom et prénoms') }}</label>
                <div class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-stone-900">{{ $student->last_name }} {{ $student->first_name }}</div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-emerald-700">{{ __('Matricule') }}</label>
                <div class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-stone-900">{{ $student->student_number ?? '—' }}</div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-emerald-700">{{ __('Genre') }}</label>
                <div class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-stone-900">{{ $student->gender ?? '—' }}</div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-emerald-700">{{ __('Date de naissance') }}</label>
                <div class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-stone-900">{{ $student->birth_date?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-emerald-700">{{ __('Lieu de naissance') }}</label>
                <div class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-stone-900">{{ $student->birth_place ?? '—' }}</div>
            </div>
        </div>

        <div class="border-y border-stone-300 bg-emerald-100 py-2 text-center text-sm font-semibold text-emerald-900">
            {{ __('Délibérations des notes') }}
        </div>

        <form wire:submit="save">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-12">
                <div class="overflow-hidden rounded-lg border border-stone-200 md:col-span-8">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50">
                            <tr>
                                <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Matière') }}</th>
                                <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Note') }}</th>
                                <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Absence') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($subjects as $subject)
                                <tr wire:key="subject-{{ $subject->id }}">
                                    <td class="px-4 py-2 text-stone-900">{{ $subject->name }}</td>
                                    <td class="px-4 py-2">
                                        <input
                                            type="number"
                                            step="0.5"
                                            wire:model.live="scores.{{ $subject->id }}"
                                            @disabled($absences[$subject->id] ?? false)
                                            class="block w-24 rounded-lg border-stone-300 text-sm disabled:bg-stone-100 disabled:text-stone-400"
                                        >
                                        @error("scores.{$subject->id}") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-2">
                                        <input
                                            type="checkbox"
                                            wire:model.live="absences.{{ $subject->id }}"
                                            class="rounded border-stone-300 text-orange-700"
                                        >
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune matière configurée pour ce niveau.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="border-t border-stone-200 p-4">
                        <label class="flex items-center gap-2 text-sm font-medium text-stone-900">
                            <input
                                type="checkbox"
                                wire:model.live="absentGenerale"
                                class="rounded border-stone-300 text-rose-600"
                            >
                            {{ __('Absent à la composition') }}
                        </label>
                    </div>
                </div>

                <div class="rounded-lg border border-stone-200 bg-white p-4 md:col-span-4">
                    <h2 class="mb-3 text-sm font-semibold text-emerald-700">{{ __('Résultats') }}</h2>

                    <div class="space-y-4 text-sm">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-emerald-700">{{ __('Total') }}</label>
                            <div class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-stone-900">{{ $preview['totalPoints'] }}</div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-emerald-700">{{ __('Moyenne / :scale', ['scale' => (int) $scale]) }}</label>
                            <div class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 font-semibold text-stone-900">{{ $preview['average'] ?? '—' }}</div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-emerald-700">{{ __('Résultat') }}</label>
                            @php
                                $resultLabel = match ($preview['result']) {
                                    'passed' => __('Admis(e)'),
                                    'absent' => __('Absence'),
                                    default => __('Refusé(e)'),
                                };
                            @endphp
                            <div @class([
                                'rounded-lg border px-3 py-2 font-semibold',
                                'border-emerald-200 bg-emerald-50 text-emerald-700' => $preview['result'] === 'passed',
                                'border-red-200 bg-red-50 text-red-600' => $preview['result'] !== 'passed',
                            ])>{{ $resultLabel }}</div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-emerald-700">{{ __('Appréciation') }}</label>
                            <div class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-stone-900">{{ $preview['appreciation'] ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-stone-200 p-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
            </div>
        </form>
    </div>
</div>
