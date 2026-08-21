# Tableau de bord fondateur / directeur / gestionnaire — design

*Statut : approuvé par l'utilisateur le 2026-08-21.*

## Contexte

Demande initiale : « On va revoir le tableau de bord du fondateur. » Après clarification, la portée retenue est une refonte du **contenu** du tableau de bord (`App\Livewire\Dashboard`, route `/dashboard`), pas une simple correction ponctuelle.

Constats issus de l'exploration du code existant :

- Un seul composant Livewire `App\Livewire\Dashboard` (pas de composant par rôle) ; la vue Blade `resources/views/livewire/dashboard.blade.php` branche l'affichage par rôle via des `in_array($role, [...])`.
- Aujourd'hui, tous les rôles voient jusqu'à 4 tuiles : Élèves actifs, Classes (année en cours), Factures en attente + solde (`fondateur, directeur, gestionnaire, caissier, educateur`), Membres du personnel (`directeur, gestionnaire, fondateur`). Tout est scopé au seul établissement courant (`app('currentEstablishmentId')`, via `TenantScoped` sur `Student`/`Classroom`, filtre explicite sur `EstablishmentUserPivot` pour le personnel, `PaymentTrackingService::balances()` pour les factures).
- Aucune conscience multi-établissement, contrairement à l'écran « Bilan financier » (commits `74b45ca`/`3cc7144`) qui a déjà introduit le pattern fondateur multi-écoles : `founderGroupEstablishments()` (dupliqué dans `FinancialSummary\Index` et `FinancialSummaryPdfController`), filtre multi-sélection, validation serveur par intersection.
- Une critique UX existante (`.impeccable/critique/2026-08-18T23-33-49Z__apps-api-app-livewire-dashboard-php.md`, score 23/40) signale, entre autres : sidebar mobile cassée (P0, hors scope — problème de layout global), « Accès rapides » pas systématiquement filtré comme les tuiles (P1), stats à zéro peu lisibles (P1), contexte année scolaire/période insuffisant.
- Données disponibles pour les alertes envisagées (vérifié) : pas de concept de « titulaire » de classe en base (seulement la table `teacher_classroom_subject`, affectations enseignant↔matière) ; `classrooms.capacity` existe (nullable, sans minimum) ; `report_cards` n'a pas de statut, seul `generated_at` (nullable) distingue généré/non généré ; `Term` a `starts_on`/`ends_on` mais pas de flag `is_current` ; `Installment.due_date` est directement exploitable pour les retards.

## Décisions validées

1. **Contenu nouveau, pour `fondateur`, `directeur`, `gestionnaire` uniquement** (caissier/éducateur/autres rôles : dashboard strictement inchangé) :
   - **Santé financière** : encaissé/dépensé/net sur l'année scolaire en cours, lien vers l'écran Bilan financier complet.
   - **Tendance** : évolution du montant encaissé du mois en cours vs le mois précédent.
   - **Alertes** : 4 signaux (détaillés en section 2 du design, repris ci-dessous).
2. **Fondateur d'un groupe multi-écoles** (Foundation à 2+ établissements) voit en plus :
   - Un filtre « Écoles » multi-sélection, identique dans son principe à celui du Bilan financier (toutes cochées par défaut, validation serveur par intersection avec le groupe réel de l'utilisateur — jamais fié à la valeur cliente).
   - Santé financière / Tendance / Alertes s'agrègent sur les écoles sélectionnées ; chaque alerte indique l'établissement concerné.
   - Une **comparaison entre écoles** : une carte par établissement sélectionné, avec ses propres chiffres clés.
3. **Alertes** :
   - *Factures en retard* : échéances `Installment.due_date` dépassées d'au moins 1 jour, non soldées.
   - *Classes sans aucun enseignant* : classes actives de l'année scolaire courante sans aucune ligne `teacher_classroom_subject` (remplace l'idée initiale « sans titulaire », concept absent du modèle actuel — ajouter un tel concept est un chantier séparé, volontairement hors scope).
   - *Effectif dépassé* : `enrollments count > classrooms.capacity`, uniquement quand `capacity` est renseignée ; aucun seuil bas (pas de minimum en base, pas de seuil arbitraire introduit).
   - *Bulletins non finalisés* : élèves inscrits dans une classe dont le `Term` est terminé (`ends_on < now()`) mais dont le `ReportCard` correspondant a `generated_at` null ou n'existe pas.
   - Gating par liste de rôles directe (`fondateur`, `directeur`, `gestionnaire`), comme le fait déjà `staffCount` — pas de nouvelle ability Policy.
   - Pour un fondateur multi-écoles : alertes agrégées sur les écoles sélectionnées, chacune taguée avec le nom de l'établissement. Une école sans alerte n'affiche rien pour elle.
