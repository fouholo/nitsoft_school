# Bilan financier — accès multi-établissements pour le fondateur — design

*Statut : approuvé par l'utilisateur le 2026-08-21.*

## Contexte

Extension du chantier « Bilan financier » livré au commit `74b45ca` (spec `2026-08-21-bilan-financier-par-profil-design.md`). Demande initiale : « Dans le profil du fondateur, normalement, il doit avoir accès au bilan financier de toutes les écoles de son organisation, aussi par école en filtrant. »

Constats issus de l'exploration du code existant :

- `Foundation` a `hasMany` vers `Establishment` (`establishments.foundation_id`) et `belongsToMany` vers `User` via `foundation_user` (colonnes `role`, `is_active`). Un fondateur a un accès admin-équivalent à chaque établissement du groupe sans ligne `establishment_user` par école (commentaire du modèle `Foundation`).
- `User::isFounderOf(int $foundationId)` / `isFounderOfEstablishment(int $establishmentId)` / `accessibleEstablishments()` existent déjà, mais aucune méthode ne retourne directement « tous les `establishment_id` de la/des Foundation(s) dont cet utilisateur est fondateur actif » — à dériver via `$user->foundations()->wherePivot('is_active', true)->pluck('foundations.id')` puis `Establishment::whereIn('foundation_id', $foundationIds)`.
- `Payment` et `Expense` utilisent `TenantScoped`, dont le global scope `EstablishmentScope` applique une égalité stricte `where('establishment_id', '=', app('currentEstablishmentId'))` — un seul établissement à la fois, jamais un `whereIn`. L'échappatoire existante `Model::withoutTenant()` retire ce scope (déjà utilisée une fois ailleurs, `Student::withoutTenant()` dans `GuardianPortal\LinkChild`, cas non lié).
- Aucun écran de l'app n'agrège aujourd'hui de données métier à travers plusieurs établissements. Le rôle `fondateur` a `finance.access` exactement comme les autres rôles dans `RolePermissions::MATRIX` — aucune distinction n'existe entre un fondateur d'une école indépendante et un fondateur d'un groupe de plusieurs écoles ; c'est une distinction purement structurelle (nombre d'établissements liés à sa/ses Foundation(s)), pas un nouveau rôle ou une nouvelle ability.
- `FinancialSummaryService::summaryByUser()` a déjà un paramètre `?int $establishmentId` mais il n'est utilisé aujourd'hui que pour résoudre le libellé de rôle (`$user->roleFor($establishmentId)`) — les requêtes `Payment`/`Expense` elles-mêmes s'appuient entièrement sur le global scope implicite (donc sur `app('currentEstablishmentId')`, un seul établissement).

## Décisions validées

1. **Filtre** : multi-sélection (cases à cocher), pas un simple sélecteur à une valeur — un fondateur doit pouvoir choisir un sous-ensemble précis d'écoles, pas seulement « une » ou « toutes ».
2. **Ventilation** : Établissement → Rôle → Utilisateur. Un bloc par établissement sélectionné, sous-totaux rôle/utilisateur identiques à l'écran actuel à l'intérieur de chaque bloc, total général tous établissements confondus en bas. Un même utilisateur actif dans deux écoles du groupe apparaît dans les deux blocs séparément (pas fusionné) — le fondateur doit pouvoir comparer ses écoles entre elles.
3. **Portée** : ce filtre n'apparaît que pour un utilisateur fondateur actif d'au moins une Foundation comptant 2+ établissements. Tous les autres profils — directeur, gestionnaire, caissier, éducateur, et un fondateur d'une école indépendante (sans Foundation ou Foundation à un seul établissement) — conservent l'écran actuel strictement inchangé (pas de filtre, pas de bloc établissement, comportement identique au commit `74b45ca`).
4. **PDF** : reflète exactement la sélection d'écoles de l'écran, avec la même structure Établissement → Rôle → Utilisateur.

## 1. Service — requêtage explicite par établissement

`FinancialSummaryService::summaryByUser()` change de signature : `$establishmentId` devient **obligatoire** (non nullable) et sert désormais de filtre de requête explicite, pas seulement à la résolution du rôle :

