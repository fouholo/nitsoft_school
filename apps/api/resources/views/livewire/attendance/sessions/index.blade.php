<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Présences') }}</h1>

        @can('create', \App\Domain\Attendance\Models\AttendanceSession::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvel appel') }}
            </button>
        @endcan
    </div>

    @if ($pendingToday > 0)
        <div class="mt-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-2.5 text-sm text-orange-900">
            {{ __(':count appel(s) à faire aujourd\'hui.', ['count' => $pendingToday]) }}
        </div>
    @elseif ($todayTotal > 0)
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">
            {{ __("Tout est fait pour aujourd'hui.") }}
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Classe') }}</label>
                <select wire:model="classroom_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                @error('classroom_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Matière (optionnel)') }}</label>
                <select wire:model="subject_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">{{ __('Appel général') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Date') }}</label>
                <input type="date" wire:model="session_date" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('session_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Heure') }}</label>
                <input type="time" wire:model="started_at" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            </div>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800 focus:outline-none focus:ring-2 focus:ring-orange-600 focus:ring-offset-1">
                    {{ __("Créer et faire l'appel") }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50 focus:outline-none focus:ring-2 focus:ring-orange-600 focus:ring-offset-1">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Classe') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Matière') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($sessions as $session)
                        <tr wire:key="session-{{ $session->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $session->session_date->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $session->classroom?->name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $session->subject?->name ?? __('Appel général') }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($session->records_count > 0)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        {{ __('Fait') }}
                                        @if ($session->absences_count > 0)
                                            — {{ __(':count exception(s)', ['count' => $session->absences_count]) }}
                                        @endif
                                    </span>
                                @elseif ($session->session_date->lt(today()))
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        {{ __('En retard') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-700">
                                        {{ __('À faire') }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-end">
                                <a href="{{ route('attendance.sessions.mark', $session) }}" class="inline-flex min-h-11 items-center text-stone-500 hover:text-stone-900">
                                    {{ __("Faire l'appel") }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun appel.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
