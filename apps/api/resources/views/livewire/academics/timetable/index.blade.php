<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-stone-900">{{ __('Emploi du temps') }} — {{ $classroom->name }}</h1>
            <p class="mt-1 text-sm text-stone-500">
                <a href="{{ route('reports.timetable-classroom-pdf', $classroom) }}" target="_blank" class="text-orange-700 hover:underline">
                    {{ __('Télécharger en PDF') }}
                </a>
            </p>
        </div>
    </div>

    @error('conflict') <p class="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-600">{{ $message }}</p> @enderror

    <div class="mt-4 overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Créneau') }}</th>
                    @foreach ($days as $day)
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ $day->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($timetableSlots as $slot)
                    <tr wire:key="slot-row-{{ $slot->id }}">
                        <td class="px-4 py-2 align-top text-stone-600">
                            <div class="font-medium text-stone-900">{{ $slot->label }}</div>
                            <div class="text-xs text-stone-500">{{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}</div>
                        </td>

                        @if ($slot->is_break)
                            <td colspan="{{ count($days) }}" class="bg-stone-50 px-4 py-2 text-center text-xs uppercase tracking-wide text-stone-400">
                                {{ __('Pause') }}
                            </td>
                        @else
                            @foreach ($days as $day)
                                @php $session = $sessions->get($day->value.'-'.$slot->id) @endphp
                                <td class="px-4 py-2 align-top">
                                    @if ($session)
                                        <button
                                            type="button"
                                            @if ($canManage) wire:click="openCell('{{ $day->value }}', {{ $slot->id }})" @endif
                                            class="block w-full rounded-lg bg-orange-50 p-2 text-start {{ $canManage ? 'hover:bg-orange-100' : '' }}"
                                        >
                                            <div class="font-medium text-orange-900">{{ $session->subject?->name }}</div>
                                            <div class="text-xs text-orange-700">{{ $session->teacher?->name }}</div>
                                            @if ($session->room)
                                                <div class="text-xs text-orange-700">{{ __('Salle') }} {{ $session->room }}</div>
                                            @endif
                                        </button>
                                    @elseif ($canManage)
                                        <button
                                            type="button"
                                            wire:click="openCell('{{ $day->value }}', {{ $slot->id }})"
                                            class="flex h-full w-full items-center justify-center rounded-lg border border-dashed border-stone-200 p-2 text-stone-300 hover:border-orange-300 hover:text-orange-500"
                                        >
                                            +
                                        </button>
                                    @endif
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($days) + 1 }}" class="px-4 py-6 text-center text-stone-500">
                            {{ __("Aucun créneau configuré pour cet établissement.") }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div class="sm:col-span-3 text-sm text-stone-500">
                {{ collect($days)->first(fn ($d) => $d->value === $day_of_week)?->label() }}
                — {{ $timetableSlots->firstWhere('id', $timetable_slot_id)?->label }}
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Enseignant / matière') }}</label>
                <select wire:model="teacher_assignment_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($assignments as $assignment)
                        <option value="{{ $assignment->id }}">{{ $assignment->teacher?->name }} — {{ $assignment->subject?->name }}</option>
                    @endforeach
                </select>
                @error('teacher_assignment_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Salle') }}</label>
                <input type="text" wire:model="room" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('room') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                @if ($editingId)
                    <button
                        type="button"
                        wire:click="delete"
                        wire:confirm="{{ __('Retirer cette séance ?') }}"
                        class="rounded-lg border border-red-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    >
                        {{ __('Retirer') }}
                    </button>
                @endif
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif
</div>
