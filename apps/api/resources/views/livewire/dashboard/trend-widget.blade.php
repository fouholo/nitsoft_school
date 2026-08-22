<div class="mt-4 rounded-2xl border border-stone-200 bg-white p-5">
    <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Tendance des encaissements') }}</p>

    @if ($noEstablishmentSelected)
        <p class="mt-3 text-sm text-stone-500">{{ __('Sélectionnez au moins une école.') }}</p>
    @else
        <div class="mt-3 flex items-baseline gap-3">
            <p class="text-xl font-semibold text-stone-900">{{ money($currentCollected) }}</p>
            <p class="text-xs text-stone-500">{{ __('ce mois-ci') }}</p>

            @if ($deltaPercent !== null)
                <span class="ms-auto text-sm font-medium {{ $deltaAmount >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                    {{ $deltaAmount >= 0 ? '↑' : '↓' }} {{ number_format(abs($deltaPercent), 1) }}%
                </span>
            @elseif ($deltaAmount != 0)
                <span class="ms-auto text-sm font-medium {{ $deltaAmount >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                    {{ $deltaAmount >= 0 ? '+' : '' }}{{ money($deltaAmount) }}
                </span>
            @endif
        </div>
        <p class="mt-1 text-xs text-stone-500">{{ __(':amount le mois précédent', ['amount' => money($previousCollected)]) }}</p>
    @endif
</div>
