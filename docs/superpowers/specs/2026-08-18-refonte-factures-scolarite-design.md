# Refonte du système de factures de scolarité

*(Design validé par l'utilisateur le 2026-08-18.)*

## Contexte

Le système actuel de facturation repose sur `LevelFee`/`LevelFeeInstallment` (tarifs configurés par niveau) et une génération manuelle par lot (`TuitionFees\Index::generateInvoices()`) qui crée une ligne `Invoice` par élève et par tranche. Les paiements sont encaissés via `Invoices\Show` et rattachés à une `Invoice` (`Payment.invoice_id`).

L'utilisateur souhaite remplacer entièrement ce mécanisme : au lieu de factures générées en lot puis consultées séparément, les montants dus (frais d'inscription + jusqu'à 7 tranches) sont enregistrés directement sur l'inscription (`Enrollment`) de chaque élève — pré-remplis depuis les tarifs du niveau, puis modifiables individuellement. Les paiements restent enregistrés individuellement (avec reçu), mais se rattachent désormais à l'inscription plutôt qu'à une facture.

Ce chantier a été discuté avec un avertissement explicite sur le risque de synchronisation offline-first : contrairement à `Invoice`/`Payment` (tables à écriture additive, qui fusionnent sans perte entre appareils hors-ligne), un champ agrégé unique (`total_paid`) sur `Enrollment` se synchronise par LWW (dernier écrit gagne) et peut transitoirement diverger si deux appareils encaissent hors-ligne au même moment sur le même élève. L'utilisateur a confirmé vouloir procéder malgré ce risque, avec la mitigation suivante : `Payment` reste la source de vérité (table additive, sûre), et `total_paid` est **recalculé** (jamais incrémenté) à chaque paiement à partir de la somme des `Payment` existants — ce qui rend le champ auto-cicatrisant dès le prochain paiement, même après une divergence transitoire de synchronisation.

Périmètre confirmé : un seul chantier, couvrant l'espace personnel (staff) et l'espace parent (`GuardianPortal`).

Données réelles actuelles (WAMP) : 8 factures, 8 paiements, 2 réductions, 5 inscriptions actives — données de démonstration, migration "repartir de zéro" (pas de script de conversion).

## 1. Schéma

### 1.1 `enrollments` — nouvelles colonnes

```php
Schema::table('enrollments', function (Blueprint $table): void {
    $table->decimal('registration_amount', 10, 2)->nullable()->after('is_assigned');
    $table->decimal('installment_1_amount', 10, 2)->nullable()->after('registration_amount');
    $table->decimal('installment_2_amount', 10, 2)->nullable()->after('installment_1_amount');
    $table->decimal('installment_3_amount', 10, 2)->nullable()->after('installment_2_amount');
    $table->decimal('installment_4_amount', 10, 2)->nullable()->after('installment_3_amount');
    $table->decimal('installment_5_amount', 10, 2)->nullable()->after('installment_4_amount');
    $table->decimal('installment_6_amount', 10, 2)->nullable()->after('installment_5_amount');
    $table->decimal('installment_7_amount', 10, 2)->nullable()->after('installment_6_amount');
    $table->decimal('total_paid', 10, 2)->default(0)->after('installment_7_amount');
});
```

`installment_N_amount` correspond au montant dû pour l'`Installment` de position `N` de l'année scolaire de l'inscription. Si l'année scolaire ne compte que 5 tranches configurées, seuls `installment_1_amount`…`installment_5_amount` sont utilisés/affichés ; `installment_6_amount`/`installment_7_amount` restent `null`. Plafond fixe de 7 tranches (demande explicite de l'utilisateur) — une année scolaire configurant plus de 7 `Installment` n'est pas prise en charge par ce schéma.

`Enrollment::$fillable`/`$casts` gagnent ces 9 colonnes (cast `decimal:2`).

### 1.2 `payments` — remplacement de `invoice_id`

```php
Schema::table('payments', function (Blueprint $table): void {
    $table->foreignId('enrollment_id')->after('student_id')->constrained()->cascadeOnDelete();
    $table->index(['establishment_id', 'enrollment_id']);
});
Schema::table('payments', function (Blueprint $table): void {
    $table->dropIndex(['establishment_id', 'invoice_id']);
    $table->dropConstrainedForeignId('invoice_id');
});
```

(`invoice_id` porte aujourd'hui un index composite `['establishment_id', 'invoice_id']` — à retirer explicitement avant `dropConstrainedForeignId`, sous peine d'échec de la migration.)

`Payment::$fillable` : `invoice_id` retiré, `enrollment_id` ajouté. Relation `invoice()` retirée, relation `enrollment(): BelongsTo` ajoutée. Le reste (`student_id`, `amount`, `method`, `paid_at`, `reference`, `received_by`, `tuition_paid_total`, `tuition_remaining`, `next_installment_due_date`, `next_installment_amount`, `uid_local`/`uid_serveur`) est inchangé — `Payment` reste la table d'audit des encaissements et la source du numéro de reçu (`receiptNumber()`).

### 1.3 Suppression d'`Invoice`

- Migration `Schema::dropIfExists('invoices')`.
- Suppression : `app/Domain/Billing/Models/Invoice.php`, `app/Policies/InvoicePolicy.php`, `database/factories/InvoiceFactory.php`.
- Suppression des écrans `app/Livewire/Billing/Invoices/Index.php`, `app/Livewire/Billing/Invoices/Show.php` et leurs vues.
- Routes `billing.invoices.index`/`billing.invoices.show` retirées de `routes/billing.php`.

## 2. Pré-remplissage à l'inscription

Dans `Students\Show::saveEnrollment()`, une fois la classe/niveau connus, avant la création de l'`Enrollment` :

```php
$levelFee = LevelFee::where('school_year_id', $data['school_year_id'])
    ->where('level_id', $classroom->level_id)
    ->with('installmentAmounts.installment')
    ->first();

$data['registration_amount'] = $levelFee
    ? (float) ($data['is_assigned'] && $levelFee->registration_amount_assigned !== null
        ? $levelFee->registration_amount_assigned
        : $levelFee->registration_amount)
    : 0.0;

foreach (range(1, 7) as $position) {
    $data["installment_{$position}_amount"] = (! $data['is_assigned'] && $levelFee)
        ? (float) ($levelFee->installmentAmounts->firstWhere('installment.position', $position)?->amount ?? 0)
        : 0.0;
}
```

(Un élève `is_assigned` conserve donc le comportement déjà en place : frais d'inscription spécifique, aucune tranche de scolarité.)

Si un `Discount` existe déjà pour cet élève sur cette année scolaire, il est appliqué immédiatement après (section 3) — dans la même méthode, avant `create()`.

Ces montants sont ensuite des champs numériques modifiables librement sur la fiche d'inscription (formulaire `Students\Show`, visibles pour toute inscription, pas seulement secondaire — contrairement aux 4 statuts booléens déjà en place). Modifier le tarif du niveau après coup ne recalcule jamais rétroactivement une inscription existante.

## 3. Réductions (`Discount`)

Nouvelle méthode `DiscountService::applyToEnrollment(Enrollment $enrollment, Discount $discount): void` :

1. Recharge les 7 montants de tranche de l'inscription à partir des valeurs par défaut du niveau (`LevelFee`/`LevelFeeInstallment`, même logique que la section 2), **sans toucher `registration_amount`**.
2. Calcule le montant à retrancher : `$discount->type === 'percentage'` → `value% × somme(installment_1..7 par défaut)` ; `fixed_amount` → `value` directement.
3. Retranche ce montant en partant de `installment_7_amount` vers `installment_1_amount`, chaque tranche plafonnée à 0 (jamais négative). Si le montant de réduction dépasse la somme des 7 tranches, le reliquat est perdu — `registration_amount` n'est jamais affecté.
4. Sauvegarde l'inscription.

Repartir des valeurs par défaut du niveau à chaque application (plutôt que déduire depuis l'état actuel) évite un double retranchement si la réduction est modifiée plusieurs fois — au prix d'écraser un éventuel ajustement manuel fait entre-temps sur une tranche (accepté par l'utilisateur).

`Discounts\Index::save()` appelle `applyToEnrollment()` juste après `updateOrCreate` du `Discount`, pour l'inscription active de l'élève sur l'année scolaire concernée (si elle existe — sinon l'application se fera au moment de l'inscription, section 2). `delete()` réinitialise les 7 tranches aux valeurs par défaut du niveau (sans retranchement).

## 4. Paiements

### 4.1 `PaymentService`

```php
public function recordPayment(Enrollment $enrollment, array $data, User $receivedBy): Payment
{
    return DB::transaction(function () use ($enrollment, $data, $receivedBy) {
        $payment = $enrollment->payments()->create([
            'establishment_id' => $enrollment->establishment_id,
            'student_id' => $enrollment->student_id,
            'amount' => $data['amount'],
            'method' => $data['method'],
            'paid_at' => $data['paid_at'],
            'reference' => $data['reference'] ?? null,
            'received_by' => $receivedBy->id,
        ]);

        $enrollment->total_paid = (float) $enrollment->payments()->sum('amount');
        $enrollment->save();

        return $payment;
    });
}
```

Le "snapshot financier" figé sur chaque `Payment` (`tuition_paid_total`, `tuition_remaining`, `next_installment_due_date`, `next_installment_amount` — chantier `2026-08-13-instantane-financier-paiement-design.md`) est recalculé à partir des 7 `installment_N_amount` de l'inscription (dû total = somme des tranches non nulles) et de `total_paid`, en conservant la même logique d'imputation cumulative déjà en place (`Invoice::nextDueDateAfterCumulativePayments`, adaptée pour itérer sur les 7 tranches ordonnées par échéance `Installment.due_date` plutôt que sur une collection d'`Invoice`).

### 4.2 Écran de saisie

`Invoices\Show` est remplacé par `app/Livewire/Billing/Enrollments/Show.php` (route `billing.enrollments.show`, `{enrollment}`), affichant :

- Frais d'inscription + 7 tranches (montant dû, avec libellé/échéance de l'`Installment` correspondant quand il existe).
- Total dû, `total_paid`, solde restant.
- Historique des paiements (`enrollment->payments()->latest('paid_at')`) avec numéro de reçu, bouton "Ajouter un paiement" (reprend `addPayment()`/`savePayment()` de l'ancien `Invoices\Show`, adapté à `Enrollment`).

## 5. Écrans impactés

- **`TuitionFees\Index`** : `generateInvoices()` et le bouton "Générer les factures" sont retirés. L'écran ne sert plus qu'à configurer les valeurs par défaut (`LevelFee`/`LevelFeeInstallment`), utilisées à l'inscription et lors de l'application d'une réduction.
- **`PaymentTracking\Index` / `PaymentTrackingService`** : `balances()` recalculé à partir d'`Enrollment` :

  ```php
  Enrollment::where('school_year_id', $schoolYearId)->where('status', 'active')
      ->with('classroom.level')
      ->get()
      ->map(function (Enrollment $enrollment) {
          $dueSoFar = (float) $enrollment->registration_amount;
          foreach ($enrollment->installmentsDue() as $installment) { // ['amount' => float, 'due_date' => Carbon], déjà échues
              $dueSoFar += $installment['amount'];
          }
          return ['student_id' => $enrollment->student_id, 'due_so_far' => $dueSoFar, 'total_paid' => (float) $enrollment->total_paid, 'balance' => $dueSoFar - (float) $enrollment->total_paid];
      });
  ```

  (`installmentsDue()` : nouvelle méthode sur `Enrollment`, retourne un tableau de `['amount' => float, 'due_date' => Carbon]` pour chaque `installment_N_amount` non nul déjà échu, en l'associant à l'échéance de l'`Installment` de position N pour l'année scolaire.)
- **`GuardianPortal\StudentInvoices`** (renommé `StudentBilling`, route `students.billing`) : liste, pour l'inscription active de l'élève, le détail dû/versé par poste (inscription + chaque tranche) et l'historique des paiements — remplace la liste de factures.
- **Nav** (`resources/views/layouts/app.blade.php`) : entrée "Factures" retirée (ligne 51). "Suivi des paiements" conservé tel quel.
- **Dashboard** (`app/Livewire/Dashboard.php`, `resources/views/livewire/dashboard.blade.php`) : `pendingInvoicesCount`/`pendingInvoicesBalance` recalculés depuis `PaymentTrackingService::balances()` (nombre d'inscriptions avec `balance > 0`, somme des soldes positifs) ; le lien "Factures" du widget "Accès rapides" est retiré (ou redirigé vers "Suivi des paiements").
- **Guardian portal nav** (`resources/views/livewire/guardian-portal/dashboard.blade.php`) : lien "Factures" pointé vers la nouvelle route `students.billing`.

## 6. Migration des données existantes

Migration Laravel standard (ajout colonnes `enrollments`, modification FK `payments`, suppression `invoices`). Aucune donnée n'est convertie — les 8 factures et 8 paiements de démonstration actuels sont perdus (confirmé par l'utilisateur, "repartir de zéro"). Les 2 réductions existantes ne sont pas affectées par la migration (table `discounts` inchangée) mais ne sont plus reflétées sur aucune inscription tant qu'elles ne sont pas ré-enregistrées (`save()` déclenchera `applyToEnrollment()`).

## 7. Tests

Extension/réécriture de `tests/Feature/Livewire/Billing/` :

- **Pré-remplissage** (`ShowEnrollmentTest.php` ou nouveau) : inscription d'un élève non affecté reprend les 8 montants du `LevelFee` du niveau ; élève affecté reprend `registration_amount_assigned` et 7 tranches à 0 ; niveau sans `LevelFee` configuré → tous les montants à 0.
- **Réductions** (`DiscountsIndexTest.php`) : réduction pourcentage/montant fixe retranchée tranche 7→1 sur une inscription existante ; réduction dépassant la somme des tranches plafonnée à 0 sans toucher `registration_amount` ; modification d'une réduction déjà appliquée ne double-retranche pas (repart des valeurs par défaut) ; suppression réinitialise aux valeurs par défaut.
- **Paiements** (`PaymentServiceTest.php` existant, adapté) : `recordPayment()` crée un `Payment` lié à l'inscription et recalcule `total_paid` comme somme des paiements ; snapshot financier figé correct ; suppression d'un paiement (policy `payments.delete`) suivie d'un nouveau paiement recalcule correctement.
- **Suivi des paiements** (`PaymentTrackingServiceTest.php` existant, adapté) : soldes recalculés depuis `Enrollment`.
- **Espace parent** : `StudentBilling` affiche le détail dû/versé et l'historique des paiements de l'élève.
- Larastan clean sur l'ensemble des fichiers modifiés/supprimés.

## Vérification

1. `php artisan migrate` (WAMP + suite de tests SQLite).
2. `vendor/bin/pest` — suite complète verte (après réécriture des tests touchant `Invoice`).
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle sur données réelles WAMP : inscrire un élève (vérifier le pré-remplissage), appliquer une réduction, encaisser un paiement partiel puis complémentaire (vérifier reçu + solde), consulter "Suivi des paiements" et l'espace parent.
5. Commit puis mise à jour de la mémoire projet.
