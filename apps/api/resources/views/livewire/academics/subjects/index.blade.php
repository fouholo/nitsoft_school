<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Matières') }}</h1>

        @can('create', \App\Domain\Academics\Models\Subject::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvelle matière') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Nom') }}</label>
                <input type="text" wire:model="name" placeholder="Mathématiques" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Abréviation') }}</label>
                <input type="text" wire:model="abbreviation" placeholder="MATHS" maxlength="10" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('abbreviation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Domaine') }}</label>
                <select wire:model="domain_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($domains as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                    @endforeach
                </select>
                @error('domain_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-4">
                <span class="block text-sm font-medium text-stone-700">{{ __('Cycle') }}</span>
                <div class="mt-1 flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="is_prescolaire_primaire" class="rounded border-stone-300">
                        {{ __('Préscolaire/Primaire') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="is_secondaire" class="rounded border-stone-300">
                        {{ __('Secondaire') }}
                    </label>
                </div>
                @error('is_prescolaire_primaire') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

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

    <div class="mt-6 overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Abréviation') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Cycle') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Domaine') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($subjects as $subject)
                    <tr wire:key="subject-{{ $subject->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $subject->name }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $subject->abbreviation }}</td>
                        <td class="px-4 py-2 text-stone-600">
                            @if ($subject->is_prescolaire_primaire && $subject->is_secondaire)
                                {{ __('Préscolaire/Primaire') }}, {{ __('Secondaire') }}
                            @elseif ($subject->is_prescolaire_primaire)
                                {{ __('Préscolaire/Primaire') }}
                            @elseif ($subject->is_secondaire)
                                {{ __('Secondaire') }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-stone-600">{{ $subject->domain?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-end whitespace-nowrap">
                            @can('update', $subject)
                                <button wire:click="edit({{ $subject->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $subject)
                                <button
                                    wire:click="delete({{ $subject->id }})"
                                    wire:confirm="{{ __('Supprimer cette matière ?') }}"
                                    class="ms-3 text-red-500 hover:text-red-700"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune matière.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
