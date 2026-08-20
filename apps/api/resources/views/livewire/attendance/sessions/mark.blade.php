<div>
    <a href="{{ route('attendance.sessions.index') }}" class="text-sm text-stone-500 hover:text-stone-900">&larr; Retour aux présences</a>

    <div class="mt-2">
        <h1 class="text-2xl font-semibold text-stone-900">Appel — {{ $session->classroom?->name }}</h1>
        <p class="text-sm text-stone-500">
            {{ $session->session_date->format('d/m/Y') }} @if ($session->subject) — {{ $session->subject->name }} @endif
        </p>
    </div>

    @if (count($students) > 0)
        <div class="mt-4 flex gap-2">
            <button type="button" wire:click="markAll('present')" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                Marquer tout présent
            </button>
            <button type="button" wire:click="markAll('absent')" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                Marquer tout absent
            </button>
        </div>
    @endif

    <form wire:submit="save" class="mt-4">
        <div class="overflow-hidden rounded-lg border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Élève</th>
                            <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Statut</th>
                            <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($students as $student)
                            <tr
                                wire:key="attendance-row-{{ $student->id }}"
                                x-data="{ status: $wire.entangle('statuses.{{ $student->id }}') }"
                                :class="{ 'bg-red-50/70': status === 'absent', 'bg-amber-50/70': status === 'late' || status === 'excused' }"
                            >
                                <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $student->last_name }} {{ $student->first_name }}</td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <select x-model="status" class="block w-36 rounded-lg border-stone-300 text-sm">
                                        <option value="present">Présent</option>
                                        <option value="absent">Absent</option>
                                        <option value="late">En retard</option>
                                        <option value="excused">Excusé</option>
                                    </select>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <input type="text" wire:model="notes.{{ $student->id }}" class="block w-full rounded-lg border-stone-300 text-sm">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-stone-500">Aucun élève inscrit dans cette classe.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                Enregistrer les présences
            </button>

            @if ($justSaved)
                <span class="rounded-lg bg-emerald-50 px-3 py-1.5 text-sm text-emerald-700">
                    Présences enregistrées.
                </span>
            @endif
        </div>
    </form>
</div>
