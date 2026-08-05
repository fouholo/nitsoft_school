<div>
    <a href="{{ route('attendance.sessions.index') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Retour aux présences</a>

    <div class="mt-2">
        <h1 class="text-2xl font-semibold text-slate-900">Appel — {{ $session->classroom?->name }}</h1>
        <p class="text-sm text-slate-500">
            {{ $session->session_date->format('d/m/Y') }} @if ($session->subject) — {{ $session->subject->name }} @endif
        </p>
    </div>

    @if ($justSaved)
        <div class="mt-4 rounded-md bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
            Présences enregistrées.
        </div>
    @endif

    <form wire:submit="save" class="mt-6">
        <div class="overflow-hidden rounded-md border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Élève</th>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Statut</th>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($students as $student)
                        <tr wire:key="attendance-row-{{ $student->id }}">
                            <td class="px-4 py-2 text-slate-900">{{ $student->last_name }} {{ $student->first_name }}</td>
                            <td class="px-4 py-2">
                                <select wire:model="statuses.{{ $student->id }}" class="block w-36 rounded-md border-slate-300 text-sm">
                                    <option value="present">Présent</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">En retard</option>
                                    <option value="excused">Excusé</option>
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <input type="text" wire:model="notes.{{ $student->id }}" class="block w-full rounded-md border-slate-300 text-sm">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-slate-500">Aucun élève inscrit dans cette classe.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <button type="submit" class="mt-4 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
            Enregistrer les présences
        </button>
    </form>
</div>
