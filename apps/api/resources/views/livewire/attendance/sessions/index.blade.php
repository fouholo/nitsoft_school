<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Présences</h1>

        @can('create', \App\Domain\Attendance\Models\AttendanceSession::class)
            <button type="button" wire:click="create" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Nouvel appel
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Classe</label>
                <select wire:model="classroom_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                @error('classroom_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Matière (optionnel)</label>
                <select wire:model="subject_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">Appel général</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Date</label>
                <input type="date" wire:model="session_date" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('session_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Heure</label>
                <input type="time" wire:model="started_at" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Créer et faire l'appel
                </button>
                <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Date</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Classe</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Matière</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sessions as $session)
                    <tr wire:key="session-{{ $session->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $session->session_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $session->classroom?->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $session->subject?->name ?? 'Appel général' }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('attendance.sessions.mark', $session) }}" class="text-slate-500 hover:text-slate-900">
                                Faire l'appel
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">Aucun appel.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
