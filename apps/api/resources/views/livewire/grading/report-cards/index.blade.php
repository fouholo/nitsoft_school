<div>
    @if ($isPrimaire)
        @livewire('grading.report-cards.primaire.index')
    @else
        @livewire('grading.report-cards.secondaire.index')
    @endif
</div>
