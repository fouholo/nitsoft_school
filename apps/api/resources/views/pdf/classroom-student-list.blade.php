<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Liste des élèves</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
        .subtitle { text-align: center; margin-bottom: 20px; font-weight: bold; font-size: 18px; text-decoration: underline; }
        table.students { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.students th, table.students td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.students th { background-color: #f1f5f9; }
        table.students .col-center { text-align: center; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.reports-header', ['establishment' => $classroom->establishment, 'generalInformation' => $generalInformation])

    <p class="subtitle">{{ \Illuminate\Support\Str::upper('Liste des élèves — '.$classroom->name) }}</p>

    <table class="students">
        <thead>
            <tr>
                <th class="col-center">N°</th>
                <th>Matricule</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th class="col-center">Sexe</th>
                <th>Date et lieu de naissance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                <tr>
                    <td class="col-center">{{ $loop->iteration }}</td>
                    <td>{{ $student->student_number }}</td>
                    <td>{{ $student->last_name }}</td>
                    <td>{{ $student->first_name }}</td>
                    <td class="col-center">{{ \Illuminate\Support\Str::upper($student->gender) }}</td>
                    <td>{{ $student->birth_date?->format('d/m/Y') }}{{ $student->birth_date && $student->birth_place ? ' à ' : '' }}{{ $student->birth_place }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Aucun élève inscrit dans cette classe.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Généré le {{ now()->locale('fr')->translatedFormat('j F Y à H:i:s') }}</p>
</body>
</html>
