<div class="mt-6 rounded-2xl border border-stone-200 bg-white p-5">
    <div class="flex items-center justify-between">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">Santé financière</p>
        @if ($schoolYear)
            <span class="text-xs text-stone-400">Année {{ $schoolYear->label }}</span>
        @endif
        <a href="{{ route('billing.financial-summary.index') }}" wire:navigate class="text-xs font-medium text-orange-700 hover:underline">
            Voir le bilan complet
        </a>
    </div>

    @if ($noEstablishmentSelected)
        <p class="mt-3 text-sm text-stone-500">Sélectionnez au moins une école.</p>
    @else
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <p class="text-xl font-semibold text-stone-900">{{ money($totals['collected']) }}</p>
                <p class="text-xs text-stone-500">Encaissé</p>
            </div>
            <div>
                <p class="text-xl font-semibold text-stone-900">{{ money($totals['spent']) }}</p>
                <p class="text-xs text-stone-500">Dépensé</p>
            </div>
            <div>
                <p class="text-xl font-semibold {{ $totals['net'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ money($totals['net']) }}</p>
                <p class="text-xs text-stone-500">Net</p>
            </div>
        </div>
    @endif
</div>
