# Réductions de scolarité

## Contexte

Sous-chantier 4/4, le dernier de "gestion des paiements" (1 — structure tarifaire par niveau, commit `5567cb5`, 2 — génération des factures, commit `d151a4a`, 3 — suivi retards/avances, commit `c22f518` — tous livrés). Ce sous-chantier permet d'accorder une réduction de scolarité à un élève (fratrie, bourse, difficulté ponctuelle...), appliquée au moment où ses factures sont générées.

## 1. Modèle de données

Nouvelle table `discounts` :

```php
Schema::create('discounts', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained()->cascadeOnDelete();
    $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
    $table->string('type'); // percentage|fixed_amount
    $table->decimal('value', 12, 2);
    $table->string('reason')->nullable();
    $table->foreignId('created_by')->constrained('users');

    $table->char('uid', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->unique(['establishment_id', 'student_id', 'school_year_id']);
});
```

Un seul enregistrement par (élève, année scolaire) — pas de cumul de plusieurs réductions simultanées pour un même élève sur une même année. `App\Domain\Billing\Models\Discount` — `HasFactory`, `SoftDeletes`, `Syncable`, `TenantScoped`, relations `student()`/`schoolYear()`/`createdBy()`.

`value` s'interprète selon `type` : un pourcentage (0-100) ou un montant en devise de l'établissement, jamais les deux à la fois pour un même enregistrement.

## 2. Application au moment de la génération des factures

Modifie uniquement `TuitionFees\Index::generateInvoices()` (sous-chantier 2). Ne concerne **jamais** les frais d'inscription — seulement les factures de tranches de scolarité — et ne modifie **jamais** une facture déjà émise (immutabilité déjà en place, cohérente avec le reste du domaine tarifaire).

Pour chaque élève dont une facture de tranche est sur le point d'être créée, résoudre son `Discount` pour (élève, année scolaire sélectionnée) :

- **Aucune réduction** : `amount_due` = montant de la tranche, inchangé (comportement actuel).
- **Pourcentage** : `amount_due = montant_tranche × (1 − value / 100)` — appliqué directement à chaque tranche, proportionnel par construction.
- **Montant fixe** : représente la réduction **totale sur l'année** pour cet élève, répartie au prorata entre les tranches configurées du niveau : `réduction_tranche = value × montant_tranche / total_scolarité_du_niveau` (le total = somme des montants de tranche non nuls du `LevelFee` de ce niveau/année), puis `amount_due = montant_tranche − réduction_tranche`. Si le total du niveau est nul (aucune tranche configurée), aucune réduction n'est appliquée à cette génération — rien à répartir.

Montants arrondis à 2 décimales par facture, comme le reste du domaine Billing — pas de réconciliation centime-près entre tranches.

## 3. Policy

`App\Policies\DiscountPolicy` (même gabarit que `LevelFeePolicy`) : `viewAny`/`view` → `RolePermissions::can($role, 'finance.access')` (+ `belongsToSameEstablishment` pour `view`) ; `create`/`update`/`delete` → `RolePermissions::can($role, 'billing.manage')` (+ `belongsToSameEstablishment` pour `update`/`delete`) — mêmes rôles que le reste de la facturation courante (`directeur`/`caissier`/`educateur`), pas une restriction supplémentaire à `fondateur`/`directeur` seuls.

## 4. Écran "Réductions"

Nouveau `Livewire\Billing\Discounts\Index` (route `billing.discounts.index`, nav "Facturation") :
- Sélecteur d'année scolaire (défaut `is_current`).
- Recherche par nom d'élève.
- Tableau **Élève | Type | Valeur | Motif | Accordée par**, avec actions modifier/supprimer.
- Formulaire création/modification : élève (recherche), type (pourcentage/montant fixe), valeur, motif (optionnel).

## Hors périmètre

- Pas de cumul de plusieurs réductions pour un même élève sur une même année (une seule ligne par élève/année, une nouvelle réduction remplace l'ancienne si besoin — via modification, pas addition).
- Pas d'application rétroactive sur des factures déjà émises.
- Pas de reconduction automatique d'une année sur l'autre — à recréer explicitement chaque année si la réduction doit continuer.
- Pas de workflow d'approbation/validation à plusieurs niveaux — accorder une réduction est une action directe, comme le reste de la facturation courante.
