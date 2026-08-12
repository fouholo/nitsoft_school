<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Liste des élèves</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
        .subtitle { text-align: center; margin-bottom: 20px; font-weight: bold; font-size: 18px; border: 1px solid #1e293b; padding: 8px; }
        table.students { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.students th, table.students td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.students th { background-color: #f1f5f9; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.reports-header', ['establishment' => $classroom->establishment, 'generalInformation' => $generalInformation])

    <p class="subtitle">{{ \Illuminate\Support\Str::upper('Liste des élèves — '.$classroom->name) }}</p>

    <table class="students">
        <thead>
            <tr>
                <th>N°</th>
                <th>Matricule</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Sexe</th>
                <th>Date de naissance</th>
                <th>Lieu de naissance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $student->student_number }}</td>
                    <td>{{ $student->last_name }}</td>
                    <td>{{ $student->first_name }}</td>
                    <td>{{ $student->gender }}</td>
                    <td>{{ $student->birth_date?->format('d/m/Y') }}</td>
                    <td>{{ $student->birth_place }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Aucun élève inscrit dans cette classe.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Document généré automatiquement — {{ config('app.name') }}</p>
</body>
</html>
