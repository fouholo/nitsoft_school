<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Mon emploi du temps') }}</h1>
        <a href="{{ route('reports.timetable-mine-pdf') }}" target="_blank" class="rounded-lg border border-orange-700 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-50">
            {{ __('Télécharger en PDF') }}
        </a>
    </div>

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
                            <td colspan="{{ count($days) }}" class="bg-stone-50 px-4 py-2 text-center text-xs uppercase tracking-wide text-stone-500">
                                {{ __('Pause') }}
                            </td>
                        @else
                            @foreach ($days as $day)
                                @php $session = $sessions->get($day->value.'-'.$slot->id) @endphp
                                <td class="px-4 py-2 align-top">
                                    @if ($session)
                                        <div class="rounded-lg bg-orange-50 p-2">
                                            <div class="font-medium text-orange-900">{{ $session->subject?->name }}</div>
                                            <div class="text-xs text-orange-700">{{ $session->classroom?->name }}</div>
                                            @if ($session->room)
                                                <div class="text-xs text-orange-700">{{ __('Salle') }} {{ $session->room }}</div>
                                            @endif
                                        </div>
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
</div>
