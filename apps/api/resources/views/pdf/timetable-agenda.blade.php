<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
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
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1e293b; }
        .subtitle { text-align: center; margin-bottom: 16px; font-weight: bold; font-size: 15px; text-decoration: underline; }
        table.agenda { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        table.agenda th, table.agenda td { border: 1px solid #cbd5e1; padding: 4px; text-align: left; vertical-align: top; }
        table.agenda th { background-color: #f1f5f9; }
        .entry { border-radius: 4px; padding: 3px 4px; margin-bottom: 4px; background-color: #fff7ed; }
        .entry.arabic { background-color: #ecfdf5; }
        .entry-time { font-size: 8px; color: #64748b; }
        .entry-subject { font-weight: bold; }
        .entry-subject.arabic-text { font-family: 'NotoNaskhArabic', "DejaVu Sans", sans-serif; direction: rtl; text-align: right; }
        .entry-detail { font-size: 8px; color: #475569; }
        .footer { margin-top: 30px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.reports-header', ['establishment' => $establishment, 'generalInformation' => $generalInformation])

    <p class="subtitle">{{ \Illuminate\Support\Str::upper($title) }}</p>

    <table class="agenda">
        <thead>
            <tr>
                @foreach ($agenda['days'] as $day)
                    <th>{{ $day->label() }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach ($agenda['days'] as $day)
                    <td>
                        @forelse ($agenda['entries'][$day->value] ?? [] as $entry)
                            <div class="entry {{ $entry['arabic'] ? 'arabic' : '' }}">
                                <div class="entry-time">{{ $entry['start'] }}–{{ $entry['end'] }}{{ $entry['arabic'] ? ' · '.__('Arabe') : '' }}</div>
                                @if (preg_match('/\p{Arabic}/u', $entry['subject']) === 1)
                                    {{-- pdf_rtl() : dompdf pose les mots arabes de gauche à droite. --}}
                                    <div class="entry-subject arabic-text">{{ pdf_rtl($entry['subject']) }}</div>
                                @else
                                    <div class="entry-subject">{{ $entry['subject'] }}</div>
                                @endif
                                <div class="entry-detail">{{ $entry['classroom'] }}</div>
                                @if ($entry['room'])
                                    <div class="entry-detail">{{ __('Salle') }} {{ $entry['room'] }}</div>
                                @endif
                            </div>
                        @empty
                            <span class="entry-detail">—</span>
                        @endforelse
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    @include('pdf.partials.director-stamp', ['establishment' => $establishment])

    <p class="footer">Généré le {{ now()->locale('fr')->translatedFormat('j F Y à H:i:s') }}</p>
</body>
</html>
