@php $showRepublicColumn = $showRepublicColumn ?? true; @endphp
<table style="width:100%; border-collapse:collapse; margin-bottom:20px; table-layout:fixed;">
    <tr>
        <td style="width: {{ $showRepublicColumn ? '50%' : '100%' }}; vertical-align:top; text-align:center;">
            @php $leftLinePrinted = false; @endphp
            @if ($generalInformation->nom_ministere)
                @if ($leftLinePrinted)
                    <p style="margin:0;">----------------------</p>
                @endif
                <p style="margin:0; font-weight:bold;">{{ \Illuminate\Support\Str::upper($generalInformation->nom_ministere) }}</p>
                @php $leftLinePrinted = true; @endphp
            @endif
            @if ($establishment->inspection?->direction)
                @if ($leftLinePrinted)
                    <p style="margin:0;">----------------------</p>
                @endif
                <p style="margin:0;">DIRECTION REGIONALE {{ \Illuminate\Support\Str::upper($establishment->inspection->direction->direction_name) }}</p>
                @php $leftLinePrinted = true; @endphp
            @endif
            @if ($establishment->type === \App\Domain\Establishments\Enums\EstablishmentType::PrescolairePrimaire && $establishment->inspection)
                @if ($leftLinePrinted)
                    <p style="margin:0;">----------------------</p>
                @endif
                <p style="margin:0;">INSPECTION DE L'ENSEIGNEMENT PRESCOLAIRE ET PRIMAIRE {{ \Illuminate\Support\Str::upper($establishment->inspection->inspection_name) }}</p>
                @php $leftLinePrinted = true; @endphp
            @endif
            @if ($leftLinePrinted)
                <p style="margin:0;">----------------------</p>
            @endif
            <p style="margin:4px 0 0; font-weight:bold;">{{ $establishment->name }}</p>
            @if ($establishment->logo_path)
                <img src="{{ public_path('storage/'.$establishment->logo_path) }}" style="height: 50px; margin-top: 4px;">
            @endif
        </td>
        @if ($showRepublicColumn)
            <td style="width:10%;"></td>
            <td style="width:40%; vertical-align:top; text-align:center;">
                <p style="margin:0; font-weight:bold;">REPUBLIQUE DE CÔTE D'IVOIRE</p>
                <p style="margin:0;">Union-Discipline-Travail</p>
                @if ($generalInformation->armoirie_path)
                    <img src="{{ public_path('storage/'.$generalInformation->armoirie_path) }}" style="height: 75px; margin-top: 4px;">
                @endif
                @if ($generalInformation->annee_scolaire_courante)
                    <p style="margin:4px 0 0;">Année scolaire : <span style="font-weight:bold;">{{ $generalInformation->annee_scolaire_courante }}</span></p>
                @endif
            </td>
        @endif
    </tr>
</table>
