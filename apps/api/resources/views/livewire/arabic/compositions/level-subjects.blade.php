<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Matières du primaire') }}</h1>
    <p class="mt-1 text-sm text-stone-500">{{ __('Les matières de chaque niveau arabe, dans l’ordre du relevé de notes.') }}</p>

    <div class="mt-4 max-w-sm">
        <label for="arabic_level_id" class="block text-sm font-medium text-stone-700">{{ __('Niveau arabe') }}</label>
        <select id="arabic_level_id" wire:model.live="arabic_level_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @foreach ($arabicLevels as $arabicLevel)
                <option value="{{ $arabicLevel->id }}">{{ $arabicLevel->wording }}{{ $arabicLevel->wording_fr ? ' — '.$arabicLevel->wording_fr : '' }}</option>
            @endforeach
        </select>
    </div>

    @can('manage', \App\Domain\Arabic\Models\ArabicLevelSubject::class)
        @php $canManage = true; @endphp
    @else
        @php $canManage = false; @endphp
    @endcan

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Matières du niveau') }}</h2>
            <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
                <ul class="divide-y divide-stone-100">
                    @forelse ($levelSubjects as $levelSubject)
                        <li wire:key="level-subject-{{ $levelSubject->id }}" class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                            <span>
                                <span class="tabular-nums text-stone-400">{{ $loop->iteration }}.</span>
                                <span class="ms-1 text-stone-900" dir="auto">{{ $levelSubject->arabicSubject?->name }}</span>
                                @if ($levelSubject->arabicSubject?->name_fr)
                                    <span class="text-stone-500">— {{ $levelSubject->arabicSubject->name_fr }}</span>
                                @endif
                            </span>
                            @if ($canManage)
                                <span class="flex shrink-0 items-center gap-1">
                                    <button type="button" wire:click="move({{ $levelSubject->id }}, -1)" @disabled($loop->first) aria-label="{{ __('Monter') }}" class="rounded px-2 py-1 text-stone-500 hover:bg-stone-100 disabled:opacity-30">↑</button>
                                    <button type="button" wire:click="move({{ $levelSubject->id }}, 1)" @disabled($loop->last) aria-label="{{ __('Descendre') }}" class="rounded px-2 py-1 text-stone-500 hover:bg-stone-100 disabled:opacity-30">↓</button>
                                    <button type="button" wire:click="remove({{ $levelSubject->id }})" class="ms-2 text-red-600 hover:text-red-800">{{ __('Retirer') }}</button>
                                </span>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-sm text-stone-500">{{ __('Aucune matière pour ce niveau.') }}</li>
                    @endforelse
                </ul>
            </div>
        </section>

        @if ($canManage)
            <section>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Matières du catalogue') }}</h2>
                <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
                    <ul class="divide-y divide-stone-100">
                        @forelse ($availableSubjects as $availableSubject)
                            <li wire:key="available-subject-{{ $availableSubject->id }}" class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                                <span>
                                    <span class="text-stone-900" dir="auto">{{ $availableSubject->name }}</span>
                                    @if ($availableSubject->name_fr)
                                        <span class="text-stone-500">— {{ $availableSubject->name_fr }}</span>
                                    @endif
                                </span>
                                <button type="button" wire:click="add({{ $availableSubject->id }})" class="shrink-0 text-orange-700 hover:underline">{{ __('Ajouter') }}</button>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center text-sm text-stone-500">{{ __('Toutes les matières du catalogue sont déjà ajoutées.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        @endif
    </div>
</div>
