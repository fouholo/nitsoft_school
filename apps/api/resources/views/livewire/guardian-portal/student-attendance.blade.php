<div>
    <a href="{{ route('guardian-portal.dashboard') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Mes enfants</a>

    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Présences — {{ $student->last_name }} {{ $student->first_name }}</h1>

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Date</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Statut</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($records as $record)
                    <tr wire:key="guardian-attendance-{{ $record->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $record->session?->session_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700' => $record->status === 'present',
                                'bg-red-100 text-red-700' => $record->status === 'absent',
                                'bg-amber-100 text-amber-700' => in_array($record->status, ['late', 'excused']),
                            ])>
                                {{ $record->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $record->note }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-slate-500">Aucune présence enregistrée.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
