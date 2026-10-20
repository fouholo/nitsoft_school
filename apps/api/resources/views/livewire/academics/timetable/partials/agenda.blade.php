{{-- Agenda de la semaine d'un enseignant d'école franco-arabe : séances
     françaises et arabes réunies, triées par heure (voir WeeklyAgenda). --}}
<div class="mt-4 overflow-x-auto rounded-lg border border-stone-200 bg-white">
    <table class="min-w-full divide-y divide-stone-200 text-sm">
        <thead class="bg-stone-50">
            <tr>
                @foreach ($agenda['days'] as $day)
                    <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ $day->label() }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach ($agenda['days'] as $day)
                    <td class="min-w-40 px-3 py-3 align-top">
                        <div class="space-y-2">
                            @forelse ($agenda['entries'][$day->value] ?? [] as $entry)
                                <div @class([
                                    'rounded-lg p-2',
                                    'bg-orange-50' => ! $entry['arabic'],
                                    'bg-emerald-50' => $entry['arabic'],
                                ])>
                                    <div class="flex items-center justify-between gap-2 text-xs tabular-nums text-stone-500">
                                        <span>{{ $entry['start'] }}–{{ $entry['end'] }}</span>
                                        @if ($entry['arabic'])
                                            <span class="rounded-full bg-emerald-100 px-1.5 py-0.5 font-medium text-emerald-800">{{ __('Arabe') }}</span>
                                        @endif
                                    </div>
                                    <div class="font-medium text-stone-900" dir="auto">{{ $entry['subject'] }}</div>
                                    <div class="text-xs text-stone-600">{{ $entry['classroom'] }}</div>
                                    @if ($entry['room'])
                                        <div class="text-xs text-stone-600">{{ __('Salle') }} {{ $entry['room'] }}</div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-stone-400">—</p>
                            @endforelse
                        </div>
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>