```php
public function summaryByUser(CarbonInterface $start, CarbonInterface $end, int $establishmentId, ?int $ownerId = null): array
{
    $collected = Payment::withoutTenant()
        ->where('establishment_id', $establishmentId)
        ->whereBetween('paid_at', [$start, $end])
        ->when($ownerId, fn ($query) => $query->where('received_by', $ownerId))
        ->selectRaw('received_by as user_id, SUM(amount) as total')
        ->groupBy('received_by')
        ->pluck('total', 'user_id')
        ->all();

    $spent = Expense::withoutTenant()
        ->where('establishment_id', $establishmentId)
        ->whereBetween('spent_at', [$start, $end])
        ->when($ownerId, fn ($query) => $query->where('recorded_by', $ownerId))
        ->selectRaw('recorded_by as user_id, SUM(amount) as total')
        ->groupBy('recorded_by')
        ->pluck('total', 'user_id')
        ->all();

    // ... reste inchangé, 'role' => $user->roleFor($establishmentId)
}
```

Ce changement rend le filtrage **toujours explicite**, y compris pour l'usage à un seul établissement (directeur, caissier, etc.) — supprime la dépendance implicite au global scope `EstablishmentScope`/`app('currentEstablishmentId')` à l'intérieur du service, ce qui simplifie le raisonnement (une seule voie de requêtage) et rend `summaryByUser()` testable sans manipuler le tenant courant.

Nouvelle méthode publique :

```php
/**
 * @param  list<int>  $establishmentIds
 * @return list<array{establishment_id: int, establishmentName: string, groups: list<array{...}>, collected: float, spent: float, net: float}>
 */
public function summaryByEstablishments(CarbonInterface $start, CarbonInterface $end, array $establishmentIds, ?int $ownerId = null): array
{
    $establishments = Establishment::whereIn('id', $establishmentIds)->get()->keyBy('id');

    $result = [];

    foreach ($establishmentIds as $establishmentId) {
        $summary = $this->summaryByUser($start, $end, $establishmentId, $ownerId);
        $groups = $this->groupByRole($summary);

        $result[] = [
            'establishment_id' => $establishmentId,
            'establishmentName' => $establishments->get($establishmentId)?->name ?? '—',
            'groups' => $groups,
            'collected' => (float) array_sum(array_column($groups, 'collected')),
            'spent' => (float) array_sum(array_column($groups, 'spent')),
            'net' => (float) array_sum(array_column($groups, 'net')),
        ];
    }

    return $result;
}
```

Une petite requête Payment + une petite requête Expense par établissement sélectionné — accepté : le nombre d'écoles d'un groupe reste toujours faible (quelques unités), et cette approche réutilise intégralement `summaryByUser()`/`groupByRole()` déjà testés plutôt que de dupliquer leur logique dans un regroupement à trois niveaux en un seul passage.

## 2. Détection « fondateur multi-écoles »

Nouvelle méthode privée partagée (dans le Livewire component et le contrôleur PDF, dupliquée volontairement comme le reste des filtres de cette famille d'écrans) :

```php
/**
 * @return Collection<int, Establishment>
 */
private function founderGroupEstablishments(User $user): Collection
{
    $foundationIds = $user->foundations()->wherePivot('is_active', true)->pluck('foundations.id');

    if ($foundationIds->isEmpty()) {
        return collect();
    }

    return Establishment::whereIn('foundation_id', $foundationIds)->orderBy('name')->get();
}
```

Le mode « multi-écoles » est actif quand `founderGroupEstablishments($user)->count() >= 2` — indépendant de l'établissement couramment sélectionné dans le switcher (un fondateur reste fondateur de tout son groupe quelle que soit l'école sur laquelle il est actuellement positionné).

## 3. Écran Livewire

`App\Livewire\Billing\FinancialSummary\Index` :

