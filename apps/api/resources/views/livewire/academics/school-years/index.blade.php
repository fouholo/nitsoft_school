<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Années scolaires') }}</h1>

        @can('create', \App\Domain\Academics\Models\SchoolYear::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                {{ __('Nouvelle année') }}
            </button>
        @endcan
    </div>

    @cannot('create', \App\Domain\Academics\Models\SchoolYear::class)
        <p class="mt-1 text-sm text-stone-500">
            {{ __("Ce calendrier est commun à tous les établissements et géré par l'administration de la plateforme.") }}
        </p>
    @endcannot

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Libellé') }}</label>
                <input type="text" wire:model="label" placeholder="2026-2027" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Début') }}</label>
                <input type="date" wire:model="starts_on" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('starts_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Fin') }}</label>
                <input type="date" wire:model="ends_on" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('ends_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-600 sm:col-span-4">
                <input type="checkbox" wire:model="is_current" class="rounded border-stone-300">
                {{ __('Définir comme année scolaire courante') }}
            </label>

            <div class="flex gap-2 sm:col-span-4">
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
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Libellé') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Période') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Courante') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($schoolYears as $schoolYear)
                    <tr wire:key="school-year-{{ $schoolYear->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $schoolYear->label }}</td>
                        <td class="px-4 py-2 text-stone-600">
                            {{ $schoolYear->starts_on->format('d/m/Y') }} — {{ $schoolYear->ends_on->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-2">
                            @if ($schoolYear->is_current)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Courante') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-end">
                            @can('update', $schoolYear)
                                <button wire:click="edit({{ $schoolYear->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $schoolYear)
                                <button
                                    wire:click="delete({{ $schoolYear->id }})"
                                    wire:confirm="{{ __('Supprimer cette année scolaire ?') }}"
                                    class="ms-3 text-red-500 hover:text-red-700"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune année scolaire.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
