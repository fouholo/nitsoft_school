<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulletin</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 16px; margin: 0 0 4px; }
        .header p { margin: 0; color: #64748b; }
        .meta { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .meta td { padding: 2px 0; }
        .meta td.label { color: #64748b; width: 120px; }
        table.grades { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.grades th, table.grades td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.grades th { background-color: #f1f5f9; }
        .summary { width: 100%; border-collapse: collapse; }
        .summary td { padding: 6px 8px; border: 1px solid #cbd5e1; }
        .summary td.label { background-color: #f1f5f9; font-weight: bold; width: 150px; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @if ($reportCard->establishment->logo_path)
            <img src="{{ public_path('storage/'.$reportCard->establishment->logo_path) }}" style="height: 60px; margin-bottom: 8px;">
        @endif
        <h1>{{ $reportCard->establishment->name }}</h1>
        <p>Bulletin — {{ $reportCard->term?->label ?? "Composition {$reportCard->composition_number}" }} ({{ ($reportCard->term?->schoolYear ?? $reportCard->schoolYear)?->label }})</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Élève</td>
            <td>{{ $reportCard->student->last_name }} {{ $reportCard->student->first_name }}</td>
            <td class="label">Matricule</td>
            <td>{{ $reportCard->student->student_number }}</td>
        </tr>
        <tr>
            <td class="label">Classe</td>
            <td>{{ $reportCard->classroom?->name }}</td>
            <td class="label">Date d'édition</td>
            <td>{{ now()->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="grades">
        <thead>
            <tr>
                <th>Matière</th>
                <th>Coef.</th>
                <th>Moyenne / 20</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($breakdown as $row)
                <tr>
                    <td>{{ $row->subject->name }}</td>
                    <td>{{ $row->coefficient !== null ? number_format($row->coefficient, 1) : '—' }}</td>
                    <td>{{ $row->average !== null ? number_format($row->average, 2) : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Aucune note disponible pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Moyenne générale</td>
            <td>{{ number_format((float) $reportCard->average, 2) }} / 20</td>
        </tr>
        <tr>
            <td class="label">Rang</td>
            <td>{{ $reportCard->rank }}</td>
        </tr>
    </table>

    @include('pdf.partials.director-stamp', ['establishment' => $reportCard->establishment])

    <p class="footer">Généré le {{ now()->locale('fr')->translatedFormat('j F Y à H:i:s') }}</p>
</body>
</html>
