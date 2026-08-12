<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 12px; }
        .header h1 { font-size: 16px; margin: 0 0 4px; }
        .header p { margin: 0; color: #64748b; }
        .divider { border: none; border-top: 3px solid #94a3b8; margin: 0 0 12px; }
        .receipt-number { text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 12px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.meta td { padding: 3px 0; font-weight: bold; }
        table.meta td.label { color: #64748b; width: 150px; font-weight: normal; }
        table.amount { width: 100%; border-collapse: collapse; }
        table.amount td { padding: 6px 8px; border: 1px solid #cbd5e1; }
        table.amount td.label { background-color: #f1f5f9; font-weight: bold; width: 150px; }
        table.amount td.value { font-size: 16px; font-weight: bold; }
        table.summary { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.summary td { padding: 3px 0; font-weight: bold; }
        table.summary td.label { color: #64748b; width: 150px; font-weight: normal; }
        table.stamp { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.stamp td.box { width: 45%; text-align: center; }
        .stamp-box { border: 1px dashed #cbd5e1; height: 70px; padding-top: 26px; font-size: 10px; color: #94a3b8; }
        table.codes { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.codes td { width: 50%; text-align: center; vertical-align: bottom; }
        table.codes .code-label { margin: 4px 0 0; font-size: 9px; color: #94a3b8; }
        .footer { margin-top: 12px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @if ($payment->establishment->logo_path)
            <img src="{{ public_path('storage/'.$payment->establishment->logo_path) }}" style="height: 60px; margin-bottom: 8px;">
        @endif
        <h1>{{ $payment->establishment->name }}</h1>
        <p>Reçu de paiement</p>
    </div>

    <hr class="divider">

    <p class="receipt-number">N&deg; {{ $payment->receiptNumber() }}</p>

    <table class="meta">
        <tr>
            <td class="label">Élève</td>
            <td>{{ $payment->student->last_name }} {{ $payment->student->first_name }}</td>
        </tr>
        <tr>
            <td class="label">Date de paiement</td>
            <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Facture</td>
            <td>{{ $payment->invoice->label }}</td>
        </tr>
        <tr>
            <td class="label">Moyen de paiement</td>
            <td>{{ $payment->method }}</td>
        </tr>
        <tr>
            <td class="label">Référence</td>
            <td>{{ $payment->reference ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Encaissé par</td>
            <td>{{ $payment->receivedBy?->name }}</td>
        </tr>
    </table>

    <table class="amount">
        <tr>
            <td class="label">Montant reçu</td>
            <td class="value">{{ money((float) $payment->amount) }}</td>
        </tr>
    </table>

    <hr class="divider" style="margin-top: 12px;">

    <table class="summary">
        <tr>
            <td class="label">Total scolarité</td>
            <td>{{ money($totalTuition) }}</td>
        </tr>
        <tr>
            <td class="label">Total versement</td>
            <td>{{ money($totalPayments) }}</td>
        </tr>
        <tr>
            <td class="label">Reste scolarité</td>
            <td>{{ money($totalTuition - $totalPayments) }}</td>
        </tr>
        @if ($nextPaymentDueDate)
            <tr>
                <td class="label">Date du prochain paiement</td>
                <td>{{ $nextPaymentDueDate->format('d/m/Y') }}</td>
            </tr>
        @endif
    </table>

    <hr class="divider" style="margin-top: 12px;">

    <table class="stamp">
        <tr>
            <td class="box" style="width: 55%;"></td>
            <td class="box">
                <div class="stamp-box">Cachet de l'établissement</div>
            </td>
        </tr>
    </table>

    <table class="codes">
        <tr>
            <td>
                @if ($payment->uid_serveur)
                    <img src="{{ barcode_data_uri($payment->uid_serveur) }}">
                @endif
            </td>
            <td>
                <img src="{{ qr_code_data_uri($payment->uid_local) }}" style="width: 53px; height: 53px;">
            </td>
        </tr>
        <tr>
            <td>
                @if ($payment->uid_serveur)
                    <p class="code-label">{{ $payment->uid_serveur }}</p>
                @endif
            </td>
            <td></td>
        </tr>
    </table>

    <p class="footer">Document généré automatiquement — {{ config('app.name') }}</p>
</body>
</html>
