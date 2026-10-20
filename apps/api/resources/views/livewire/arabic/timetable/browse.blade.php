<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Emplois du temps arabes') }}</h1>

    <div class="mt-4 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Classe arabe') }}</th>
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Séances') }}</th>
                    <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($arabicClassrooms as $arabicClassroom)
                    <tr wire:key="arabic-classroom-{{ $arabicClassroom->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $arabicClassroom->name }}</td>
                        <td class="px-4 py-2 tabular-nums text-stone-600">{{ $arabicClassroom->timetable_sessions_count }}</td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('arabic.timetable.index', $arabicClassroom) }}" class="text-orange-700 hover:underline">{{ __('Voir la grille') }}</a>
                            <a href="{{ route('reports.arabic-timetable-classroom-pdf', $arabicClassroom) }}" target="_blank" class="ms-3 text-stone-500 hover:text-stone-900">{{ __('PDF') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune classe arabe pour cette année scolaire.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