- `mount()` calcule `$this->groupEstablishments = $this->founderGroupEstablishments($user)` et `$this->isMultiSchoolFounder = $this->groupEstablishments->count() >= 2`. Si vrai, `$this->establishmentFilter` est initialisé à **toutes** les `establishment_id` du groupe (toutes les cases cochées par défaut).
- Nouvel état public `array $establishmentFilter = []` (liste d'IDs cochés), lié à des `<input type="checkbox">` (une par établissement de `$groupEstablishments`), affichées uniquement `@if ($isMultiSchoolFounder)`.
- **Validation serveur de la sélection** : à chaque `render()`, `$establishmentFilter` est réintersecté avec `$groupEstablishments->pluck('id')` — toute valeur reçue hors de cet ensemble autorisé est silencieusement écartée, jamais utilisée telle quelle. Ce n'est pas une simple UX : c'est la seule barrière empêchant un fondateur (ou un client Livewire manipulé) d'interroger les finances d'un établissement hors de son groupe.
- Aucune case cochée après filtrage → message « Sélectionnez au moins une école. », pas de requête (même traitement que la plage de dates invalide déjà en place).
- `render()` :
  - Si `! $isMultiSchoolFounder` : chemin **strictement inchangé** — `summaryByUser($start, $end, $currentEstablishmentId, $ownerId)` puis `groupByRole()`, vue actuelle.
  - Si `$isMultiSchoolFounder` : `summaryByEstablishments($start, $end, $establishmentFilter, $ownerId)` (note : `$ownerId` sera toujours `null` ici, `fondateur` n'étant jamais dans `finance.scope_own_only`). Résultat passé à la vue sous une clé distincte (`establishmentGroups`) pour que le template puisse choisir sans ambiguïté quelle structure afficher.

Vue : un `@if ($isMultiSchoolFounder)` bascule entre l'affichage simple existant (`$groups`) et un nouvel affichage imbriqué (`$establishmentGroups`) — un bloc par établissement (nom en en-tête, style visuellement distinct des lignes de rôle), puis à l'intérieur exactement le même gabarit rôle/utilisateur/sous-totaux que l'écran actuel (extrait dans un sous-vue partagée `livewire.billing.financial-summary.role-groups-table` pour éviter la duplication de markup entre les deux modes), puis un total général tous établissements confondus.

## 4. Export PDF

`FinancialSummaryPdfController` applique la même logique de détection et de validation de sélection (dupliquée, cohérent avec le reste du contrôleur qui reproduit déjà la résolution de période du composant Livewire). Query params : `establishment_ids[]` (liste), ignorés/filtrés par intersection avec le groupe autorisé du fondateur connecté. Si l'utilisateur n'est pas un fondateur multi-écoles, ce paramètre est ignoré et le comportement actuel (un seul établissement, celui de la session) s'applique intégralement.

Vue `pdf/financial-summary.blade.php` : structure identique à l'écran (bloc par établissement avec ses sous-totaux rôle/utilisateur, total général), réutilisant le même partial de table que l'écran pour éviter la duplication de markup entre HTML et PDF autant que possible (dans les limites de ce que dompdf accepte).

## 5. Tests

- `FinancialSummaryServiceTest` : `summaryByEstablishments()` — un établissement par bloc avec ses bons montants ; un utilisateur actif dans 2 écoles apparaît dans les deux blocs séparément avec des montants distincts (pas fusionné) ; total général = somme des blocs ; ordre des blocs = ordre des `establishmentIds` passés.
- `FinancialSummary\IndexTest` :
  - Le filtre écoles n'apparaît pas pour directeur/gestionnaire/caissier/éducateur, ni pour un fondateur d'une école indépendante (pas de Foundation, ou Foundation à un seul établissement).
  - Il apparaît pour un fondateur actif d'une Foundation à 2+ établissements, toutes les cases cochées par défaut.
  - Sélection partielle (une seule école cochée sur deux) → un seul bloc affiché.
  - Aucune case cochée → message dédié, pas de requête.
  - Isolation : un `establishment_id` hors du groupe du fondateur injecté directement dans `establishmentFilter` via `set()` n'apparaît jamais dans les résultats ni ne lève d'erreur — silencieusement filtré.
- `FinancialSummaryPdfTest` : même structure reflétée en PDF ; même filtrage serveur d'un `establishment_id` hors groupe passé en query string ; un directeur (non-fondateur) passant `establishment_ids[]` en query string voit son paramètre totalement ignoré (comportement actuel inchangé, un seul établissement).

## Hors périmètre (pour l'instant)

- Étendre ce filtre multi-établissement à d'autres rôles que fondateur.
- Fusion optionnelle des lignes utilisateur à travers les établissements (l'option écartée lors du brainstorming) — pourra être ajoutée plus tard comme bascule d'affichage si le besoin se confirme.
- Export autre que PDF (Excel/CSV) pour cette vue multi-établissements.
