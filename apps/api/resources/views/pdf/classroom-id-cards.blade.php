<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cartes d'identité scolaires — {{ $classroom->name }}</title>
    @include('pdf.partials.student-id-card-style')
    <style>
        body { font-family: "DejaVu Sans", sans-serif; margin: 0; padding: 10mm; }
        table.cards-grid { border-collapse: collapse; }
        table.cards-grid td.card-cell {
            padding: 0 4mm 4mm 0;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    @if ($students->isEmpty())
        <p>Aucun élève inscrit dans cette classe.</p>
    @else
        <table class="cards-grid" cellpadding="0" cellspacing="0">
            @foreach ($students->chunk(2) as $pair)
                <tr>
                    @foreach ($pair as $student)
                        <td class="card-cell">
                            @include('pdf.partials.student-id-card', [
                                'student' => $student,
                                'establishment' => $classroom->establishment,
                                'classroom' => $classroom,
                                'schoolYear' => $schoolYear,
                            ])
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
