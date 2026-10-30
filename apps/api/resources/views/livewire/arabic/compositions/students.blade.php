<div>
    <a href="{{ route('arabic.compositions.index') }}" class="text-sm text-stone-500 hover:text-stone-900">{!! app()->getLocale() === 'ar' ? '&rarr;' : '&larr;' !!} {{ __('Retour aux compositions') }}</a>

    <h1 class="mt-2 text-2xl font-semibold text-stone-900">{{ __('Composition de :month', ['month' => $arabicComposition->month->label()]) }}</h1>
    <p class="text-sm text-stone-500">{{ $arabicComposition->graded_on->format('d/m/Y') }} — {{ $arabicComposition->schoolYear?->label }}</p>

    <div class="mt-4 max-w-sm">
        <label for="arabic_classroom_id" class="block text-sm font-medium text-stone-700">{{ __('Classe arabe') }}</label>
        <select id="arabic_classroom_id" wire:model.live="arabic_classroom_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            <option value="">—</option>
            @foreach ($classrooms as $classroomOption)
                <option value="{{ $classroomOption->id }}">{{ $classroomOption->name }}</option>
            @endforeach
        </select>
    </div>

    @if ($selectedClassroom)
        <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Élève') }}</th>
                        <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Notes saisies') }}</th>
                        <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($enrollments as $enrollment)
                        <tr wire:key="enrollment-{{ $enrollment->id }}">
                            <td class="px-4 py-2 text-stone-900">{{ $enrollment->student?->last_name }} {{ $enrollment->student?->first_name }}</td>
                            <td class="px-4 py-2 tabular-nums text-stone-600">{{ $gradeCounts->get($enrollment->id, 0) }}</td>
                            <td class="px-4 py-2 text-end">
                                <a href="{{ route('arabic.compositions.enter', ['arabicComposition' => $arabicComposition, 'enrollment' => $enrollment]) }}" class="text-orange-700 hover:underline">{{ __('Saisir les notes') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun élève dans cette classe.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    @endif
</div>
