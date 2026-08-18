# Tarifs par niveau : distinction élèves affectés / non affectés

*(Design validé par l'utilisateur le 2026-08-18.)*

## Contexte

Le chantier "Tarifs par niveau" (`app/Livewire/Billing/TuitionFees/Index.php`) traite aujourd'hui tous les élèves d'un même niveau de façon uniforme : un seul frais d'inscription (`LevelFee.registration_amount`) et les mêmes tranches de scolarité pour tous.

L'utilisateur signale que pour le secondaire, deux populations existent :
- **Élève affecté** (affectation post-BEPC) : frais d'inscription différent, et **aucun frais de scolarité** ensuite.
- **Élève non affecté** : comportement actuel (frais d'inscription + toutes les tranches de scolarité).

`Enrollment.is_assigned` existe déjà (chantier précédent de cette même session, commit `f589607`, spec `docs/superpowers/specs/2026-08-18-statuts-inscription-secondaire-design.md`) : booléen exposé uniquement dans le formulaire d'inscription quand la classe sélectionnée est de cycle secondaire, toujours `false` ailleurs.

Vérifié en base réelle (WAMP) : une seule `LevelFee` existe actuellement, pour CP1 (primaire) — aucune donnée secondaire préexistante à migrer.

## 1. Schéma

Migration ajoutant une colonne à `level_fees` :

```php
Schema::table('level_fees', function (Blueprint $table): void {
    $table->decimal('registration_amount_assigned', 10, 2)->nullable()->after('registration_amount');
});
```

`LevelFee::$fillable`/`$casts` gagnent `registration_amount_assigned` (cast `decimal:2`, comme `registration_amount`).

Aucune table séparée, aucune ligne dupliquée — une seule ligne `LevelFee` par (établissement, année scolaire, niveau), comme aujourd'hui.

## 2. Formulaire "Configurer" (`TuitionFees\Index`)

`configureLevel()` calcule si le niveau est de cycle secondaire (`Level::find($levelId)->cycle === Cycle::Secondaire`) et expose ce booléen à la vue sous `configuringLevelIsSecondaire`. Nouvelle propriété `public ?float $registration_amount_assigned = null;`, préremplie dans `configureLevel()` comme `registration_amount` l'est déjà.

`saveLevelFees()` valide `registration_amount_assigned` comme `['nullable', 'numeric', 'min:0']` et l'inclut dans les données passées à `LevelFee::updateOrCreate(...)`.

Dans `resources/views/livewire/billing/tuition-fees/index.blade.php`, le champ "Frais d'inscription (affecté)" n'apparaît dans le formulaire que si `$configuringLevelIsSecondaire` est vrai — juste après le champ "Frais d'inscription" existant.

## 3. Tableau "Tarifs par niveau"

Pour une ligne de niveau secondaire, la colonne "Frais d'inscription" affiche les deux montants :

```
Non affecté : 15 000 F CFA
Affecté : 25 000 F CFA
```

Pour les autres cycles, seul le montant `registration_amount` s'affiche (comportement actuel inchangé).

## 4. Génération des factures (`generateInvoices()`)

Les inscriptions actives du niveau (`$enrollments`, déjà chargées) sont scindées :

```php
$assignedEnrollments = $enrollments->where('is_assigned', true);
$notAssignedEnrollments = $enrollments->where('is_assigned', false);
```

**Frais d'inscription** — même logique de garde qu'aujourd'hui (une seule facture par élève, `whereNull('installment_id')`), mais montant choisi selon le groupe :
- `$notAssignedEnrollments` → `$levelFee->registration_amount` (si > 0).
- `$assignedEnrollments` → `$levelFee->registration_amount_assigned` (si renseigné et > 0).

**Tranches de scolarité** — générées uniquement pour `$notAssignedEnrollments`, boucle inchangée sinon (garde par élève déjà facturé, réductions appliquées pareil).

**Correction lors d'un changement de statut** — après la génération ci-dessus, pour chaque élève de `$assignedEnrollments` (donc actuellement affecté) : supprimer (soft delete, `Invoice` utilise déjà `SoftDeletes`) toute facture de tranche (`installment_id` non null, portant sur un des `installmentAmounts` de ce niveau) devenue injustifiée — **seulement si `amount_paid == 0`** (aucun paiement, même partiel, jamais touché) :

```php
foreach ($assignedEnrollments as $enrollment) {
    Invoice::where('student_id', $enrollment->student_id)
        ->where('school_year_id', $this->school_year_id)
        ->whereIn('installment_id', $levelFee->installmentAmounts->pluck('installment_id'))
        ->where('amount_paid', 0)
        ->delete();
}
```

Aucune correction symétrique n'est nécessaire côté "devenu non affecté" : la boucle de génération des tranches (ci-dessus) les crée déjà normalement au prochain clic, puisque ces élèves font maintenant partie de `$notAssignedEnrollments`.

La facture d'inscription déjà émise n'est jamais recalculée après coup (même principe déjà en vigueur : modifier un tarif après génération ne change pas les factures déjà émises, testé dans `TuitionFees/IndexTest.php`).

## 5. Tests

Extension de `tests/Feature/Livewire/Billing/TuitionFees/IndexTest.php` :

- Un niveau secondaire avec deux élèves (un affecté, un non affecté) : génération produit la bonne facture d'inscription pour chacun (montant différent), et seul le non-affecté reçoit les factures de tranches.
- Le champ "Frais d'inscription (affecté)" n'apparaît pas pour un niveau primaire/préscolaire (`assertDontSee`), apparaît pour un niveau secondaire (`assertSee`).
- Un élève passe de non-affecté à affecté après une première génération (avec tranches déjà émises, impayées) : relancer la génération supprime ces tranches, ne touche pas à la facture d'inscription déjà émise.
- Une tranche déjà partiellement payée pour un élève devenu affecté n'est **pas** supprimée lors d'une régénération.
- Un élève passe d'affecté à non-affecté après une première génération (facture d'inscription seule) : relancer la génération ajoute les tranches manquantes sans dupliquer la facture d'inscription.

## Vérification

1. `php artisan migrate` (WAMP + suite de tests).
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle sur données réelles WAMP : configurer un niveau secondaire avec les deux frais d'inscription et une tranche, inscrire un élève affecté et un non affecté, générer les factures, confirmer la différence ; basculer le statut d'un élève et confirmer la correction au clic suivant.
5. Commit puis mise à jour de la mémoire projet.
