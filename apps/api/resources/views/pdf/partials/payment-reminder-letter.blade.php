@php
    /**
     * Contenu d'une lettre de relance (en-tête officiel + corps + tableau du
     * solde) — réutilisé tel quel par la lettre isolée et la planche
     * groupée, pour les deux types de relance ($reminderType : "late" =
     * solde en retard, "upcoming" = prochaine échéance non encore soldée,
     * $nextInstallment requis dans ce second cas). Texte fixe, non
     * configurable — voir
     * docs/superpowers/specs/2026-08-21-lettre-relance-paiement-design.md.
     */
    $reminderType = $reminderType ?? 'late';
    $nextInstallment = $nextInstallment ?? null;

    $primaryGuardian = $student->guardians()
        ->wherePivot('is_primary_contact', true)
        ->wherePivot('status', \App\Domain\Enrollment\Enums\GuardianLinkStatus::Approved)
        ->first();

    $studentFullName = trim("{$student->first_name} {$student->last_name}");

    $recipient = $primaryGuardian && trim("{$primaryGuardian->first_name} {$primaryGuardian->last_name}") !== ''
        ? 'À l\'attention de '.trim("{$primaryGuardian->first_name} {$primaryGuardian->last_name}")
        : "À l'attention du tuteur/tutrice de {$studentFullName}";
@endphp
<div class="reminder-letter">
    @include('pdf.partials.reports-header', ['establishment' => $establishment, 'generalInformation' => $generalInformation])

    <p class="reminder-date">{{ $establishment->address ?? '' }}{{ $establishment->address ? ', ' : '' }}le {{ now()->locale('fr')->translatedFormat('j F Y') }}</p>

    <p class="reminder-recipient">{{ $recipient }}</p>

    <div class="reminder-body">
        @if ($reminderType === 'upcoming' && $nextInstallment)
            <p>
                Nous portons à votre connaissance que la tranche de scolarité « {{ $nextInstallment->label }} », à échéance le {{ $nextInstallment->due_date->format('d/m/Y') }},
                n'est pas encore soldée pour <strong>{{ \Illuminate\Support\Str::upper($student->last_name) }} {{ $student->first_name }}</strong>,
                élève en classe de {{ $classroom?->name ?? '—' }} au titre de l'année scolaire {{ $schoolYear?->label ?? '—' }}. Le détail de la situation est précisé ci-dessous.
            </p>
            <p>
                Nous vous invitons à procéder au règlement avant cette date.
            </p>
        @else
            <p>
                Nous portons à votre connaissance que la scolarité de <strong>{{ \Illuminate\Support\Str::upper($student->last_name) }} {{ $student->first_name }}</strong>,
                élève en classe de {{ $classroom?->name ?? '—' }} au titre de l'année scolaire {{ $schoolYear?->label ?? '—' }},
                présente à ce jour un solde impayé détaillé ci-dessous.
            </p>
            <p>
                Nous vous prions de bien vouloir procéder à la régularisation de ce solde dans les meilleurs délais.
            </p>
        @endif
    </div>

    <table class="reminder-balance" cellpadding="0" cellspacing="0">
        <tr>
            <th>Libellé</th>
            <th>Échéance</th>
            <th class="amount">Dû</th>
            <th class="amount">Payé</th>
            <th class="amount">Reste</th>
        </tr>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ $row['due_date']?->format('d/m/Y') ?? '—' }}</td>
                <td class="amount">{{ money($row['due']) }}</td>
                <td class="amount">{{ money($row['paid']) }}</td>
                <td class="amount">{{ money($row['remaining']) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="4">Total restant dû</td>
            <td class="amount">{{ money($total) }}</td>
        </tr>
    </table>

    <p class="reminder-closing">
        Nous vous remercions par avance de l'attention portée à ce courrier et vous prions d'agréer, Madame, Monsieur, l'expression de nos salutations distinguées.
    </p>

    <p class="reminder-signature">Le Directeur / La Directrice</p>
</div>
