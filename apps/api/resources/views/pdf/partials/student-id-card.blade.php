@php
    /**
     * Une carte, dimensions ISO/IEC 7810 ID-1 (format carte de crédit) :
     * 85,6mm x 53,98mm. Réutilisée telle quelle pour une carte seule
     * (page à la taille de la carte) et pour une planche de classe
     * (grille sur A4) — voir student-id-card.blade.php et
     * classroom-id-cards.blade.php. Mise en page en tables (pas flex/float)
     * : c'est le seul modèle de layout fiable dans dompdf, même convention
     * que pdf/partials/reports-header.blade.php.
     *
     * L'identité visuelle vient entièrement de l'image de fond choisie par
     * un administrateur SaaS (Informations générales) — ce partial ne pose
     * que les informations, sans couleur ni décor propres.
     */
    $classroom = $classroom ?? null;
    $schoolYear = $schoolYear ?? null;
    $cardBackgroundPath = $cardBackgroundPath ?? null;
@endphp
<div class="id-card">
    @if ($cardBackgroundPath)
        <img src="{{ public_path('storage/'.$cardBackgroundPath) }}" class="id-card-bg">
    @endif

    <div class="id-card-content">
        <table class="id-card-heading" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <p class="id-card-establishment">{{ \Illuminate\Support\Str::upper($establishment->name) }}</p>
                    <p class="id-card-title">Carte d'identité scolaire</p>
                </td>
            </tr>
        </table>

        <table class="id-card-body" cellpadding="0" cellspacing="0">
            <tr>
                <td class="id-card-photo-cell">
                    <table class="id-card-photo" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>
                                @if ($student->photo_path)
                                    <img src="{{ public_path('storage/'.$student->photo_path) }}">
                                @else
                                    Photo
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="id-card-info-cell">
                    <table class="id-card-info" cellpadding="0" cellspacing="0">
                        <tr>
                            <td colspan="2" class="id-card-name">{{ \Illuminate\Support\Str::upper($student->last_name) }} {{ $student->first_name }}</td>
                        </tr>
                        <tr>
                            <td class="id-card-label">Matricule</td>
                            <td>{{ $student->student_number ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="id-card-label">Classe</td>
                            <td>{{ $classroom?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="id-card-label">Année</td>
                            <td>{{ $schoolYear?->label ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="id-card-label">Né(e) le</td>
                            <td>
                                {{ $student->birth_date?->format('d/m/Y') ?? '—' }}
                                @if ($student->birth_place)
                                    à {{ $student->birth_place }}
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="id-card-footer">
        {{ $establishment->address }}
        @if ($establishment->address && $establishment->phone) · @endif
        {{ $establishment->phone ? 'Tél. '.$establishment->phone : '' }}
    </div>
</div>
