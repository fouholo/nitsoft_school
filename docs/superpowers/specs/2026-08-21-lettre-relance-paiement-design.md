# Lettre de relance de paiement (document imprimable)

*(Design validé par l'utilisateur le 2026-08-21.)*

## Contexte

L'utilisateur souhaite une "demande de relance" pour les frais de scolarité impayés. Deux canaux sont envisagés à terme (SMS et document imprimable) ; ce chantier ne couvre que le document imprimable — le SMS (`invoice_reminder`, code déjà anticipé dans `sms_templates`) sera traité séparément.

Le système de facturation a été refondu (`2026-08-18-refonte-factures-scolarite-design.md`) : il n'existe plus de modèle `Invoice`. Les montants dus sont portés directement par `Enrollment` (`registration_amount`, `installment_1_amount`…`installment_7_amount`, `total_paid`), et `Enrollment::tuitionInstallmentsWithStatus()` calcule déjà, par tranche, `{position, amount, due_date, status, paid}` avec `status` parmi `paid`/`partial_late`/`partial_upcoming`/`late`/`due` (imputation cumulative : inscription d'abord, puis tranches par échéance croissante). `Enrollment::registrationAmountPaid()` renvoie la part des versements imputée aux frais d'inscription.

`PaymentTrackingService::balances(int $schoolYearId, ?int $ownerId, ?int $studentId)` reste la source de vérité pour "qui est en retard" (`balance > 0`), déjà utilisée par l'écran "Suivi des paiements".

## 1. Contenu du document

Une page A5 portrait par élève (`setPaper('a5', 'portrait')`), dompdf, dans le même esprit que les autres PDF du module (`pdf/partials/reports-header.blade.php` déjà utilisé par la liste des élèves de classe) :

1. En-tête officiel : `@include('pdf.partials.reports-header', ['establishment' => ..., 'generalInformation' => GeneralInformation::current()])` — inchangé, pas de nouveau champ de configuration.
2. Date du jour.
3. Destinataire : tuteur principal approuvé (`$student->guardians()->wherePivot('is_primary_contact', true)->wherePivot('status', GuardianLinkStatus::Approved)->first()`) — "À l'attention de [Prénom Nom]" si trouvé et nommé, sinon "À l'attention du tuteur/tutrice de [Prénom Nom élève]".
4. Corps de lettre — texte fixe (non configurable), mentionnant nom de l'élève, classe, année scolaire, invitant à régulariser le solde ci-dessous.
5. Tableau du solde — une ligne par poste **non soldé** :
   - "Frais d'inscription" si `registration_amount > 0` et `registrationAmountPaid() < registration_amount` — dû = `registration_amount`, payé = `registrationAmountPaid()`, reste = différence.
   - Une ligne par tranche de `tuitionInstallmentsWithStatus()` dont `status !== 'paid'` — libellé = `Installment::label` (à joindre : `tuitionInstallmentsWithStatus()` ne renvoie que `position`/`amount`/`due_date`/`status`/`paid`, le contrôleur complète le libellé via `Installment::where('school_year_id', ...)->pluck('label', 'position')`), échéance, dû, payé, reste.
   - Ligne total (somme des restes).
6. Formule de politesse + "Le Directeur / La Directrice" en texte (pas de signature scannée — rien de tel n'existe ailleurs dans l'app).

## 2. Déclenchement

### 2.1 Suivi des paiements (individuel)

`resources/views/livewire/billing/payment-tracking/index.blade.php` : à côté du bouton "Encaisser" existant (visible si `$canRecordPayments`), un lien "Relance" visible quand `$row['balance'] > 0`, `href="{{ route('reports.payment-reminder-pdf', $row['student']) }}"` ouvert dans un nouvel onglet (`target="_blank"`).

### 2.2 Listes/Rapports (groupé)

`app/Livewire/Reports/Index.php` : nouveau bloc "Lettres de relance" — un filtre `reminderLevelFilter` (select, "Tous les niveaux" par défaut, même source que `PaymentTracking\Index::$levels`), un bouton "Générer les lettres de relance" → `route('reports.payment-reminders-pdf', ['level_id' => $this->reminderLevelFilter])`.

## 3. Contrôleurs et routes

`routes/reports.php` :

```php
Route::get('/eleves/{student}/relance', PaymentReminderPdfController::class)->name('payment-reminder-pdf');
Route::get('/relances', PaymentRemindersBatchPdfController::class)->name('payment-reminders-pdf');
```

Nouveau namespace `App\Http\Controllers\Billing\` (domaine facturation, pas académique) :

- **`PaymentReminderPdfController`** (`__invoke(Request $request, Student $student)`) : `Gate::authorize('view', $student)` ; résout l'année scolaire courante et l'inscription active ; calcule le solde via `app(PaymentTrackingService::class)->balanceForStudent($student->id, $schoolYearId)` ; `abort_if(($balance['balance'] ?? 0) <= 0, 404)` (pas de lettre pour un solde nul/négatif, y compris en accès direct par URL) ; rend `pdf.payment-reminder`.
- **`PaymentRemindersBatchPdfController`** (`__invoke(Request $request)`) : `Gate::authorize('viewAny', Payment::class)` ; récupère les soldes en retard via `PaymentTrackingService::balances()` sur l'année scolaire courante, filtrés par `level_id` si fourni (même logique que `PaymentTracking\Index::render()` — passer par les inscriptions actives de la classe du niveau) ; une page par élève, séparées par `page-break-after: always` (sauf la dernière) ; si aucun élève en retard dans le périmètre, affiche un message ("Aucun élève en retard sur ce périmètre.") plutôt qu'un PDF vide silencieux.

Les deux vues (`pdf/payment-reminder.blade.php` pour la lettre seule, `pdf/payment-reminders-batch.blade.php` qui inclut la même en boucle) partagent un partial `pdf/partials/payment-reminder-letter.blade.php` (contenu de la section 1, hors `<html>`/`@page`), sur le modèle du partial de carte d'identité.

## 4. Tests

`tests/Feature/Http/PaymentReminderPdfTest.php` (nouveau) :
- Accès en ligne / tenant isolation, sur le modèle de `StudentIdCardPdfTest`.
- 404 si le solde n'est pas positif.
- Contenu : nom élève, classe, année, montant du solde, ligne "Frais d'inscription" absente si déjà soldée, tranches non payées listées avec le bon libellé/échéance, tranches payées absentes.
- Destinataire : nom du tuteur principal si renseigné, formule générique sinon.

`tests/Feature/Http/PaymentRemindersBatchPdfTest.php` (nouveau) :
- Une page par élève en retard, élèves à jour exclus.
- Filtre par niveau.
- Message si aucun élève en retard dans le périmètre.
- Tenant isolation.

`tests/Feature/Livewire/Reports/IndexTest.php` : ajout du bloc "Lettres de relance" (lien présent, filtre niveau).
`tests/Feature/Livewire/Billing/PaymentTrackingIndexTest.php` (ou équivalent existant) : lien "Relance" visible seulement si `balance > 0`.

## Vérification

1. `vendor/bin/pest` — suite complète verte.
2. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
3. Vérification manuelle (environnement scratch SQLite, jamais la base réelle) : élève avec solde partiellement payé → tableau correct ; élève à jour → 404 en accès direct, pas de bouton "Relance" ; génération groupée par niveau ; rendu visuel A5 (dompdf → PyMuPDF).
4. Commit puis mise à jour de la mémoire projet.