4. **Architecture** : composants Livewire enfants imbriqués dans `Dashboard.php`, synchronisés par événement (détail section 3). Filtre écoles propre à cet écran, indépendant de celui du Bilan financier (pas de session partagée).
5. **Correctifs UX inclus** (car les zones sont de toute façon réécrites) : alignement des permissions du bloc « Accès rapides » sur celles des tuiles, état « 0 » explicite et non ambigu, contexte établissement/année scolaire toujours visible dans l'en-tête (y compris « Vue consolidée : N écoles » pour un fondateur multi-écoles).
6. **Hors scope** : correction de la sidebar mobile (P0, problème de layout global) ; ajout d'un concept de titulaire de classe en base ; extension de ces sections aux rôles caissier/éducateur/enseignant.

## 1. Services

### 1.1 `FinancialSummaryService::totalsForEstablishments()`

Nouvelle méthode légère, sans ventilation par utilisateur (contrairement à `summaryByUser()`/`summaryByEstablishments()`, pensées pour l'écran Bilan financier) :

```php
/**
 * @param  list<int>  $establishmentIds
 * @return array{collected: float, spent: float, net: float}
 */
public function totalsForEstablishments(CarbonInterface $start, CarbonInterface $end, array $establishmentIds): array
{
    $collected = (float) Payment::withoutTenant()
        ->whereIn('establishment_id', $establishmentIds)
        ->whereBetween('paid_at', [$start, $end])
        ->sum('amount');

    $spent = (float) Expense::withoutTenant()
        ->whereIn('establishment_id', $establishmentIds)
        ->whereBetween('spent_at', [$start, $end])
        ->sum('amount');

    return ['collected' => $collected, 'spent' => $spent, 'net' => $collected - $spent];
}
```

Un seul `whereIn` (pas une requête par établissement) : contrairement à `summaryByEstablishments()`, on ne cherche pas de détail par établissement ici — un simple total agrégé suffit pour le widget de santé financière et pour la tendance (deux appels, un pour le mois courant, un pour le mois précédent).

### 1.2 `DashboardAlertsService` (nouveau)

`app/Domain/Dashboard/Services/DashboardAlertsService.php`. Une méthode publique par type d'alerte, toutes acceptant `list<int> $establishmentIds` et retournant une liste d'items uniformes :

```php
/**
 * @return list<array{type: string, label: string, establishment_id: int, establishmentName: string, link: string}>
 */
```

- `overdueInvoices(array $establishmentIds): array` — s'appuie sur les données déjà exposées par `PaymentTrackingService` (échéances `Installment` par établissement), filtre `due_date < today` et solde restant > 0.
- `classroomsWithoutTeacher(array $establishmentIds): array` — classes actives de l'année scolaire courante par établissement, `whereDoesntHave('teacherAssignments')` (ou équivalent sur `teacher_classroom_subject`).
- `classroomsOverCapacity(array $establishmentIds): array` — classes avec `capacity` non nulle et `enrollments_count > capacity` (`withCount('enrollments')`).
- `unfinalizedReportCards(array $establishmentIds): array` — élèves inscrits dans une classe dont le `Term` a `ends_on < now()`, sans `ReportCard.generated_at` renseigné pour ce term ; regroupé par classe pour éviter une alerte par élève (une entrée par classe avec le nombre d'élèves concernés).

Chaque méthode charge le nom de l'établissement une seule fois (`Establishment::whereIn('id', $establishmentIds)->pluck('name', 'id')`) pour taguer ses résultats, évitant les micro-instructions dans une boucle.

### 1.3 `User::founderGroupEstablishments()`

Actuellement dupliquée en méthode privée dans `FinancialSummary\Index` et `FinancialSummaryPdfController`. Promue en méthode publique sur le modèle `User` (aux côtés de `accessibleEstablishments()`, `isFounderOfEstablishment()`) :

```php
/**
 * @return Collection<int, Establishment>
 */
public function founderGroupEstablishments(): Collection
{
    $foundationIds = $this->foundations()->wherePivot('is_active', true)->pluck('foundations.id');

    if ($foundationIds->isEmpty()) {
        return collect();
    }

    return Establishment::whereIn('foundation_id', $foundationIds)->orderBy('name')->get();
}
```

`FinancialSummary\Index` et `FinancialSummaryPdfController` sont mis à jour pour appeler `$user->founderGroupEstablishments()` au lieu de leur copie privée (suppression de la duplication, comportement strictement identique).

## 2. Composants Livewire

Tous sous `App\Livewire\Dashboard\`, imbriqués dans la vue `dashboard.blade.php` du composant parent existant, uniquement pour les rôles `fondateur`/`directeur`/`gestionnaire` :

- **`EstablishmentFilter`** — rendu seulement si `auth()->user()->founderGroupEstablishments()->count() >= 2`. État public `array $selected` initialisé à tous les IDs du groupe. À chaque changement (`wire:model.live`), `dispatch('establishment-filter-changed', establishmentIds: $this->selected)`. Aucune case cochée → `$selected = []` est propagé tel quel ; chaque widget récepteur affiche alors « Sélectionnez au moins une école. » au lieu de lancer une requête sur un `whereIn` vide (même traitement que sur le Bilan financier).
- **`FinancialHealthWidget`**, **`AlertsWidget`**, **`TrendWidget`** — chacun résout son `$establishmentIds` au montage : `[app('currentEstablishmentId')]` par défaut. `#[On('establishment-filter-changed')]` met à jour cette propriété. **Avant tout usage**, quel que soit l'événement reçu, le composant réintersecte `$establishmentIds` avec `auth()->user()->founderGroupEstablishments()->pluck('id')` s'il est fondateur multi-écoles (même garde que sur le Bilan financier — l'événement client n'est jamais une source d'autorisation).
- **`EstablishmentComparisonWidget`** — même mécanisme, rendu seulement si fondateur multi-écoles ; une carte par établissement dans `$establishmentIds` (chiffres calculés individuellement par établissement, pas de fusion).

Le parent `Dashboard.php` continue de porter les 4 tuiles existantes (logique inchangée) et détermine seulement quels widgets enfants monter, selon le rôle courant.

## 3. Vue

`resources/views/livewire/dashboard.blade.php` :

- Bloc existant des 4 tuiles, inchangé dans sa logique ; état « 0 » désormais explicite (ex. libellé atténué « Aucun(e) » plutôt qu'un simple « 0 »).
- En-tête : établissement + année scolaire courante ; si fondateur multi-écoles, remplacé par « Vue consolidée : {n} écoles » (ou le nom de l'unique établissement si un seul est sélectionné dans le filtre).
- `@if (in_array($role, ['fondateur', 'directeur', 'gestionnaire']))` : rendu de `<livewire:dashboard.establishment-filter />` (seulement si applicable, la condition interne au composant gère déjà le cas), `<livewire:dashboard.financial-health-widget />`, `<livewire:dashboard.trend-widget />`, `<livewire:dashboard.alerts-widget />`, puis `<livewire:dashboard.establishment-comparison-widget />` (fondateur multi-écoles uniquement).
- Bloc « Accès rapides » : audit des `@can` existants pour vérifier l'alignement avec les rôles qui voient effectivement les tuiles/sections correspondantes (pas de nouveau mécanisme de permission, correction de cohérence).

## 4. Tests

- `Domain/DashboardAlertsServiceTest.php` : un groupe de tests par type d'alerte — cas positif, cas négatif, exclusion des classes sans `capacity`, agrégation multi-établissements avec tag du bon nom d'établissement par item.
- `Domain/FinancialSummaryServiceTest.php` : cas pour `totalsForEstablishments()` (agrégation simple, plage vide → zéros).
- `Livewire/Dashboard/EstablishmentFilterTest.php`, `FinancialHealthWidgetTest.php`, `TrendWidgetTest.php`, `AlertsWidgetTest.php`, `EstablishmentComparisonWidgetTest.php` :
  - Rendu par rôle (visible pour fondateur/directeur/gestionnaire, absent pour caissier/éducateur/enseignant).
  - Isolation tenant : établissement courant uniquement par défaut.
  - Fondateur multi-écoles : événement `establishment-filter-changed` propage correctement la sélection ; un `establishment_id` hors groupe injecté directement (`set()`/événement forgé) est silencieusement filtré, jamais utilisé.
  - Widgets absents pour un fondateur d'une école indépendante ou d'un groupe à une seule école (même garde que sur le Bilan financier).
- Réutilisation du helper `createFounder(Foundation $foundation)` déjà en place.

## Hors périmètre (pour l'instant)

- Correction de la sidebar mobile (P0 de la critique UX) — problème de layout global, pas spécifique au contenu du dashboard.
- Ajout d'un concept de titulaire de classe en base.
- Extension des nouvelles sections (santé financière, tendance, alertes, comparaison) aux rôles caissier, éducateur, enseignant.
- Seuil bas d'effectif (aucune donnée en base ne le justifie aujourd'hui).
- Partage de l'état du filtre écoles entre le Dashboard et l'écran Bilan financier.
- Export PDF du tableau de bord.
