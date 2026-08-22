<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Groupes scolaires') }}</h1>

        @can('create', \App\Domain\Establishments\Models\Foundation::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                {{ __('Nouveau groupe') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-stone-700">{{ __('Nom du groupe') }}</label>
                <input type="text" wire:model="name" placeholder="{{ __('Groupe Scolaire Excellence') }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" wire:model="is_active" class="rounded border-stone-300">
                {{ __('Actif') }}
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
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Établissements') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($foundations as $foundation)
                    <tr wire:key="foundation-{{ $foundation->id }}">
                        <td class="px-4 py-2 text-stone-900">
                            <a href="{{ route('foundations.show', $foundation) }}" wire:navigate class="hover:underline">{{ $foundation->name }}</a>
                        </td>
                        <td class="px-4 py-2 text-stone-600">{{ $foundation->establishments_count }}</td>
                        <td class="px-4 py-2">
                            @if ($foundation->is_active)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Actif') }}</span>
                            @else
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ __('Inactif') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-end">
                            @can('update', $foundation)
                                <button wire:click="edit({{ $foundation->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $foundation)
                                <button
                                    wire:click="delete({{ $foundation->id }})"
                                    wire:confirm="{{ __('Supprimer ce groupe scolaire ? Les établissements liés redeviendront indépendants.') }}"
                                    class="ms-3 text-red-500 hover:text-red-700"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun groupe scolaire.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
