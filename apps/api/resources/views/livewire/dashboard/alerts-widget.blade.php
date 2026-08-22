<div class="mt-4 rounded-2xl border border-stone-200 bg-white p-5">
    <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Alertes') }}</p>

    @if (count($items) === 0)
        <p class="mt-3 text-sm text-stone-500">{{ __("Aucun point d'attention pour l'instant.") }}</p>
    @else
        <ul class="mt-3 space-y-2">
            @foreach ($items as $item)
                <li class="flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    <span>
                        {{ $item['label'] }}
                        @if ($showEstablishmentTag)
                            <span class="ms-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $item['establishmentName'] }}</span>
                        @endif
                    </span>

                    @if ($item['link'])
                        <a href="{{ $item['link'] }}" wire:navigate class="shrink-0 text-xs font-medium text-orange-700 hover:underline">
                            {{ __('Voir') }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
