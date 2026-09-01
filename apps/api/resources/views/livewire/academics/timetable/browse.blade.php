<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Emplois du temps') }}</h1>

    <div class="mt-4 flex gap-1 border-b border-stone-200">
        <button
            type="button"
            wire:click="selectTab('classes')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'classes' ? 'border-orange-700 text-orange-700' : 'border-transparent text-stone-500 hover:text-stone-700' }}"
        >
            {{ __('Classes') }}
        </button>
        <button
            type="button"
            wire:click="selectTab('teachers')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'teachers' ? 'border-orange-700 text-orange-700' : 'border-transparent text-stone-500 hover:text-stone-700' }}"
        >
            {{ __('Enseignants') }}
        </button>
    </div>

    @if ($tab === 'classes')
        <div class="mt-4 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Classe') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($classrooms as $classroom)
                        <tr wire:key="classroom-{{ $classroom->id }}">
                            <td class="px-4 py-2 text-stone-900">{{ $classroom->name }}</td>
                            <td class="px-4 py-2 text-end">
                                <a href="{{ route('academics.timetable.index', $classroom) }}" class="text-orange-700 hover:underline">
                                    {{ __('Voir la grille') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune classe secondaire pour cette année scolaire.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="mt-4 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Enseignant') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($teachers as $teacher)
                        <tr wire:key="teacher-{{ $teacher->id }}">
                            <td class="px-4 py-2 text-stone-900">{{ $teacher->name }}</td>
                            <td class="px-4 py-2 text-end">
                                <a href="{{ route('academics.timetable.teachers.show', $teacher) }}" class="text-orange-700 hover:underline">
                                    {{ __('Voir la grille') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun enseignant actif.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
