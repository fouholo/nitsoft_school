<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Grille de créneaux') }}</h1>

        @can('create', \App\Domain\Timetable\Models\TimetableSlot::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouveau créneau') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-5">
            <div>
                <label for="label" class="block text-sm font-medium text-stone-700">{{ __('Libellé') }}</label>
                <input id="label" type="text" wire:model="label" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="start_time" class="block text-sm font-medium text-stone-700">{{ __('Heure de début') }}</label>
                <input id="start_time" type="time" wire:model="start_time" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('start_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="end_time" class="block text-sm font-medium text-stone-700">{{ __('Heure de fin') }}</label>
                <input id="end_time" type="time" wire:model="end_time" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('end_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="sequence" class="block text-sm font-medium text-stone-700">{{ __('Ordre') }}</label>
                <input id="sequence" type="number" wire:model="sequence" min="1" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('sequence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-end pb-1.5">
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" wire:model="is_break" class="rounded border-stone-300">
                    {{ __('Pause / récréation') }}
                </label>
            </div>

            <div class="flex gap-2 sm:col-span-5">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
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
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Libellé') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Horaire') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Type') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($timetableSlots as $slot)
                    <tr wire:key="slot-{{ $slot->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $slot->label }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}</td>
                        <td class="px-4 py-2 text-stone-600">
                            @if ($slot->is_break)
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ __('Pause') }}</span>
                            @else
                                {{ __('Cours') }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-end">
                            @can('update', $slot)
                                <button wire:click="edit({{ $slot->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $slot)
                                <button
                                    wire:click="delete({{ $slot->id }})"
                                    wire:confirm="{{ __('Supprimer ce créneau ?') }}"
                                    class="ms-3 text-red-600 hover:text-red-800"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun créneau configuré.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
