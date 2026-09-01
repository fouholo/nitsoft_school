<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Sauvegarde') }}</h1>
    <p class="mt-1 text-sm text-stone-600">
        {{ __('Exporter, vider ou restaurer les données métier de toute la plateforme (:count tables).', ['count' => count($tables)]) }}
    </p>

    {{-- Export --}}
    <div class="mt-6 rounded-lg border border-stone-200 bg-white p-4">
        <h2 class="text-lg font-semibold text-stone-900">{{ __('Exporter') }}</h2>
        <p class="mt-1 text-sm text-stone-600">
            {{ __('Génère une archive .zip contenant un fichier Excel par table.') }}
        </p>
        <a
            href="{{ route('backup.export') }}"
            class="mt-3 inline-block rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
        >
            {{ __('Télécharger l’archive') }}
        </a>
    </div>

    @if ($canWipe)
        {{-- Vider --}}
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
            <h2 class="text-lg font-semibold text-red-900">{{ __('Vider') }}</h2>
            <p class="mt-1 text-sm text-red-800">
                {{ __('Supprime définitivement les données des tables sélectionnées. Action irréversible.') }}
            </p>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-stone-700">{{ __('Périmètre') }}</label>
                    <select wire:model.live="wipeScope" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        <option value="all">{{ __('Toutes les tables') }}</option>
                        <option value="table">{{ __('Une table précise') }}</option>
                    </select>
                </div>

                @if ($wipeScope === 'table')
                    <div>
                        <label class="block text-sm font-medium text-stone-700">{{ __('Table') }}</label>
                        <select wire:model="wipeTable" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            <option value="">—</option>
                            @foreach ($tables as $table)
                                <option value="{{ $table }}">{{ $table }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-stone-700">
                        {{ __('Tapez :word pour confirmer', ['word' => 'VIDER']) }}
                    </label>
                    <input type="text" wire:model.live="wipeConfirmationWord" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @error('wipeConfirmationWord') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <button
                type="button"
                wire:click="wipe"
                wire:loading.attr="disabled"
                @disabled($wipeConfirmationWord !== 'VIDER')
                class="mt-3 rounded-lg bg-red-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ __('Vider') }}
            </button>

            @if ($lastWipeResult !== null)
                <div class="mt-3 rounded-lg bg-white p-3 text-sm text-stone-700">
                    @foreach ($lastWipeResult as $table => $rowsDeleted)
                        <p>{{ $table }} : {{ __(':count ligne(s) supprimée(s)', ['count' => $rowsDeleted]) }}</p>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Restaurer --}}
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
            <h2 class="text-lg font-semibold text-red-900">{{ __('Restaurer') }}</h2>
            <p class="mt-1 text-sm text-red-800">
                {{ __('Restaure les données depuis une archive de sauvegarde. Refuse d’écrire dans une table déjà non vide.') }}
            </p>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">{{ __('Archive (.zip)') }}</label>
                    <input type="file" wire:model="archive" class="mt-1 block w-full text-sm">
                    @error('archive') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-stone-700">{{ __('Périmètre') }}</label>
                    <select wire:model.live="importScope" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        <option value="all">{{ __('Toutes les tables') }}</option>
                        <option value="table">{{ __('Une table précise') }}</option>
                    </select>
                </div>

                @if ($importScope === 'table')
                    <div>
                        <label class="block text-sm font-medium text-stone-700">{{ __('Table') }}</label>
                        <select wire:model="importTable" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            <option value="">—</option>
                            @foreach ($tables as $table)
                                <option value="{{ $table }}">{{ $table }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-stone-700">
                        {{ __('Tapez :word pour confirmer', ['word' => 'RESTAURER']) }}
                    </label>
                    <input type="text" wire:model.live="importConfirmationWord" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @error('importConfirmationWord') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <button
                type="button"
                wire:click="import"
                wire:loading.attr="disabled"
                @disabled($importConfirmationWord !== 'RESTAURER')
                class="mt-3 rounded-lg bg-red-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ __('Restaurer') }}
            </button>

            @if ($lastImportResult !== null)
                <div class="mt-3 rounded-lg bg-white p-3 text-sm text-stone-700">
                    @foreach ($lastImportResult['tables'] as $table => $status)
                        <p>{{ $table }} : {{ $status['status'] }} ({{ __(':count ligne(s)', ['count' => $status['rows'] ?? 0]) }})</p>
                    @endforeach
                    @if ($lastImportResult['orphans'] !== [])
                        <p class="mt-2 text-amber-700">
                            {{ __('Fichiers ignorés (aucune table correspondante) : :files', ['files' => implode(', ', $lastImportResult['orphans'])]) }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
