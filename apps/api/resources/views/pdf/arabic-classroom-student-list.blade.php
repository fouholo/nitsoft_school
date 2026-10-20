<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>قائمة التلاميذ</title>
    <style>
        @font-face {
            font-family: 'NotoNaskhArabic';
            src: url('file://{{ $fontRegularPath }}') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'NotoNaskhArabic';
            src: url('file://{{ $fontBoldPath }}') format('truetype');
            font-weight: bold;
        }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
        .arabic { font-family: 'NotoNaskhArabic', "DejaVu Sans", sans-serif; direction: rtl; }
        .subtitle { text-align: center; margin: 0 0 2px; font-weight: bold; font-size: 18px; text-decoration: underline; }
        .classroom-name { text-align: center; margin: 0 0 4px; font-size: 11px; color: #475569; }
        .filters-subtitle { text-align: center; margin: 0 0 20px; font-size: 12px; color: #475569; }
        table.students { width: 100%; border-collapse: collapse; margin: 16px 0 20px; direction: rtl; font-family: 'NotoNaskhArabic', "DejaVu Sans", sans-serif; }
        table.students th, table.students td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: right; direction: rtl; }
        table.students th { background-color: #f1f5f9; text-align: center; }
        table.students .col-center { text-align: center; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.reports-header', ['establishment' => $arabicClassroom->establishment, 'generalInformation' => $generalInformation])

    {{-- pdf_rtl() : dompdf pose les mots arabes de gauche à droite (voir app/Support/helpers.php). --}}
    <p class="subtitle arabic">{{ pdf_rtl('قائمة تلاميذ القسم — '.trim($arabicClassroom->arabicLevel?->wording.' '.$arabicClassroom->arabicSerie?->serie_wording.' '.$arabicClassroom->numero)) }}</p>
    <p class="classroom-name">{{ $arabicClassroom->name }}{{ $arabicClassroom->schoolYear ? ' — '.$arabicClassroom->schoolYear->label : '' }}</p>
    @if ($filterLabels !== [])
        <p class="filters-subtitle arabic">{{ pdf_rtl(implode(' · ', $filterLabels)) }}</p>
    @endif

    {{-- dompdf ignore direction: rtl pour l'ordre des colonnes d'un tableau :
         elles sont écrites ici de gauche à droite, dans l'ordre visuel, pour que
         « الرقم » soit la première colonne à droite. --}}
    <table class="students">
        <thead>
            <tr>
                <th>{{ pdf_rtl('تاريخ ومكان الميلاد') }}</th>
                <th class="col-center">الجنس</th>
                <th>الاسم</th>
                <th>اللقب</th>
                <th>{{ pdf_rtl('رقم التسجيل') }}</th>
                <th class="col-center">الرقم</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                <tr>
                    <td>{{ $student->birth_date?->format('d/m/Y') }}{{ $student->birth_date && $student->birth_place ? ' — ' : '' }}{{ $student->birth_place }}</td>
                    <td class="col-center">{{ match ($student->gender) { 'm' => 'ذ', 'f' => 'أ', default => '' } }}</td>
                    <td>{{ $student->first_name }}</td>
                    <td>{{ $student->last_name }}</td>
                    <td>{{ $student->student_number }}</td>
                    <td class="col-center">{{ $loop->iteration }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="col-center">{{ pdf_rtl('لا يوجد تلاميذ مسجلون في هذا القسم') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="arabic">
        @include('pdf.partials.director-stamp', ['establishment' => $arabicClassroom->establishment, 'title' => 'المدير'])
    </div>

    <p class="footer arabic">{{ pdf_rtl('أنشئت في '.now()->format('d/m/Y H:i')) }}</p>
</body>
</html>
