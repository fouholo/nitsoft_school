<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bilan financier</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
        .subtitle { text-align: center; margin-bottom: 4px; font-weight: bold; font-size: 18px; text-decoration: underline; }
        .period-subtitle { text-align: center; margin-bottom: 20px; font-size: 12px; color: #475569; font-style: italic; }
        table.summary { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.summary th, table.summary td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.summary th { background-color: #f1f5f9; }
        table.summary td.amount, table.summary th.amount { text-align: right; }
        table.summary tr.role-row td { font-weight: bold; background-color: #f8fafc; }
        table.summary tr.user-row td:first-child { padding-left: 20px; color: #475569; }
        table.summary tr.total-row td { font-weight: bold; background-color: #f1f5f9; }
        .empty-state { text-align: center; color: #64748b; margin: 20px 0; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.reports-header', ['establishment' => $establishment, 'generalInformation' => $generalInformation])

    <p class="subtitle">BILAN FINANCIER</p>
    <p class="period-subtitle">Période : {{ $periodLabel }}</p>

    @if (count($groups) === 0)
        <p class="empty-state">Aucun encaissement ni dépense enregistré sur cette période.</p>
    @else
        <table class="summary">
            <thead>
                <tr>
                    <th>Rôle / Utilisateur</th>
                    <th class="amount">Encaissé</th>
                    <th class="amount">Dépensé</th>
                    <th class="amount">Net</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $group)
                    <tr class="role-row">
                        <td>{{ $group['roleLabel'] }}</td>
                        <td class="amount">{{ money($group['collected']) }}</td>
                        <td class="amount">{{ money($group['spent']) }}</td>
                        <td class="amount">{{ money($group['net']) }}</td>
                    </tr>
                    @foreach ($group['rows'] as $row)
                        <tr class="user-row">
                            <td>{{ $row['user_name'] }}</td>
                            <td class="amount">{{ money($row['collected']) }}</td>
                            <td class="amount">{{ money($row['spent']) }}</td>
                            <td class="amount">{{ money($row['net']) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td>Total général</td>
                    <td class="amount">{{ money($totalCollected) }}</td>
                    <td class="amount">{{ money($totalSpent) }}</td>
                    <td class="amount">{{ money($totalNet) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <p class="footer">Généré le {{ now()->locale('fr')->translatedFormat('j F Y à H:i:s') }}</p>
</body>
</html>
