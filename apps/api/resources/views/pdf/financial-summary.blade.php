<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bilan financier</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
        .subtitle { text-align: center; margin-bottom: 4px; font-weight: bold; font-size: 18px; text-decoration: underline; }
        .period-subtitle { text-align: center; margin-bottom: 20px; font-size: 12px; color: #475569; font-style: italic; }
        .establishment-heading { font-weight: bold; font-size: 14px; margin: 16px 0 6px; }
        table.summary { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.summary th, table.summary td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.summary th { background-color: #f1f5f9; }
        table.summary td.amount, table.summary th.amount { text-align: right; }
        table.summary tr.role-row td { font-weight: bold; background-color: #f8fafc; }
        table.summary tr.user-row td:first-child { padding-left: 20px; color: #475569; }
        table.summary tr.total-row td { font-weight: bold; background-color: #f1f5f9; }
        table.summary tr.grand-total-row td { font-weight: bold; background-color: #e2e8f0; }
        .empty-state { text-align: center; color: #64748b; margin: 20px 0; }
        .empty-establishment { color: #64748b; margin: 0 0 10px; font-size: 11px; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.reports-header', ['establishment' => $establishment, 'generalInformation' => $generalInformation])

    <p class="subtitle">BILAN FINANCIER</p>
    <p class="period-subtitle">Période : {{ $periodLabel }}</p>

    @if ($isMultiSchoolFounder)
        @foreach ($establishmentGroups as $establishmentGroup)
            <p class="establishment-heading">{{ $establishmentGroup['establishmentName'] }}</p>

            @if (count($establishmentGroup['groups']) === 0)
                <p class="empty-establishment">Aucun encaissement ni dépense enregistré pour cette école sur cette période.</p>
            @else
                @include('pdf.partials.financial-summary-table', [
                    'groups' => $establishmentGroup['groups'],
                    'totalCollected' => $establishmentGroup['collected'],
                    'totalSpent' => $establishmentGroup['spent'],
                    'totalNet' => $establishmentGroup['net'],
                    'totalLabel' => 'Total école',
                ])
            @endif
        @endforeach

        <table class="summary">
            <tbody>
                <tr class="grand-total-row">
                    <td>Total général ({{ count($establishmentGroups) }} {{ count($establishmentGroups) > 1 ? 'écoles' : 'école' }})</td>
                    <td class="amount">{{ money($grandTotalCollected) }}</td>
                    <td class="amount">{{ money($grandTotalSpent) }}</td>
                    <td class="amount">{{ money($grandTotalNet) }}</td>
                </tr>
            </tbody>
        </table>
    @elseif (count($groups) === 0)
        <p class="empty-state">Aucun encaissement ni dépense enregistré sur cette période.</p>
    @else
        @include('pdf.partials.financial-summary-table', [
            'groups' => $groups,
            'totalCollected' => $totalCollected,
            'totalSpent' => $totalSpent,
            'totalNet' => $totalNet,
            'totalLabel' => 'Total général',
        ])
    @endif

    <p class="footer">Généré le {{ now()->locale('fr')->translatedFormat('j F Y à H:i:s') }}</p>
</body>
</html>
