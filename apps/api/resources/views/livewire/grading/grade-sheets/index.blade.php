<div>
    @if ($isPrimaire)
        @livewire('grading.grade-sheets.primaire.index')
    @else
        @livewire('grading.grade-sheets.secondaire.index')
    @endif
</div>
