<div>
    <a href="{{ route('guardian-portal.dashboard') }}" class="text-sm text-stone-500 hover:text-stone-900">&larr; {{ __('Mes enfants') }}</a>

    <h1 class="mt-2 text-2xl font-semibold text-stone-900">{{ __('Présences — :name', ['name' => $student->last_name.' '.$student->first_name]) }}</h1>

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Note') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($records as $record)
                    <tr wire:key="guardian-attendance-{{ $record->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $record->session?->session_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">
                            @php
                                $statusLabel = match ($record->status) {
                                    'present' => __('Présent'),
                                    'absent' => __('Absent'),
                                    'late' => __('En retard'),
                                    'excused' => __('Excusé'),
                                    default => $record->status,
                                };
                            @endphp
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700' => $record->status === 'present',
                                'bg-red-100 text-red-700' => $record->status === 'absent',
                                'bg-amber-100 text-amber-700' => in_array($record->status, ['late', 'excused']),
                            ])>
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-stone-600">{{ $record->note }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune présence enregistrée.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
