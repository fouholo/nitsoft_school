<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>جدول الحصص</title>
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
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1e293b; }
        .arabic { font-family: 'NotoNaskhArabic', "DejaVu Sans", sans-serif; direction: rtl; }
        .subtitle { text-align: center; margin: 0 0 2px; font-weight: bold; font-size: 16px; text-decoration: underline; }
        .classroom-name { text-align: center; margin: 0 0 16px; font-size: 10px; color: #475569; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-family: 'NotoNaskhArabic', "DejaVu Sans", sans-serif; }
        table.grid th, table.grid td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: right; vertical-align: top; direction: rtl; }
        table.grid th { background-color: #f1f5f9; text-align: center; }
        .slot-label { font-weight: bold; }
        .slot-time { font-size: 9px; color: #64748b; }
        .session-subject { font-weight: bold; }
        .session-detail { font-size: 9px; color: #475569; }
        table.grid td.break { background-color: #f8fafc; text-align: center; font-size: 9px; color: #94a3b8; }
        .footer { margin-top: 30px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.reports-header', ['establishment' => $establishment, 'generalInformation' => $generalInformation])

    {{-- pdf_rtl() : dompdf pose les mots arabes de gauche à droite (voir app/Support/helpers.php). --}}
    <p class="subtitle arabic">{{ pdf_rtl('جدول الحصص — '.trim($arabicClassroom->arabicLevel?->wording.' '.$arabicClassroom->arabicSerie?->serie_wording.' '.$arabicClassroom->numero)) }}</p>
    <p class="classroom-name">{{ $arabicClassroom->name }}{{ $arabicClassroom->schoolYear ? ' — '.$arabicClassroom->schoolYear->label : '' }}</p>

    {{-- dompdf ignore direction: rtl pour l'ordre des colonnes : elles sont
         écrites de gauche à droite dans l'ordre visuel, le dernier jour à
         gauche et la colonne des créneaux à droite. --}}
    @php $visualDays = array_reverse($days); @endphp
    <table class="grid">
        <thead>
            <tr>
                @foreach ($visualDays as $day)
                    <th>{{ $day->arabicLabel() }}</th>
                @endforeach
                <th>{{ pdf_rtl('الحصة') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($slots as $slot)
                <tr>
                    @if ($slot->is_break)
                        <td colspan="{{ count($days) }}" class="break">{{ pdf_rtl('استراحة') }}</td>
                    @else
                        @foreach ($visualDays as $day)
                            @php $session = $sessions->get($day->value.'-'.$slot->id) @endphp
                            <td>
                                @if ($session)
                                    <div class="session-subject">{{ pdf_rtl($session->arabicSubject?->name) }}</div>
                                    <div class="session-detail">{{ $session->teacher?->name }}</div>
                                    @if ($session->room)
                                        <div class="session-detail">{{ pdf_rtl('القاعة '.$session->room) }}</div>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    @endif
                    <td>
                        <div class="slot-label">{{ pdf_rtl($slot->label) }}</div>
                        <div class="slot-time">{{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($days) + 1 }}" class="break">{{ pdf_rtl('لا توجد حصص مسجلة') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="arabic">
        @include('pdf.partials.director-stamp', ['establishment' => $establishment, 'title' => 'المدير'])
    </div>

    <p class="footer arabic">{{ pdf_rtl('أنشئت في '.now()->format('d/m/Y H:i')) }}</p>
</body>
</html>
