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
        table.grades th { background-color: #f1f5f9; text-align: center; }
        table.grades th.col-narrow, table.grades td.col-narrow { width: 35px; text-align: center; }
        table.grades th.col-disciplines, table.grades td.col-disciplines { width: 190px; }
        table.grades th.col-appreciation, table.grades td.col-appreciation { width: 90px; }
        table.grades td.col-rang { text-align: center; }
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
                <th class="col-disciplines">Disciplines</th>
                <th class="col-narrow">Moy. /20</th>
                <th class="col-narrow">Coéf.</th>
                <th class="col-narrow">Moy. coéf.</th>
                <th>Rang</th>
                <th class="col-appreciation">Appréciation</th>
                <th>Nom et signature du prof</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($breakdown as $row)
                <tr>
                    <td class="col-disciplines">{{ $row->subject->name }}</td>
                    <td class="col-narrow">{{ $row->average !== null ? number_format($row->average, 2) : '—' }}</td>
                    <td class="col-narrow">{{ $row->coefficient !== null ? number_format($row->coefficient, 1) : '—' }}</td>
                    <td class="col-narrow">{{ $row->coefficientWeightedAverage() !== null ? number_format($row->coefficientWeightedAverage(), 2) : '—' }}</td>
                    <td class="col-rang">{{ $row->rank ?? '—' }}</td>
                    <td class="col-appreciation">{{ $row->appreciation ?? '—' }}</td>
                    <td>{{ $row->teacherName ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Aucune note disponible pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Moyenne générale</td>
            <td>{{ number_format((float) $reportCard->average, 2) }} / {{ (int) $reportCard->classroom->level->compositionAverageScale() }}</td>
        </tr>
        <tr>
            <td class="label">Rang</td>
            <td>{{ $reportCard->rank }}</td>
        </tr>
        @if ($reportCard->appreciation)
            <tr>
                <td class="label">Appréciation</td>
                <td>{{ $reportCard->appreciation }}</td>
            </tr>
        @endif
    </table>

    @include('pdf.partials.director-stamp', ['establishment' => $reportCard->establishment])

    <p class="footer">Généré le {{ now()->locale('fr')->translatedFormat('j F Y à H:i:s') }}</p>
</body>
</html>
